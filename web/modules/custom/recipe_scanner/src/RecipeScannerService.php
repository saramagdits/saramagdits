<?php

namespace Drupal\recipe_scanner;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for scanning recipe photos via OpenAI GPT-4o vision API.
 */
class RecipeScannerService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a RecipeScannerService object.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  /**
   * Gets the OpenAI API key from the OPENAI_API_KEY environment variable.
   *
   * @return string
   *   The API key.
   */
  protected function getApiKey(): string {
    return getenv('OPENAI_API_KEY') ?: '';
  }

  /**
   * Gets the configured OpenAI model.
   *
   * @return string
   *   The model name.
   */
  protected function getModel(): string {
    return $this->configFactory->get('recipe_scanner.settings')->get('openai_model') ?: 'gpt-4o';
  }

  /**
   * Scans a recipe image and returns structured data.
   *
   * @param string $file_path
   *   The absolute path to the image file.
   *
   * @return array
   *   The parsed recipe data.
   *
   * @throws \RuntimeException
   *   If the API call fails or returns invalid data.
   */
  public function scanImage(string $file_path): array {
    return $this->scanImages([$file_path]);
  }

  /**
   * Scans multiple recipe images and returns structured data.
   *
   * Images are sent in the provided order so multi-page recipes are read
   * sequentially and combined into a single result.
   *
   * @param array $file_paths
   *   An ordered array of absolute paths to image files.
   *
   * @return array
   *   The parsed recipe data.
   *
   * @throws \RuntimeException
   *   If the API call fails or returns invalid data.
   */
  public function scanImages(array $file_paths): array {
    $api_key = $this->getApiKey();
    if (empty($api_key)) {
      throw new \RuntimeException('OpenAI API key is not configured. Set the OPENAI_API_KEY environment variable on your server.');
    }

    if (empty($file_paths)) {
      throw new \RuntimeException('No image files provided.');
    }

    $image_count = count($file_paths);
    $multi_page_note = $image_count > 1
      ? "\n- The images are pages of the SAME recipe in order. Combine all information across pages into one unified recipe. Do not duplicate ingredients or instructions that appear on multiple pages."
      : '';

    $prompt = <<<PROMPT
You are a recipe extraction assistant. Analyze {$this->imageCountLabel($image_count)} of a recipe and extract all information into the following JSON structure. Be thorough and accurate.

Return ONLY valid JSON with this exact structure:
{
  "title": "Recipe title",
  "description": "Brief description of the dish",
  "ingredients": [
    {"quantity": 2.25, "unit": "cups", "name": "flour", "note": "sifted"}
  ],
  "instructions": "Full step-by-step instructions as a single text block. Preserve numbered steps if present.",
  "prep_time": 15,
  "cook_time": 30,
  "yield_amount": 12,
  "yield_unit": "servings",
  "notes": "Any additional notes, tips, or variations mentioned",
  "source": "Source attribution if mentioned",
  "tags": ["tag1", "tag2"],
  "category": "Category like Dessert, Main Course, Appetizer, etc."
}

Rules:
- quantity must be a number (convert fractions: "1/2" = 0.5, "1 1/2" = 1.5)
- unit should be the common unit name (cups, tablespoons, teaspoons, ounces, pounds, etc.)
- If a field is not present in the recipe, use null for strings/numbers or empty array for arrays
- prep_time and cook_time are in minutes
- For ingredients with no specific unit (like "3 eggs"), use "" for unit
- Include ALL ingredients, even if they appear in sub-sections
- Preserve the original meaning and quantities exactly as written{$multi_page_note}
PROMPT;

    // Build the content array: text prompt followed by images in order.
    $content = [
      [
        'type' => 'text',
        'text' => $prompt,
      ],
    ];

    foreach ($file_paths as $file_path) {
      $image_data = file_get_contents($file_path);
      if ($image_data === FALSE) {
        throw new \RuntimeException('Could not read image file: ' . $file_path);
      }

      $mime_type = mime_content_type($file_path);
      $base64 = base64_encode($image_data);

      $content[] = [
        'type' => 'image_url',
        'image_url' => [
          'url' => 'data:' . $mime_type . ';base64,' . $base64,
          'detail' => 'high',
        ],
      ];
    }

    try {
      $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
          'Authorization' => 'Bearer ' . $api_key,
          'Content-Type' => 'application/json',
        ],
        'json' => [
          'model' => $this->getModel(),
          'messages' => [
            [
              'role' => 'user',
              'content' => $content,
            ],
          ],
          'response_format' => ['type' => 'json_object'],
          'max_tokens' => 4096,
        ],
        'timeout' => 120,
      ]);
    }
    catch (GuzzleException $e) {
      throw new \RuntimeException('OpenAI API request failed: ' . $e->getMessage());
    }

    $body = json_decode($response->getBody()->getContents(), TRUE);

    if (empty($body['choices'][0]['message']['content'])) {
      throw new \RuntimeException('OpenAI returned an empty response.');
    }

    $data = json_decode($body['choices'][0]['message']['content'], TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException('Failed to parse OpenAI response as JSON: ' . json_last_error_msg());
    }

    // Normalize ingredient quantities — ensure they are floats.
    if (!empty($data['ingredients']) && is_array($data['ingredients'])) {
      foreach ($data['ingredients'] as &$ingredient) {
        if (isset($ingredient['quantity'])) {
          $ingredient['quantity'] = $this->parseQuantity($ingredient['quantity']);
        }
      }
    }

    return $data;
  }

  /**
   * Returns a human-readable label for the number of images.
   */
  protected function imageCountLabel(int $count): string {
    if ($count === 1) {
      return 'this image';
    }
    return "these {$count} images (pages)";
  }

  /**
   * Parses a quantity value that may be a fraction string into a float.
   *
   * @param mixed $value
   *   The quantity value (number, fraction string like "1/2" or "1 1/2").
   *
   * @return float|null
   *   The parsed float value, or NULL if unparseable.
   */
  public function parseQuantity($value): ?float {
    if (is_numeric($value)) {
      return (float) $value;
    }

    if (!is_string($value)) {
      return NULL;
    }

    $value = trim($value);
    if ($value === '') {
      return NULL;
    }

    // Handle "1 1/2" or "1-1/2" style mixed fractions.
    if (preg_match('/^(\d+)\s*[\s\-]\s*(\d+)\s*\/\s*(\d+)$/', $value, $matches)) {
      return (float) $matches[1] + ((float) $matches[2] / (float) $matches[3]);
    }

    // Handle simple fractions "1/2".
    if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $value, $matches)) {
      return (float) $matches[1] / (float) $matches[2];
    }

    return is_numeric($value) ? (float) $value : NULL;
  }

  /**
   * Converts a HEIC/HEIF file to JPEG using ImageMagick.
   *
   * @param string $source_path
   *   The absolute path to the HEIC/HEIF file.
   *
   * @return string|null
   *   The path to the converted JPEG file, or NULL if no conversion was needed.
   *
   * @throws \RuntimeException
   *   If the conversion fails.
   */
  public function convertHeicToJpeg(string $source_path): ?string {
    $mime_type = mime_content_type($source_path);
    if (!in_array($mime_type, ['image/heic', 'image/heif'])) {
      return NULL;
    }

    $jpeg_path = preg_replace('/\.heic$|\.heif$/i', '.jpg', $source_path);
    // Avoid overwriting if the regex didn't change the name.
    if ($jpeg_path === $source_path) {
      $jpeg_path = $source_path . '.jpg';
    }

    $result = 0;
    $command = sprintf('convert %s %s 2>&1', escapeshellarg($source_path), escapeshellarg($jpeg_path));
    exec($command, $output, $result);
    if ($result !== 0 || !file_exists($jpeg_path)) {
      throw new \RuntimeException('Failed to convert HEIC image. Ensure ImageMagick is installed with HEIC support.');
    }

    return $jpeg_path;
  }

}
