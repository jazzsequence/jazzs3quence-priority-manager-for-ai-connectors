<?php
/**
 * Unit tests for priority retrieval and model list reordering.
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
	}

	// -------------------------------------------------------------------------
	// get_priorities()
	// -------------------------------------------------------------------------

	public function test_priorities_has_all_three_task_keys(): void {
		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertArrayHasKey( 'text', $priorities );
		$this->assertArrayHasKey( 'image', $priorities );
		$this->assertArrayHasKey( 'vision', $priorities );
	}

	public function test_saved_text_priority_overrides_default(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'openai', 'google', 'anthropic' ],
		];

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( [ 'openai', 'google', 'anthropic' ], $priorities['text'] );
	}

	public function test_partial_saved_option_keeps_other_tasks_as_defaults(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'openai', 'google', 'anthropic' ],
		];

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertIsArray( $priorities['image'] );
		$this->assertIsArray( $priorities['vision'] );
	}

	// -------------------------------------------------------------------------
	// reorder_model_list()
	// -------------------------------------------------------------------------

	public function test_reorder_preserves_all_active_provider_models(): void {
		$models = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
			[ 'openai', 'gpt-a' ],
		];
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'anthropic', 'google', 'openai' ],
		];

		$result = \AiConnectorPriority\reorder_model_list( $models, 'text' );

		$this->assertCount( 3, $result );
	}

	public function test_reorder_applies_saved_priority_order(): void {
		$models = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
			[ 'openai', 'gpt-a' ],
		];
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'openai', 'google', 'anthropic' ],
		];

		$result = \AiConnectorPriority\reorder_model_list( $models, 'text' );

		$this->assertSame( 'openai', $result[0][0] );
		$this->assertSame( 'google', $result[1][0] );
		$this->assertSame( 'anthropic', $result[2][0] );
	}

	public function test_reorder_drops_inactive_provider_models(): void {
		$GLOBALS['_test_ai_connectors'] = [
			'google' => [ 'name' => 'Google (Gemini)' ],
		];
		$models = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
			[ 'openai', 'gpt-a' ],
		];
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'anthropic', 'google', 'openai' ],
		];

		$result    = \AiConnectorPriority\reorder_model_list( $models, 'text' );
		$providers = array_column( $result, 0 );

		$this->assertNotContains( 'anthropic', $providers );
		$this->assertNotContains( 'openai', $providers );
		$this->assertContains( 'google', $providers );
	}

	public function test_reorder_returns_empty_when_no_connectors_active(): void {
		$GLOBALS['_test_ai_connectors'] = [];
		$models                         = [
			[ 'anthropic', 'claude-a' ],
			[ 'google', 'gemini-a' ],
		];

		$result = \AiConnectorPriority\reorder_model_list( $models, 'text' );

		$this->assertEmpty( $result );
	}

	public function test_reorder_appends_unprioritised_active_providers(): void {
		$GLOBALS['_test_ai_connectors'] = [
			'openai' => [ 'name' => 'OpenAI' ],
			'google' => [ 'name' => 'Google (Gemini)' ],
		];
		$models = [
			[ 'openai', 'gpt-a' ],
			[ 'google', 'gemini-a' ],
		];
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'openai' ],
		];

		$result    = \AiConnectorPriority\reorder_model_list( $models, 'text' );
		$providers = array_column( $result, 0 );

		$this->assertSame( 'openai', $providers[0] );
		$this->assertSame( 'google', $providers[1] );
	}

	public function test_reorder_preserves_per_provider_model_order(): void {
		$models = [
			[ 'google', 'gemini-flash' ],
			[ 'google', 'gemini-pro' ],
			[ 'openai', 'gpt-4' ],
		];
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'google', 'openai' ],
		];

		$result = \AiConnectorPriority\reorder_model_list( $models, 'text' );

		$this->assertSame( [ 'google', 'gemini-flash' ], $result[0] );
		$this->assertSame( [ 'google', 'gemini-pro' ], $result[1] );
		$this->assertSame( [ 'openai', 'gpt-4' ], $result[2] );
	}
}
