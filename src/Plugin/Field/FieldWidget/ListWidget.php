<?php
/**
 * @file
 * Contains \Drupal\multiple_selects\Plugin\Field\FieldWidget\ListWidget.
 */

namespace Drupal\multiple_selects\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'multiple_selects_list' widget.
 *
 * @FieldWidget(
 *   id = "multiple_selects_list",
 *   label = @Translation("Multiple Selects"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string"
 *   }
 * )
 */
class ListWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $field = $items->getFieldDefinition();
    $settings = $field->getSettings();
    $element['value'] = $element + array(
      '#type' => 'select',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#options' => $settings['allowed_values'],
      '#empty_option' => $this->t('- None -'),
    );

    return $element;
  }

}
