<?php

namespace Drupal\recipe_scanner\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ingredient\Utility\IngredientUnitFuzzymatch;
use Drupal\ingredient\Utility\IngredientUnitUtility;
use Drupal\recipe_scanner\RecipeScannerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Multi-step form for scanning recipe photos.
 */
class RecipeScanForm extends FormBase {

  /**
   * The recipe scanner service.
   *
   * @var \Drupal\recipe_scanner\RecipeScannerService
   */
  protected $scanner;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The ingredient unit fuzzymatch service.
   *
   * @var \Drupal\ingredient\Utility\IngredientUnitFuzzymatch
   */
  protected $unitFuzzymatch;

  /**
   * The ingredient unit utility service.
   *
   * @var \Drupal\ingredient\Utility\IngredientUnitUtility
   */
  protected $unitUtility;

  /**
   * Constructs a RecipeScanForm object.
   */
  public function __construct(
    RecipeScannerService $scanner,
    EntityTypeManagerInterface $entity_type_manager,
    IngredientUnitFuzzymatch $unit_fuzzymatch,
    IngredientUnitUtility $unit_utility
  ) {
    $this->scanner = $scanner;
    $this->entityTypeManager = $entity_type_manager;
    $this->unitFuzzymatch = $unit_fuzzymatch;
    $this->unitUtility = $unit_utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recipe_scanner.scanner'),
      $container->get('entity_type.manager'),
      $container->get('ingredient.fuzzymatch'),
      $container->get('ingredient.unit')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipe_scan_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $step = $form_state->get('step') ?: 1;
    $form_state->set('step', $step);

    $form['#tree'] = TRUE;

    if ($step === 1) {
      $this->buildUploadStep($form, $form_state);
    }
    elseif ($step === 2) {
      $this->buildReviewStep($form, $form_state);
    }

    return $form;
  }

  /**
   * Builds the upload step of the form.
   */
  protected function buildUploadStep(array &$form, FormStateInterface $form_state) {
    $form['photo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Recipe Photo'),
      '#description' => $this->t('Upload a photo of a handwritten or printed recipe. Supported formats: JPG, PNG, WebP, HEIC. Max 4 MB.'),
      '#upload_location' => 'public://recipe-scanner-uploads',
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png webp heic heif'],
        'file_validate_size' => [4 * 1024 * 1024],
      ],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['scan'] = [
      '#type' => 'submit',
      '#value' => $this->t('Scan Recipe'),
      '#submit' => ['::scanSubmit'],
    ];
  }

  /**
   * Builds the review/edit step of the form.
   */
  protected function buildReviewStep(array &$form, FormStateInterface $form_state) {
    $data = $form_state->get('scanned_data');

    // Show uploaded image preview.
    $fid = $form_state->get('uploaded_fid');
    if ($fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($file) {
        $form['preview'] = [
          '#theme' => 'image',
          '#uri' => $file->getFileUri(),
          '#alt' => $this->t('Uploaded recipe photo'),
          '#attributes' => ['style' => 'max-width: 400px; height: auto;'],
        ];
      }
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $data['title'] ?? '',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $data['description'] ?? '',
      '#rows' => 3,
    ];

    $form['instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
      '#default_value' => $data['instructions'] ?? '',
      '#rows' => 10,
    ];

    $form['prep_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Prep time (minutes)'),
      '#default_value' => $data['prep_time'] ?? NULL,
      '#min' => 0,
    ];

    $form['cook_time'] = [
      '#type' => 'number',
      '#title' => $this->t('Cook time (minutes)'),
      '#default_value' => $data['cook_time'] ?? NULL,
      '#min' => 0,
    ];

    $form['yield_amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Yield amount'),
      '#default_value' => $data['yield_amount'] ?? NULL,
      '#min' => 0,
    ];

    $form['yield_unit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Yield unit'),
      '#default_value' => $data['yield_unit'] ?? '',
      '#size' => 20,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#default_value' => $data['notes'] ?? '',
      '#rows' => 3,
    ];

    $form['source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source'),
      '#default_value' => $data['source'] ?? '',
    ];

    $form['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#default_value' => !empty($data['tags']) ? implode(', ', $data['tags']) : '',
      '#description' => $this->t('Comma-separated list of tags.'),
    ];

    $form['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category'),
      '#default_value' => $data['category'] ?? '',
      '#required' => TRUE,
    ];

    // Build ingredients table.
    $this->buildIngredientsTable($form, $form_state, $data['ingredients'] ?? []);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::backSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Recipe'),
      '#submit' => ['::saveSubmit'],
    ];
  }

  /**
   * Builds the ingredients table with AJAX support.
   */
  protected function buildIngredientsTable(array &$form, FormStateInterface $form_state, array $scanned_ingredients) {
    // Get unit options.
    $units = $this->unitUtility->getConfiguredUnits();
    $units = $this->unitUtility->sortUnitsByName($units);
    $unit_options = $this->unitUtility->createUnitSelectOptions($units);

    // Determine ingredient rows — use form_state if available (for AJAX),
    // otherwise use scanned data.
    $ingredients = $form_state->get('ingredients');
    if ($ingredients === NULL) {
      $ingredients = [];
      foreach ($scanned_ingredients as $ing) {
        $unit_key = '';
        if (!empty($ing['unit'])) {
          $matched = $this->unitFuzzymatch->getUnitFuzzymatch($ing['unit']);
          $unit_key = $matched !== FALSE ? $matched : 'unit';
        }
        $ingredients[] = [
          'quantity' => $ing['quantity'] ?? '',
          'unit_key' => $unit_key,
          'name' => $ing['name'] ?? '',
          'note' => $ing['note'] ?? '',
        ];
      }
      // Ensure at least one empty row.
      if (empty($ingredients)) {
        $ingredients[] = ['quantity' => '', 'unit_key' => '', 'name' => '', 'note' => ''];
      }
      $form_state->set('ingredients', $ingredients);
    }

    $form['ingredients'] = [
      '#type' => 'container',
      '#prefix' => '<div id="ingredients-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['ingredients']['heading'] = [
      '#markup' => '<h3>' . $this->t('Ingredients') . '</h3>',
    ];

    $form['ingredients']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Quantity'),
        $this->t('Unit'),
        $this->t('Ingredient'),
        $this->t('Note'),
      ],
    ];

    foreach ($ingredients as $i => $ing) {
      $form['ingredients']['table'][$i]['quantity'] = [
        '#type' => 'number',
        '#default_value' => $ing['quantity'],
        '#step' => 'any',
        '#min' => 0,
        '#size' => 8,
      ];

      $form['ingredients']['table'][$i]['unit_key'] = [
        '#type' => 'select',
        '#default_value' => $ing['unit_key'],
        '#options' => $unit_options,
      ];

      $form['ingredients']['table'][$i]['name'] = [
        '#type' => 'textfield',
        '#default_value' => $ing['name'],
        '#size' => 30,
      ];

      $form['ingredients']['table'][$i]['note'] = [
        '#type' => 'textfield',
        '#default_value' => $ing['note'],
        '#size' => 20,
      ];
    }

    $form['ingredients']['add_more'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another ingredient'),
      '#submit' => ['::addIngredientSubmit'],
      '#ajax' => [
        'callback' => '::ingredientsAjaxCallback',
        'wrapper' => 'ingredients-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * AJAX callback for ingredients table.
   */
  public function ingredientsAjaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['ingredients'];
  }

  /**
   * Submit handler to add another ingredient row.
   */
  public function addIngredientSubmit(array &$form, FormStateInterface $form_state) {
    // Capture current ingredient values from user input.
    $input = $form_state->getUserInput();
    $ingredients = [];
    if (!empty($input['ingredients']['table'])) {
      foreach ($input['ingredients']['table'] as $row) {
        $ingredients[] = [
          'quantity' => $row['quantity'] ?? '',
          'unit_key' => $row['unit_key'] ?? '',
          'name' => $row['name'] ?? '',
          'note' => $row['note'] ?? '',
        ];
      }
    }

    // Add empty row.
    $ingredients[] = ['quantity' => '', 'unit_key' => '', 'name' => '', 'note' => ''];
    $form_state->set('ingredients', $ingredients);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the "Scan Recipe" button.
   */
  public function scanSubmit(array &$form, FormStateInterface $form_state) {
    $fids = $form_state->getValue('photo');
    if (empty($fids)) {
      $this->messenger()->addError($this->t('Please upload a photo.'));
      return;
    }

    $fid = reset($fids);
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      $this->messenger()->addError($this->t('Could not load the uploaded file.'));
      return;
    }

    $file_system = \Drupal::service('file_system');
    $file_path = $file_system->realpath($file->getFileUri());

    // Convert HEIC/HEIF to JPEG so both the API and the stored file use JPEG.
    try {
      $converted_path = $this->scanner->convertHeicToJpeg($file_path);
    }
    catch (\RuntimeException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }

    if ($converted_path) {
      // Replace the managed file with the converted JPEG.
      $new_uri = preg_replace('/\.heic$|\.heif$/i', '.jpg', $file->getFileUri());
      $file_system->move($converted_path, $new_uri);
      // Delete the original HEIC.
      $file_system->delete($file_path);
      $file->setFileUri($new_uri);
      $file->setMimeType('image/jpeg');
      $file->setFilename(basename($new_uri));
      $file_path = $file_system->realpath($new_uri);
    }

    // Make the file permanent so it persists.
    $file->setPermanent();
    $file->save();

    try {
      $data = $this->scanner->scanImage($file_path);
    }
    catch (\RuntimeException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }

    $form_state->set('scanned_data', $data);
    $form_state->set('uploaded_fid', $file->id());
    $form_state->set('step', 2);
    $form_state->set('ingredients', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the "Back" button.
   */
  public function backSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 1);
    $form_state->set('ingredients', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the "Save Recipe" button.
   */
  public function saveSubmit(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Build ingredient field values.
    $ingredient_values = [];
    if (!empty($values['ingredients']['table'])) {
      foreach ($values['ingredients']['table'] as $row) {
        $name = trim($row['name'] ?? '');
        if (empty($name)) {
          continue;
        }

        // Look up or create ingredient entity.
        $ingredient = $this->findOrCreateIngredient($name);

        $ingredient_values[] = [
          'target_id' => $ingredient->id(),
          'quantity' => !empty($row['quantity']) ? (float) $row['quantity'] : NULL,
          'unit_key' => $row['unit_key'] ?: 'unit',
          'note' => trim($row['note'] ?? ''),
        ];
      }
    }

    // Look up or create taxonomy terms.
    $category_tid = $this->findOrCreateTerm($values['category'], 'recipe_category');
    $tag_tids = [];
    if (!empty($values['tags'])) {
      $tag_names = array_map('trim', explode(',', $values['tags']));
      foreach ($tag_names as $tag_name) {
        if (!empty($tag_name)) {
          $tag_tids[] = ['target_id' => $this->findOrCreateTerm($tag_name, 'recipe_tags')];
        }
      }
    }

    // Create recipe node.
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->create([
      'type' => 'recipe',
      'title' => $values['title'],
      'recipe_description' => [
        'value' => $values['description'] ?? '',
        'format' => 'plain_text',
      ],
      'recipe_instructions' => [
        'value' => $values['instructions'] ?? '',
        'format' => 'plain_text',
      ],
      'recipe_prep_time' => $values['prep_time'] ?? NULL,
      'recipe_cook_time' => $values['cook_time'] ?? NULL,
      'recipe_yield_amount' => $values['yield_amount'] ?? NULL,
      'recipe_yield_unit' => $values['yield_unit'] ?? '',
      'recipe_notes' => [
        'value' => $values['notes'] ?? '',
        'format' => 'plain_text',
      ],
      'recipe_source' => [
        'value' => $values['source'] ?? '',
        'format' => 'plain_text',
      ],
      'recipe_ingredient' => $ingredient_values,
      'field_category' => ['target_id' => $category_tid],
      'field_recipe_tags' => $tag_tids,
      'status' => 1,
    ]);

    // Attach the source photo.
    $fid = $form_state->get('uploaded_fid');
    if ($fid) {
      $node->set('field_recipe_source_photo', ['target_id' => $fid]);
    }

    $node->save();

    $this->messenger()->addStatus($this->t('Recipe "%title" has been created.', [
      '%title' => $node->getTitle(),
    ]));

    $form_state->setRedirectUrl(Url::fromRoute('entity.node.canonical', ['node' => $node->id()]));
  }

  /**
   * Finds an existing ingredient entity by name or creates a new one.
   *
   * @param string $name
   *   The ingredient name.
   *
   * @return \Drupal\ingredient\Entity\Ingredient
   *   The ingredient entity.
   */
  protected function findOrCreateIngredient(string $name) {
    $storage = $this->entityTypeManager->getStorage('ingredient');

    // Ingredient module normalizes names to lowercase on presave.
    $normalized_name = mb_strtolower($name);

    $existing = $storage->loadByProperties(['name' => $normalized_name]);
    if (!empty($existing)) {
      return reset($existing);
    }

    $ingredient = $storage->create(['name' => $name]);
    $ingredient->save();
    return $ingredient;
  }

  /**
   * Finds or creates a taxonomy term in the given vocabulary.
   *
   * @param string $name
   *   The term name.
   * @param string $vid
   *   The vocabulary ID.
   *
   * @return int
   *   The term ID.
   */
  protected function findOrCreateTerm(string $name, string $vid): int {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $existing = $term_storage->loadByProperties([
      'name' => $name,
      'vid' => $vid,
    ]);

    if (!empty($existing)) {
      return reset($existing)->id();
    }

    $term = $term_storage->create([
      'name' => $name,
      'vid' => $vid,
    ]);
    $term->save();
    return $term->id();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handled by individual submit handlers.
  }

}
