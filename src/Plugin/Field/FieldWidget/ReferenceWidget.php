<?php
/**
 * @file
 * Contains \Drupal\multiple_selects\Plugin\Field\FieldWidget\ReferenceWidget.
 */

namespace Drupal\multiple_selects\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Plugin implementation of the 'multiple_selects_reference' widget.
 *
 * @FieldWidget(
 *   id = "multiple_selects_reference",
 *   label = @Translation("Multiple Selects"),
 *   field_types = {
 *     "entity_reference"
 *   },
 * )
 */
class ReferenceWidget extends OptionsSelectWidget {

  /**
   * Helper method which prepares element values for validation.
   *
   * EntityAutocomplete::validateEntityAutocomplete expects a string when
   * validating taxonomy terms.
   *
   * @param array $element
   *   The element to prepare.
   */
  protected static function prepareElementValuesForValidation(array &$element) {
    if ($element['#tags']) {
      $element['#value'] = Tags::implode($element['#value']);
    }
  }

  /**
   * Retrieves the entityStorage object.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   EntityStorage for entity types that can be referenced by this widget.
   *
   * @codeCoverageIgnore
   *   Ignore this method because if ::getFieldSetting() or entityTypeManager
   *   does not return the expected result, we have other problems on our hands.
   */
  protected function getEntityStorage() {
    $target_type = $this->getFieldSetting('target_type');
    return \Drupal::entityTypeManager()->getStorage($target_type);
  }

  /**
   * Retrieves the key used to indicate a bundle for the entity type.
   *
   * @return string
   *   The key used to indicate a bundle for the entity type referenced by this
   *   widget's field.
   *
   * @codeCoverageIgnore
   *   Ignore this method because if any of the called core functions does not
   *   return the expected result, we've got other problems on our hands.
   */
  protected function getBundleKey() {
    $entity_keys = $this->getEntityStorage()
      ->getEntityType()
      ->get('entity_keys');
    return $entity_keys['bundle'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity = NULL) {
    $options = [];

    // Prepare properties to use for loading.
    $entity_storage = $this->getEntityStorage();
    $bundle_key = $this->getBundleKey();
    $target_bundles = $this->getSelectionHandlerSetting('target_bundles');
    $properties = [$bundle_key => $target_bundles];

    $entities = $entity_storage->loadByProperties($properties);

    // Prepare the options.
    foreach ($entities as $entity) {
      $options["{$entity->label()} ({$entity->id()})"] = $entity->label();
    }

    // Add an empty option if the widget needs one.
    if ($empty_label = $this->getEmptyLabel()) {
      $options = ['_none' => $empty_label] + $options;
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSelectedOptions(array $options) {
    $prepared_options = [];
    $entities = $this->getEntityStorage()->loadMultiple($options);

    foreach ($entities as $entity) {
      $prepared_options[] = "{$entity->label()} ({$entity->id()})";
    }

    return $prepared_options;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $entity = $items->getEntity();

    $element = $element + array(
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#target_type' => $this->getFieldSetting('target_type'),
      '#selection_handler' => $this->getFieldSetting('handler'),
      '#selection_settings' => $this->getFieldSetting('handler_settings'),
      '#validate_reference' => TRUE,
      '#tags' => $this->getFieldSetting('target_type') === 'taxonomy_term',
      '#merged_values' => TRUE,
    );

    $element['#element_validate'] = [
      [
        get_class($this),
        'validateReferenceWidget'
      ]
    ];

    return $element;
  }

  /**
   * Returns the value of a setting for the entity reference selection handler.
   *
   * @todo This is shamelessly copied from EntityAutocomplete. We should
   * probably file a core issue to turn this into a trait. The same goes for
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   *
   * @codeCoverageIgnore
   *   Ignore this function because it is a straight copy->paste.
   */
  protected function getSelectionHandlerSetting($setting_name) {
    $settings = $this->getFieldSetting('handler_settings');
    return isset($settings[$setting_name]) ? $settings[$setting_name] : NULL;
  }

  /**
   * Form element validation handler for multiple_selects_reference elements.
   *
   * @codeCoverageIgnore
   *   Ignore
   */
  public static function validateReferenceWidget(array &$element, FormStateInterface $form_state, array &$complete_form) {
    self::prepareElementValuesForValidation($element);
    if (!empty($element['#value'])) {
      EntityAutocomplete::validateEntityAutocomplete($element, $form_state, $complete_form);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    return $this->t('- None -');
  }

  /**
   * Return the Multiple Selects field widget option only if the Auto Create
   * Entity option is disabled.
   *
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $options = $field_definition->getSettings();
    $handler_settings = isset($options['handler_settings']) ? $options['handler_settings'] : NULL;
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')
      ->getInstance($options);
    if ($handler instanceof SelectionWithAutocreateInterface
      && isset($handler_settings['auto_create'])
      && $handler_settings['auto_create']) {
        return FALSE;
    }
    return TRUE;
  }


}
