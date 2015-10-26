<?php

/**
 * @file
 * Contains \Drupal\fallback_formatter\Tests\FallbackFormatterOutputTest.
 */

namespace Drupal\fallback_formatter\Tests;

use Drupal\Core\Render\Element;

/**
 * Test basic functionality of the fallback formatter.
 *
 * @group fallback_formatter
 */
class FallbackFormatterOutputTest extends FallbackFormatterTestBase {

  public static $modules = [
    'field_ui',
    'node',
    'fallback_formatter',
    'fallback_formatter_test',
  ];

  /**
   * The admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'create node_test content',
      'access content overview',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the output of the fallback formatter.
   */
  public function testOutput() {
    // Set fallback formatter as the formatter for the new field.
    $this->drupalGet('admin/structure/types/manage/node_test/display');
    $edit = [
      'fields[test_text][type]' => 'fallback',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet('admin/structure/types/manage/node_test/display');
    $this->drupalPostAjaxForm(NULL, array(), "test_text_settings_edit");
    $edit = [
      'fields[test_text][settings_edit_form][settings][formatters][fallback_test_b][status]' => '1',
      'fields[test_text][settings_edit_form][settings][formatters][fallback_test_b][weight]' => 2,
      'fields[test_text][settings_edit_form][settings][formatters][fallback_test_a][status]' => '1',
      'fields[test_text][settings_edit_form][settings][formatters][fallback_test_a][weight]' => 1,
    ];
    $this->drupalPostAjaxForm(NULL, $edit, "test_text_plugin_settings_update");
    $this->drupalPostForm(NULL, [], t('Save'));

    // Check the output of the formatter.
    $this->drupalGet('node/' . $this->node->id());
    $this->assertText('A: Apple');
  }

}
