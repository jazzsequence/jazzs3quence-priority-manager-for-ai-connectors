<?php
/**
 * Unit tests for provider preference and model list reordering.
 */

namespace AiConnectorPriority\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PrioritiesTest extends TestCase {

	protected function setUp(): void {
		unset( $GLOBALS['_test_wp_options'] );
		$GLOBALS['_test_ai_connectors'] = [
			'anthropic' => [ 'name' => 'Anthropic (Claude)' ],
			'google'    => [ 'name' => 'Google (Gemini)' ],
			'openai'    => [ 'name' => 'OpenAI' ],
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_wp_options'] );
		unset( $GLOBALS['_test_ai_connectors'] );
		unset( $GLOBALS['_test_active_connectors'] );
	}

	// -------------------------------------------------------------------------
	// get_priorities()
	// -------------------------------------------------------------------------

	public function test_returns_string_not_array_per_task(): void {
		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertIsString( $priorities['text'] );
		$this->assertIsString( $priorities['image'] );
		$this->assertIsString( $priorities['vision'] );
	}

	public function test_defaults_to_first_active_provider_for_each_task(): void {
		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertNotEmpty( $priorities['text'] );
		$this->assertNotEmpty( $priorities['image'] );
		$this->assertNotEmpty( $priorities['vision'] );
	}

	public function test_saved_provider_overrides_default(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => 'openai',
		];

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( 'openai', $priorities['text'] );
	}

	public function test_priorities_has_all_three_task_keys(): void {
		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertArrayHasKey( 'text', $priorities );
		$this->assertArrayHasKey( 'image', $priorities );
		$this->assertArrayHasKey( 'vision', $priorities );
	}

	public function test_migrates_old_array_format_to_string(): void {
		// 1.0.x stored an ordered array; 1.1.x stores a single string.
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'openai', 'google', 'anthropic' ],
		];

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( 'openai', $priorities['text'] );
	}

	public function test_returns_empty_string_when_no_providers_active(): void {
		$GLOBALS['_test_ai_connectors'] = [];

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( '', $priorities['text'] );
		$this->assertSame( '', $priorities['image'] );
		$this->assertSame( '', $priorities['vision'] );
	}

	// -------------------------------------------------------------------------
	// reorder_model_list()
	// -------------------------------------------------------------------------

	public function test_preferred_provider_models_come_first(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => 'openai',
		];
		$models = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
			[ 'openai', 'gpt-a' ],
		];

		$result = \AiConnectorPriority\reorder_model_list( $models, 'text' );

		$this->assertSame( 'openai', $result[0][0] );
	}

	public function test_inactive_provider_models_are_dropped(): void {
		$GLOBALS['_test_ai_connectors'] = [
			'google' => [ 'name' => 'Google (Gemini)' ],
		];
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => 'google',
		];
		$models = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
			[ 'openai', 'gpt-a' ],
		];

		$result    = \AiConnectorPriority\reorder_model_list( $models, 'text' );
		$providers = array_column( $result, 0 );

		$this->assertContains( 'google', $providers );
		$this->assertNotContains( 'anthropic', $providers );
		$this->assertNotContains( 'openai', $providers );
	}

	public function test_other_active_providers_follow_preferred_in_original_order(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => 'openai',
		];
		$models = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
			[ 'openai', 'gpt-a' ],
		];

		$result    = \AiConnectorPriority\reorder_model_list( $models, 'text' );
		$providers = array_column( $result, 0 );

		$this->assertSame( 'openai', $providers[0] );
		$this->assertContains( 'anthropic', $providers );
		$this->assertContains( 'google', $providers );
	}

	public function test_returns_empty_when_no_providers_active(): void {
		$GLOBALS['_test_ai_connectors'] = [];
		$models                         = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
		];

		$result = \AiConnectorPriority\reorder_model_list( $models, 'text' );

		$this->assertEmpty( $result );
	}

	public function test_preserves_per_provider_model_order(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => 'google',
		];
		$models = [
			[ 'google', 'gemini-flash' ],
			[ 'google', 'gemini-pro' ],
			[ 'openai', 'gpt-4' ],
		];

		$result = \AiConnectorPriority\reorder_model_list( $models, 'text' );

		$this->assertSame( [ 'google', 'gemini-flash' ], $result[0] );
		$this->assertSame( [ 'google', 'gemini-pro' ], $result[1] );
		$this->assertSame( [ 'openai', 'gpt-4' ], $result[2] );
	}
}
