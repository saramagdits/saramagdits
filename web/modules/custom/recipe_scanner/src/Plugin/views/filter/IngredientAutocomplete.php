<?php

namespace Drupal\recipe_scanner\Plugin\views\filter;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter recipes by ingredient entity with autocomplete.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("ingredient_autocomplete")]
class IngredientAutocomplete extends ManyToOne {

  /**
   * Stores the exposed input for this filter.
   *
   * @var array|null
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $validated_exposed_input = NULL;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['error_message'] = ['default' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('ingredient');
    $ingredients = $this->value ? $storage->loadMultiple($this->value) : [];

    $form['value'] = [
      '#title' => $this->t('Select ingredients'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'ingredient',
      '#tags' => TRUE,
      '#default_value' => $ingredients,
      '#process_default_value' => FALSE,
    ];

    if ($form_state->get('exposed')) {
      $identifier = $this->options['expose']['identifier'];
      $form['value']['#default_value'] = NULL;

      if (!empty($this->value)) {
        $loaded = $storage->loadMultiple($this->value);
        if ($loaded) {
          $form['value']['#default_value'] = EntityAutocomplete::getEntityLabels(array_values($loaded));
          $form['value']['#type'] = 'textfield';
        }
      }
    }

    if (!$form_state->get('exposed')) {
      $this->helper->buildOptionsForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function valueValidate($form, FormStateInterface $form_state) {
    $ids = [];
    if ($values = $form_state->getValue(['options', 'value'])) {
      foreach ($values as $value) {
        $ids[] = $value['target_id'];
      }
    }
    $form_state->setValue(['options', 'value'], $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    $identifier = $this->options['expose']['identifier'];
    $input = $form_state->getValue($identifier);

    if ($this->options['is_grouped'] && isset($this->options['group_info']['group_items'][$input])) {
      $this->validated_exposed_input = $this->options['group_info']['group_items'][$input]['value'];
      return;
    }

    if (empty($input)) {
      return;
    }

    // The entity_autocomplete element returns structured array values.
    if (is_array($input)) {
      foreach ($input as $value) {
        if (isset($value['target_id'])) {
          $this->validated_exposed_input[] = $value['target_id'];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // Set the operator if exposed.
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id']) && isset($input[$this->options['expose']['operator_id']])) {
      $this->operator = $input[$this->options['expose']['operator_id']];
    }

    // If view is an attachment and is inheriting exposed filters, then assume
    // exposed input has already been validated.
    if (!empty($this->view->is_attachment) && $this->view->display_handler->usesExposed()) {
      $this->validated_exposed_input = (array) $this->view->exposed_raw_input[$this->options['expose']['identifier']];
    }

    // If we're checking for EMPTY or NOT, we don't need any input.
    if ($this->operator == 'empty' || $this->operator == 'not empty') {
      return TRUE;
    }

    // If it's non-required and there's no value don't bother filtering.
    if (!$this->options['expose']['required'] && empty($this->validated_exposed_input)) {
      return FALSE;
    }

    $rc = parent::acceptExposedInput($input);
    if ($rc) {
      if (isset($this->validated_exposed_input)) {
        $this->value = $this->validated_exposed_input;
      }
    }

    return $rc;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state) {
    // Prevent array_filter from messing up our arrays in parent submit.
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);
    $form['error_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display error message'),
      '#default_value' => !empty($this->options['error_message']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $this->valueOptions = [];

    if ($this->value) {
      $this->value = array_filter($this->value);
      $storage = $this->entityTypeManager->getStorage('ingredient');
      $ingredients = $storage->loadMultiple($this->value);
      foreach ($ingredients as $ingredient) {
        $this->valueOptions[$ingredient->id()] = $ingredient->label();
      }
    }
    return parent::adminSummary();
  }

}
