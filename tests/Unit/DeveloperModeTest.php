<?php
/**
 * Unit tests for Developer Mode override detection.
 */

namespace AiConnectorPriority\Tests\Unit;

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
		$map = \AiConnectorPriority\get_task_feature_map();

		$this->assertArrayHasKey( 'text', $map );
		$this->assertArrayHasKey( 'image', $map );
		$this->assertArrayHasKey( 'vision', $map );
	}

	public function test_text_task_includes_known_features(): void {
		$map = \AiConnectorPriority\get_task_feature_map();

		$this->assertContains( 'title-generation', $map['text'] );
		$this->assertContains( 'excerpt-generation', $map['text'] );
		$this->assertContains( 'summarization', $map['text'] );
		$this->assertContains( 'editorial-notes', $map['text'] );
		$this->assertContains( 'editorial-updates', $map['text'] );
		$this->assertContains( 'content-resizing', $map['text'] );
		$this->assertContains( 'meta-description', $map['text'] );
		$this->assertContains( 'comment-moderation', $map['text'] );
	}

	public function test_image_task_includes_image_generation_feature(): void {
		$map = \AiConnectorPriority\get_task_feature_map();

		$this->assertContains( 'image-generation', $map['image'] );
	}

	public function test_vision_task_includes_alt_text_generation_feature(): void {
		$map = \AiConnectorPriority\get_task_feature_map();

		$this->assertContains( 'alt-text-generation', $map['vision'] );
	}

	// -------------------------------------------------------------------------
	// get_developer_mode_overridden_tasks()
	// -------------------------------------------------------------------------

	public function test_returns_empty_when_no_developer_mode_overrides_set(): void {
		$overridden = \AiConnectorPriority\get_developer_mode_overridden_tasks();

		$this->assertEmpty( $overridden );
	}

	public function test_detects_text_task_override_when_any_text_feature_has_config(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer'] = [
			'provider' => 'anthropic',
			'model'    => 'claude-sonnet-4-6',
		];

		$overridden = \AiConnectorPriority\get_developer_mode_overridden_tasks();

		$this->assertContains( 'text', $overridden );
	}

	public function test_detects_image_task_override(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_image-generation_field_developer'] = [
			'provider' => 'openai',
			'model'    => 'gpt-image-2',
		];

		$overridden = \AiConnectorPriority\get_developer_mode_overridden_tasks();

		$this->assertContains( 'image', $overridden );
	}

	public function test_detects_vision_task_override(): void {
		$GLOBALS['_test_wp_options']['wpai_feature_alt-text-generation_field_developer'] = [
			'provider' => 'google',
			'model'    => 'gemini-flash',
		];

		$overridden = \AiConnectorPriority\get_developer_mode_overridden_tasks();

		$this->assertContains( 'vision', $overridden );
	}

	public function test_returns_only_tasks_with_active_overrides(): void {
		// Only image is overridden.
		$GLOBALS['_test_wp_options']['wpai_feature_image-generation_field_developer'] = [
			'provider' => 'openai',
			'model'    => 'gpt-image-2',
		];

		$overridden = \AiConnectorPriority\get_developer_mode_overridden_tasks();

		$this->assertContains( 'image', $overridden );
		$this->assertNotContains( 'text', $overridden );
		$this->assertNotContains( 'vision', $overridden );
	}

	public function test_empty_developer_config_is_not_treated_as_override(): void {
		// An empty array stored in the option means no override is active.
		$GLOBALS['_test_wp_options']['wpai_feature_title-generation_field_developer'] = [];

		$overridden = \AiConnectorPriority\get_developer_mode_overridden_tasks();

		$this->assertNotContains( 'text', $overridden );
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

		$overridden = \AiConnectorPriority\get_developer_mode_overridden_tasks();

		$this->assertSame( 1, count( array_filter( $overridden, fn( $t ) => $t === 'text' ) ) );
	}
}
