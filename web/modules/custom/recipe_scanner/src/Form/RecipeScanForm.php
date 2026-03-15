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
      '#title' => $this->t('Recipe Photos'),
      '#description' => $this->t('Upload one or more photos of a recipe. For multi-page recipes, upload each page as a separate image — they will be processed in upload order. Supported formats: JPG, PNG, WebP, HEIC. Max 4 MB per file.'),
      '#upload_location' => 'public://recipe-scanner-uploads',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'jpg jpeg png webp heic heif'],
        'FileSizeLimit' => ['fileLimit' => 4 * 1024 * 1024],
      ],
      '#multiple' => TRUE,
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

    // Show uploaded image previews.
    $fids = $form_state->get('uploaded_fids') ?: [];
    if ($fids) {
      $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);
      if ($files) {
        $form['preview'] = [
          '#type' => 'container',
          '#attributes' => ['style' => 'display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 1em;'],
        ];
        foreach (array_values($files) as $i => $file) {
          $form['preview'][$i] = [
            '#theme' => 'image',
            '#uri' => $file->getFileUri(),
            '#alt' => $this->t('Recipe photo page @num', ['@num' => $i + 1]),
            '#attributes' => ['style' => 'max-width: 300px; height: auto;'],
          ];
        }
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

    $form['net_carbs'] = [
      '#type' => 'number',
      '#title' => $this->t('Net carbs per serving'),
      '#default_value' => $data['net_carbs_per_serving'] ?? NULL,
      '#min' => 0,
      '#description' => $this->t('Total carbohydrates minus fiber, per serving.'),
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

    // Tags — entity autocomplete with auto-create, tags style.
    $tag_default = [];
    if (!empty($data['tags'])) {
      foreach ($data['tags'] as $tag_name) {
        $tag_name = trim($tag_name);
        if (empty($tag_name)) {
          continue;
        }
        $existing = $this->entityTypeManager->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $tag_name, 'vid' => 'recipe_tags']);
        if ($existing) {
          $tag_default[] = reset($existing);
        }
        else {
          // Create a temporary unsaved entity for the autocomplete default.
          $tag_default[] = $this->entityTypeManager->getStorage('taxonomy_term')
            ->create(['name' => $tag_name, 'vid' => 'recipe_tags']);
        }
      }
    }

    $form['tags'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Tags'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['recipe_tags' => 'recipe_tags'],
        'auto_create' => TRUE,
        'auto_create_bundle' => 'recipe_tags',
      ],
      '#autocreate' => [
        'bundle' => 'recipe_tags',
      ],
      '#tags' => TRUE,
      '#default_value' => $tag_default,
    ];

    // Category — select dropdown from existing terms.
    $category_terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'recipe_category']);
    $category_options = ['' => $this->t('- Select -')];
    foreach ($category_terms as $term) {
      $category_options[$term->id()] = $term->label();
    }
    asort($category_options);

    // Try to match the scanned category to an existing term.
    $category_default = '';
    if (!empty($data['category'])) {
      foreach ($category_terms as $term) {
        if (strcasecmp($term->label(), $data['category']) === 0) {
          $category_default = $term->id();
          break;
        }
      }
    }

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => $category_options,
      '#default_value' => $category_default,
      '#required' => TRUE,
    ];

    // If the scanned category didn't match, show a textfield to create one.
    $form['category_new'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Or create new category'),
      '#default_value' => (!empty($data['category']) && empty($category_default)) ? $data['category'] : '',
      '#description' => $this->t('If the category above doesn\'t have the right option, type a new one here.'),
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
      $this->messenger()->addError($this->t('Please upload at least one photo.'));
      return;
    }

    // Ensure $fids is an array (single upload returns a flat array with one element).
    $fids = array_values(array_filter((array) $fids));

    $file_storage = $this->entityTypeManager->getStorage('file');
    $file_system = \Drupal::service('file_system');
    $file_paths = [];
    $saved_fids = [];

    foreach ($fids as $fid) {
      $file = $file_storage->load($fid);
      if (!$file) {
        $this->messenger()->addError($this->t('Could not load an uploaded file.'));
        return;
      }

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
        $new_uri = preg_replace('/\.heic$|\.heif$/i', '.jpg', $file->getFileUri());
        $file_system->move($converted_path, $new_uri);
        $file_system->delete($file_path);
        $file->setFileUri($new_uri);
        $file->setMimeType('image/jpeg');
        $file->setFilename(basename($new_uri));
        $file_path = $file_system->realpath($new_uri);
      }

      $file->setPermanent();
      $file->save();

      $file_paths[] = $file_path;
      $saved_fids[] = (int) $file->id();
    }

    try {
      $data = $this->scanner->scanImages($file_paths);
    }
    catch (\RuntimeException $e) {
      $this->messenger()->addError($e->getMessage());
      return;
    }

    $form_state->set('scanned_data', $data);
    $form_state->set('uploaded_fids', $saved_fids);
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

    // Category — prefer the new category textfield if filled in.
    $category_new = trim($values['category_new'] ?? '');
    if (!empty($category_new)) {
      $category_tid = $this->findOrCreateTerm($category_new, 'recipe_category');
    }
    else {
      $category_tid = $values['category'];
    }

    // Tags — entity_autocomplete with #tags returns an array of entries.
    // Existing terms have integer target_id; auto-created terms have an
    // unsaved entity object in 'entity' that we need to save first.
    $tag_tids = [];
    if (!empty($values['tags'])) {
      foreach ($values['tags'] as $tag_entry) {
        if (!empty($tag_entry['entity']) && $tag_entry['entity'] instanceof \Drupal\Core\Entity\EntityInterface) {
          $tag_entry['entity']->save();
          $tag_tids[] = ['target_id' => $tag_entry['entity']->id()];
        }
        elseif (!empty($tag_entry['target_id'])) {
          $tag_tids[] = ['target_id' => $tag_entry['target_id']];
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
      'field_net_carbs' => $values['net_carbs'] ?? NULL,
      'field_category' => ['target_id' => $category_tid],
      'field_recipe_tags' => $tag_tids,
      'status' => 1,
    ]);

    // Attach the source photos (preserving upload order).
    $fids = $form_state->get('uploaded_fids') ?: [];
    if ($fids) {
      $photo_values = [];
      foreach ($fids as $fid) {
        $photo_values[] = ['target_id' => $fid];
      }
      $node->set('field_recipe_source_photo', $photo_values);
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
