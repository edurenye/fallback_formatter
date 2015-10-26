<?php

/**
 * @file
 * Contains \Drupal\fallback_formatter\Tests\FallbackFormatterTestBase.
 */

namespace Drupal\fallback_formatter\Tests;

use Drupal\Core\Render\Element;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;

/**
 * Base class for the fallback formatter tests.
 */
abstract class FallbackFormatterTestBase extends WebTestBase {

  public static $modules = ['user', 'field', 'node', 'text', 'fallback_formatter', 'fallback_formatter_test'];

  /**
   * Node with a field with test formatters.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $node_type_id = 'node_test';
    $node_type = NodeType::create([
      'type' => $node_type_id,
      'name' => $node_type_id,
    ]);
    $node_type->save();

    FieldStorageConfig::create([
      'field_name' => 'test_text',
      'entity_type' => 'node',
      'type' => 'text',
    ])->save();

    FieldConfig::create([
      'field_name' => 'test_text',
      'entity_type' => 'node',
      'label' => 'Test',
      'bundle' => $node_type_id,
    ])->save();

    entity_get_form_display('node', $node_type_id, 'default')
      ->setComponent('test_text', array(
        'type' => 'string_textfield',
      ))
      ->save();

    entity_get_display('node', $node_type_id, 'default')
      ->setComponent('test_text')
      ->save();

    $this->node = Node::create([
      'type' => $node_type_id,
      'title' => $this->randomMachineName(),
      'test_text' => array(
        array(
          'value' => 'Apple',
          'format' => NULL,
        ),
        array(
          'value' => 'Banana',
        ),
        array(
          'value' => 'Carrot',
        ),
      ),
    ]);
    $this->node->save();
  }

}
