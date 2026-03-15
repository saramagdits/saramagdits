<?php

namespace Drupal\recipe_scanner\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for the Recipe Scanner module.
 */
class RecipeScannerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recipe_scanner.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipe_scanner_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('recipe_scanner.settings');

    $has_key = !empty(getenv('OPENAI_API_KEY'));
    $form['openai_api_key_status'] = [
      '#type' => 'item',
      '#title' => $this->t('OpenAI API Key'),
      '#markup' => $has_key
        ? $this->t('Set via <code>OPENAI_API_KEY</code> environment variable.')
        : $this->t('<strong>Not set.</strong> Set the <code>OPENAI_API_KEY</code> environment variable on your server.'),
    ];

    $form['openai_model'] = [
      '#type' => 'select',
      '#title' => $this->t('OpenAI Model'),
      '#default_value' => $config->get('openai_model') ?: 'gpt-4o',
      '#options' => [
        'gpt-4o' => 'GPT-4o',
        'gpt-4o-mini' => 'GPT-4o Mini',
        'gpt-4.1' => 'GPT-4.1',
        'gpt-4.1-mini' => 'GPT-4.1 Mini',
      ],
      '#description' => $this->t('Select the OpenAI model to use for recipe scanning. GPT-4o is recommended for best accuracy.'),
    ];

    $default_prompt = <<<'PROMPT'
You are a recipe extraction assistant. Analyze @image_count of a recipe and extract all information into the following JSON structure. Be thorough and accurate.

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
  "category": "Category like Dessert, Main Course, Appetizer, etc.",
  "net_carbs_per_serving": 12
}

Rules:
- quantity must be a number (convert fractions: "1/2" = 0.5, "1 1/2" = 1.5)
- unit should be the common unit name (cups, tablespoons, teaspoons, ounces, pounds, etc.)
- If a field is not present in the recipe, use null for strings/numbers or empty array for arrays
- prep_time and cook_time are in minutes
- For ingredients with no specific unit (like "3 eggs"), use "" for unit
- Include ALL ingredients, even if they appear in sub-sections
- Preserve the original meaning and quantities exactly as written
- net_carbs_per_serving is the net carbs per serving (total carbohydrates minus fiber). Calculate this ONLY if the recipe provides enough nutritional information (total carbs and fiber per serving, or enough detail to estimate). If the information is not available, use null.
- @multi_page_note
PROMPT;

    $form['scan_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Scan Prompt'),
      '#default_value' => $config->get('scan_prompt') ?: $default_prompt,
      '#rows' => 20,
      '#description' => $this->t('The prompt sent to the AI model when scanning recipe photos. Available tokens: <code>@image_count</code> (replaced with "this image" or "these N images (pages)"), <code>@multi_page_note</code> (replaced with multi-page instructions when multiple images are uploaded, or removed for single images).'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('recipe_scanner.settings')
      ->set('openai_model', $form_state->getValue('openai_model'))
      ->set('scan_prompt', $form_state->getValue('scan_prompt'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
