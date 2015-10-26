<?php

/**
 * @file
 * Contains \Drupal\fallback_formatter\Plugin\Field\FieldFormatter\FallbackFormatter.
 */

namespace Drupal\fallback_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Fallback formatter.
 *
 * @FieldFormatter(
 *   id = "fallback",
 *   label = @Translation("Fallback"),
 *   weight = 100
 * )
 */
class FallbackFormatter extends FormatterBase {

  /**
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $formatterManager;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->formatterManager = \Drupal::service('plugin.manager.field.formatter');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();
    $settings = $this->getSettings();

    $items_array = array();
    foreach ($items as $item) {
      $items_array[] = $item;
    }

    foreach ($settings['formatters'] as $key => $formatter) {
      if (!$formatter['status']) {
        unset($settings['formatters'][$key]);
      }
    }

    // Merge defaults from the formatters and ensure proper ordering.
    $this->prepareFormatters($this->fieldDefinition->getType(), $settings['formatters']);

    // Loop through each formatter in order.
    foreach ($settings['formatters'] as $name => $options) {

      // Run any unrendered items through the formatter.
      $formatter_items = array_diff_key($items_array, $element);

      $formatter_instance = $this->getFormatter($options);
      $formatter_instance->prepareView(array($items->getEntity()->id() => $items));

      if ($result = $formatter_instance->viewElements($items, $langcode)) {

        // Only add visible content from the formatter's render array result
        // that matches an unseen delta.
        $visible_deltas = Element::getVisibleChildren($result);
        $visible_deltas = array_intersect($visible_deltas, array_keys($formatter_items));
        $element += array_intersect_key($result, array_flip($visible_deltas));

        // If running this formatter completed the output for all items, then
        // there is no need to loop through the rest of the formatters.
        if (count($element) == count($items_array)) {
          break;
        }
      }
    }

    // Ensure the resulting elements are ordered properly by delta.
    ksort($element);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $settings = $this->getSettings();

    $formatters = $settings['formatters'];
    $this->prepareFormatters($this->fieldDefinition->getType(), $formatters, FALSE);

    $rows = [];
    foreach ($formatters as $name => $options) {
      $rows[$name] = [];
      $rows[$name]['#attributes']['class'][] = 'draggable';
      $rows[$name]['#weight'] = $options['weight'];
      $rows[$name]['label'] = array(
        '#markup' => $options['label'],
      );
      $rows[$name]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight for @title', array('@title' => $options['label'])),
        '#title_display' => 'invisible',
        '#delta' => 50,
        '#default_value' => $options['weight'],
        '#attributes' => ['class' => ['fallback-formatter-order-weight']],
      );
      $rows[$name]['status'] = array(
        '#type' => 'select',
        '#title_display' => 'invisible',
        '#options' => array_combine(array_keys($this->getRegions()), array_column($this->getRegions(), 'title')),
        '#default_value' => (int) $options['status'],
      );

      // Filter settings.
      $formatter_instance = $this->getFormatter($options);
      $settings_form = $formatter_instance->settingsForm($form, $form_state);

      if (!empty($settings_form)) {
        $rows[$name]['settings'][] = array(
          '#type' => 'fieldset',
          '#title' => $options['label'],
          '#weight' => $options['weight'],
          '#group' => 'formatter_settings',
          '#states' => [
            'visible' => [
              ':input[name="fields[' . $form_state->getTriggeringElement()['#field_name'] . '][settings_edit_form][settings][formatters][' . $name . '][status]"]' => array('value' => '1'),
            ],
          ],
        );
        $rows[$name]['settings'][$name] = $settings_form;
      }
    }

    $header = [
      'label' => $this->t('Label'),
      'weight' => $this->t('Weight'),
      'status' => $this->t('Status'),
    'settings' => '',
    ];

    $elements['formatters'] = [
      '#type' => 'table',
      '#title' => t('Formatter processing order'),
      '#regions' => $this->getRegions(),
      '#header' => $header,
      '#tree' => TRUE,
      '#empty' => $this->t('There are no fields available.'),
      '#tabledrag' => array(
        array(
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'fallback-formatter-order-weight',
        ),
      ),
      '#theme_wrappers' => array('form_element'),
    ] + $rows;

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $formatters = $this->formatterManager->getDefinitions();

    $this->prepareFormatters($this->fieldDefinition->getType(), $settings['formatters']);

    $summary_items = array();
    foreach ($settings['formatters'] as $name => $options) {
      if ($options['status']) {
        if (!isset($formatters[$name])) {
          $summary_items[] = t('Unknown formatter %name.', array('%name' => $name));
        }
        elseif (!in_array($this->fieldDefinition->getType(), $formatters[$name]['field_types'])) {
          $summary_items[] = t('Invalid formatter %name.', array('%name' => $formatters[$name]['label']));
        }
        else {

          $formatter_instance = $this->getFormatter($options);
          $result = $formatter_instance->settingsSummary();

          $summary_items[] = new FormattableMarkup('<strong>@label</strong><br>@settings_summary', array(
            '@label' => $formatter_instance->getPluginDefinition()['label'],
            '@settings_summary' => !empty($result) ? implode(', ', $result) : '',
          ));
        }
      }
    }

    if (empty($summary_items)) {
      $summary = array(
        '#markup' => t('No formatters selected yet.'),
        '#prefix' => '<strong>',
        '#suffix' => '</strong>',
      );
    }
    else {
      $summary = array(
        '#theme' => 'item_list',
        '#items' => $summary_items,
        '#type' => 'ol',
      );
    }

    return array(drupal_render($summary));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'formatters' => array(),
    );
  }

  /**
   * Gets an instance of a formatter.
   *
   * @param array $options
   *   Formatter options.
   *
   * @return \Drupal\Core\Field\FormatterInterface
   */
  protected function getFormatter($options) {
    if (!isset($options['settings'])) {
      $options['settings'] = array();
    }

    $options += array(
      'field_definition' => $this->fieldDefinition,
      'view_mode' => $this->viewMode,
      'configuration' => array('type' => $options['id'], 'settings' => $options['settings']),
    );

    return $this->formatterManager->getInstance($options);
  }

  /**
   * Decorates formatters definitions to be complete for plugin instantiation.
   *
   * @param string $field_type
   *   The field type for which to prepare the formatters.
   * @param array $formatters
   *   The formatter definitions we want to prepare.
   * @param bool $filter_enabled
   *   If TRUE (default) will filter out any disabled formatters. If FALSE
   *   will return all possible formatters.
   *
   * @todo - this might be merged with getFormatter()?
   */
  protected function prepareFormatters($field_type, array &$formatters, $filter_enabled = TRUE) {
    $default_weight = 0;

    $allowed_formatters = $this->getPossibleFormatters($field_type);
    $formatters += $allowed_formatters;

    $formatters = array_intersect_key($formatters, $allowed_formatters);

    foreach ($formatters as $formatter => $info) {
      $formatters[$formatter] += array('status' => FALSE);

      // Provide some default values.
      $formatters[$formatter] += array('weight' => $default_weight++);
      // Merge in defaults.
      $formatters[$formatter] += $allowed_formatters[$formatter];
      if (!empty($allowed_formatters[$formatter]['settings'])) {
        $formatters[$formatter]['settings'] += $allowed_formatters[$formatter]['settings'];
      }
    }

    // Sort by weight.
    uasort($formatters, array('Drupal\Component\Utility\SortArray', 'sortByWeightElement'));
  }

  /**
   * Gets possible formatters for the given field type.
   *
   * @param string $field_type
   *   Field type for which we want to get the possible formatters.
   *
   * @return array
   *   Formatters info array.
   */
  protected function getPossibleFormatters($field_type) {
    $return = array();

    foreach (\Drupal::service('plugin.manager.field.formatter')->getDefinitions() as $formatter => $info) {
      // The fallback formatter cannot be used as a fallback formatter.
      if ($formatter == 'fallback') {
        continue;
      }
      // Check that the field type is allowed for the formatter.
      elseif (!in_array($field_type, $info['field_types'])) {
        continue;
      }
      elseif (!$info['class']::isApplicable($this->fieldDefinition)) {
        continue;
      }
      else {
        $return[$formatter] = $info;
      }
    }

    return $return;
  }

  /**
   * Get the regions needed to create the settings fallback_formatter form.
   */
  public function getRegions() {
    return array(
      TRUE => array(
        'title' => $this->t('Enabled'),
        'invisible' => TRUE,
        'message' => $this->t('No field is enabled.'),
      ),
      FALSE => array(
        'title' => $this->t('Disabled', array(), array('context' => 'Plural')),
        'message' => $this->t('No field is disabled.'),
      ),
    );
  }

}
