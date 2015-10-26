<?php

/**
 * @file
 * Contains \Drupal\fallback_formatter\Tests\FallbackFormatterTestCase.
 */

namespace Drupal\fallback_formatter\Tests;

use Drupal\Core\Render\Element;

/**
 * Test basic functionality of the fallback formatter.
 *
 * @group fallback_formatter
 */
class FallbackFormatterTestCase extends FallbackFormatterTestBase {

  public static $modules = ['user', 'field', 'node', 'text', 'fallback_formatter', 'fallback_formatter_test'];

  public function test() {
    $formatters = array(
      'fallback_test_a' => array(
        'settings' => array(),
        'status' => 1,
      ),
      'fallback_test_b' => array(
        'settings' => array(),
        'status' => 1,
      ),
      'fallback_test_default' => array(
        'settings' => array('prefix' => 'DEFAULT: '),
        'status' => 1,
      ),
    );
    $expected = array(
      0 => array('#markup' => 'A: Apple'),
      1 => array('#markup' => 'B: Banana'),
      2 => array('#markup' => 'DEFAULT: Carrot'),
    );
    $this->assertFallbackFormatter($this->node, $formatters, $expected);

    $formatters = array(
      'fallback_test_a' => array(
        'status' => 1,
      ),
      'fallback_test_b' => array(
        'status' => 1,
      ),
      'fallback_test_default' => array(
        'settings' => array('prefix' => 'DEFAULT: '),
        'status' => 1,
        'weight' => -1,
      ),
    );
    $expected = array(
      0 => array('#markup' => 'DEFAULT: Apple'),
      1 => array('#markup' => 'DEFAULT: Banana'),
      2 => array('#markup' => 'DEFAULT: Carrot'),
    );
    $this->assertFallbackFormatter($this->node, $formatters, $expected);

    $formatters = array(
      'fallback_test_a' => array(
        'settings' => array('deny' => TRUE),
        'status' => 1,
      ),
      'fallback_test_b' => array(
        'status' => 1,
      ),
      'fallback_test_default' => array(
        'settings' => array('prefix' => 'DEFAULT: '),
        'status' => 1,
      ),
    );
    $expected = array(
      // Delta 0 skips the first formatter, but we test that it is still
      // returned in the proper order since the last formatter displayed it.
      0 => array('#markup' => 'DEFAULT: Apple'),
      1 => array('#markup' => 'B: Banana'),
      2 => array('#markup' => 'DEFAULT: Carrot'),
    );
    $this->assertFallbackFormatter($this->node, $formatters, $expected);
  }

  protected function assertFallbackFormatter($entity, array $formatters = array(), array $expected_output) {
    $display = array(
      'type' => 'fallback',
      'settings' => array('formatters' => $formatters),
    );
    $output = $entity->test_text->view($display);
    $output = array_intersect_key($output, Element::children($output));
    $this->assertEqual($output, $expected_output);
  }

}
