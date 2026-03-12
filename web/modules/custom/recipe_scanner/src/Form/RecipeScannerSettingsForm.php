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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('recipe_scanner.settings')
      ->set('openai_model', $form_state->getValue('openai_model'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
