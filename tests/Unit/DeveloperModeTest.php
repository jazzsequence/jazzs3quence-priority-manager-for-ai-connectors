<?php
/**
 * Unit tests for Developer Mode override detection.
 */

namespace Jazzs3quence\AIPriorityManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeveloperModeTest extends TestCase {

	protected function setUp(): void {
		unset( $GLOBALS['_test_wp_options'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_wp_options'] );
	}

	// -------------------------------------------------------------------------
	// get_task_feature_map()
	// -------------------------------------------------------------------------

	public function test_task_feature_map_has_all_three_task_keys(): void {
		$map = \Jazzs3quence\AIPriorityManager\get_task_feature_map();

		$this->assertArrayHasKey( 'text', $map );
		$this->assertArrayHasKey( 'image', $map );
		$this->assertArrayHasKey( 'vision', $map );
	}

	public function test_text_task_includes_known_features(): void {
		$map = \Jazzs3quence\AIPriorityManager\get_task_feature_map();

		$this->assertContains( 'title-generation', $map['text'] );
		$this->assertContains( 'excerpt-generation', $map['text'] );
		$this->assertContains( 'summarization', $map['text'] );
		$this->assertContains( 'editorial-notes', $map['text'] );
		$this->assertContains( 'editorial-updates', $map['text'] );
		$this->assertContains( 'content-resizing', $map['text'] );
		$this->assertContains( 'meta-description', $map['text'] );
		$this->assertContains( 'content-classification', $map['text'] );
	}

	public function test_text_task_does_not_include_comment_moderation(): void {
		// comment-moderation uses using_model_preference() directly, not
		// set_provider_model_preference(), so it cannot have Developer Mode overrides.
		$map = \Jazzs3quence\AIPriorityManager\get_task_feature_map();

		$this->assertNotContains( 'comment-moderation', $map['text'] );
	}

	public function test_task_is_fully_overridden_when_all_its_features_are_overridden(): void {
		// image has one feature; when it is overridden the task is fully overridden.
		$GLOBALS['_test_wp_options']['wpai_feature_image-generation_field_developer'] = [
			'provider' => 'openai',
			'model'    => 'gpt-image-2',
		];

		$this->assertTrue( \Jazzs3quence\AIPriorityManager\is_task_fully_overridden( 'image' ) );
	}

	public function test_task_is_not_fully_overridden_when_only_some_features_are_overridden(): void {
		// text has many features; overriding one does not fully override the task.
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer'] = [
			'provider' => 'anthropic',
			'model'    => 'claude-sonnet-4-6',
		];

		$this->assertFalse( \Jazzs3quence\AIPriorityManager\is_task_fully_overridden( 'text' ) );
	}

	public function test_image_task_includes_image_generation_feature(): void {
		$map = \Jazzs3quence\AIPriorityManager\get_task_feature_map();

		$this->assertContains( 'image-generation', $map['image'] );
	}

	public function test_vision_task_includes_alt_text_generation_feature(): void {
		$map = \Jazzs3quence\AIPriorityManager\get_task_feature_map();

		$this->assertContains( 'alt-text-generation', $map['vision'] );
	}

	// -------------------------------------------------------------------------
	// get_developer_mode_overridden_tasks()
	// -------------------------------------------------------------------------

	public function test_returns_empty_when_no_developer_mode_overrides_set(): void {
		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertEmpty( $overridden );
	}

	public function test_detects_text_task_override_when_any_text_feature_has_config(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer'] = [
			'provider' => 'anthropic',
			'model'    => 'claude-sonnet-4-6',
		];

		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertContains( 'text', $overridden );
	}

	public function test_detects_image_task_override(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_image-generation_field_developer'] = [
			'provider' => 'openai',
			'model'    => 'gpt-image-2',
		];

		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertContains( 'image', $overridden );
	}

	public function test_detects_vision_task_override(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_alt-text-generation_field_developer'] = [
			'provider' => 'google',
			'model'    => 'gemini-flash',
		];

		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertContains( 'vision', $overridden );
	}

	public function test_returns_only_tasks_with_active_overrides(): void {
		// Only image is overridden.
		$GLOBALS['_test_wp_options']['wpai_feature_image-generation_field_developer'] = [
			'provider' => 'openai',
			'model'    => 'gpt-image-2',
		];

		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertContains( 'image', $overridden );
		$this->assertNotContains( 'text', $overridden );
		$this->assertNotContains( 'vision', $overridden );
	}

	public function test_empty_developer_config_is_not_treated_as_override(): void {
		// An empty array stored in the option means no override is active.
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer'] = [];

		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertNotContains( 'text', $overridden );
	}

	public function test_cleared_developer_config_with_empty_strings_is_not_treated_as_override(): void {
		// When a Developer Mode override is cleared in the AI plugin UI, the option
		// is set to ['provider' => '', 'model' => ''] — not an empty array.
		// !empty() on this array returns true, so we must check the values, not the array.
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer'] = [
			'provider' => '',
			'model'    => '',
		];

		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertNotContains( 'text', $overridden );
	}

	// -------------------------------------------------------------------------
	// get_developer_mode_overrides_by_task()
	// -------------------------------------------------------------------------

	public function test_get_overrides_by_task_returns_empty_when_no_overrides(): void {
		$overrides = \Jazzs3quence\AIPriorityManager\get_developer_mode_overrides_by_task();

		$this->assertEmpty( $overrides );
	}

	public function test_get_overrides_by_task_returns_feature_ids_keyed_by_task(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer']    = [ 'provider' => 'anthropic', 'model' => 'claude-test' ];
		$GLOBALS['_test_wp_options']['wpai_feature_excerpt-generation_field_developer']  = [ 'provider' => 'anthropic', 'model' => 'claude-test' ];
		$GLOBALS['_test_wp_options']['wpai_feature_image-generation_field_developer']    = [ 'provider' => 'openai', 'model' => 'gpt-image-2' ];

		$overrides = \Jazzs3quence\AIPriorityManager\get_developer_mode_overrides_by_task();

		$this->assertArrayHasKey( 'text', $overrides );
		$this->assertContains( 'title-generation', $overrides['text'] );
		$this->assertContains( 'excerpt-generation', $overrides['text'] );
		$this->assertArrayHasKey( 'image', $overrides );
		$this->assertContains( 'image-generation', $overrides['image'] );
		$this->assertArrayNotHasKey( 'vision', $overrides );
	}

	public function test_text_task_returns_at_most_once_even_with_multiple_feature_overrides(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer']  = [
			'provider' => 'anthropic',
			'model'    => 'claude-sonnet-4-6',
		];
		$GLOBALS['_test_wp_options']['wpai_feature_excerpt-generation_field_developer'] = [
			'provider' => 'openai',
			'model'    => 'gpt-5.4-mini',
		];

		$overridden = \Jazzs3quence\AIPriorityManager\get_developer_mode_overridden_tasks();

		$this->assertSame( 1, count( array_filter( $overridden, fn( $t ) => $t === 'text' ) ) );
	}
}
