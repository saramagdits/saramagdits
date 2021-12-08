<?php

/**
 * @file
 * Theme settings form for indie theme.
 */

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function indie_form_system_theme_settings_alter(&$form, &$form_state) {

  $form['indie'] = [
    '#type' => 'details',
    '#title' => t('indie'),
    '#open' => TRUE,
  ];

  $form['indie']['font_size'] = [
    '#type' => 'number',
    '#title' => t('Font size'),
    '#min' => 12,
    '#max' => 18,
    '#default_value' => theme_get_setting('font_size'),
  ];

}
