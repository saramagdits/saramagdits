<?php

namespace Drupal\gallery_grid\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin for gallery grid layout.
 *
 * @ViewsStyle(
 *   id = "gallery_grid",
 *   title = @Translation("Gallery Grid"),
 *   help = @Translation("Displays content in a responsive grid layout."),
 *   theme = "views_view_gallery_grid",
 *   display_types = {"normal"}
 * )
 */
class GalleryGrid extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesFields = FALSE;

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    foreach ($this->view->result as $row_index => $row) {
      $rows[] = [
        'content' => $this->view->rowPlugin->render($row),
        'attributes' => [],
      ];
    }
    $build = [
      '#theme' => 'views_view_gallery_grid',
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
      '#title' => $this->view->getTitle(),
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowClass = TRUE;

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['columns'] = ['default' => 3];
    $options['gap'] = ['default' => '1rem'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['columns'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of columns'),
      '#default_value' => $this->options['columns'],
      '#min' => 1,
      '#max' => 12,
      '#description' => $this->t('Number of columns in the grid.'),
    ];

    $form['gap'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Grid gap'),
      '#default_value' => $this->options['gap'],
      '#description' => $this->t('Space between grid items (e.g., 1rem, 20px).'),
    ];
  }

} 