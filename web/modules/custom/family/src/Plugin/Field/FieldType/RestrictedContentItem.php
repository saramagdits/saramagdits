<?php declare(strict_types = 1);

namespace Drupal\family\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'family_restricted_content' field type.
 *
 * @FieldType(
 *   id = "family_restricted_content",
 *   label = @Translation("Restricted Content"),
 *   category = @Translation("General"),
 *   default_widget = "boolean_checkbox",
 *   default_formatter = "boolean",
 * )
 */
final class RestrictedContentItem extends BooleanItem {

}
