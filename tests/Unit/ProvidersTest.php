<?php
/**
 * Unit tests for connector discovery and provider-for-task lookup.
 */

namespace AiConnectorPriority\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProvidersTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_test_ai_connectors'] = [
			'anthropic' => [ 'name' => 'Anthropic (Claude)' ],
			'google'    => [ 'name' => 'Google (Gemini)' ],
			'openai'    => [ 'name' => 'OpenAI' ],
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_ai_connectors'] );
		unset( $GLOBALS['_test_active_connectors'] );
	}

	// -------------------------------------------------------------------------
	// get_active_connectors()
	// -------------------------------------------------------------------------

	public function test_returns_connectors_from_ai_plugin(): void {
		$connectors = \AiConnectorPriority\get_active_connectors();

		$this->assertArrayHasKey( 'anthropic', $connectors );
		$this->assertArrayHasKey( 'google', $connectors );
		$this->assertArrayHasKey( 'openai', $connectors );
	}

	public function test_returns_empty_when_no_connectors_registered(): void {
		$GLOBALS['_test_ai_connectors'] = [];

		$this->assertEmpty( \AiConnectorPriority\get_active_connectors() );
	}

	public function test_only_returns_connectors_whose_plugin_is_active(): void {
		// get_ai_connectors(true) checks is_plugin_active() for built-in providers.
		// _test_active_connectors simulates the subset whose plugin is actually active.
		$GLOBALS['_test_ai_connectors']     = [
			'anthropic' => [ 'name' => 'Anthropic (Claude)' ],
			'google'    => [ 'name' => 'Google (Gemini)' ],
			'openai'    => [ 'name' => 'OpenAI' ],
		];
		$GLOBALS['_test_active_connectors'] = [
			'google' => [ 'name' => 'Google (Gemini)' ],
		];

		$connectors = \AiConnectorPriority\get_active_connectors();

		$this->assertArrayHasKey( 'google', $connectors );
		$this->assertArrayNotHasKey( 'anthropic', $connectors );
		$this->assertArrayNotHasKey( 'openai', $connectors );
	}

	// -------------------------------------------------------------------------
	// get_providers_for_task()
	// -------------------------------------------------------------------------

	public function test_providers_for_task_returns_active_providers_in_model_list(): void {
		$models = [
			[ 'anthropic', 'claude-sonnet-4-6' ],
			[ 'google', 'gemini-flash' ],
			[ 'openai', 'gpt-4' ],
		];

		$providers = \AiConnectorPriority\get_providers_for_task( 'text', $models );

		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_providers_for_task_excludes_inactive_connectors(): void {
		$GLOBALS['_test_ai_connectors'] = [
			'google' => [ 'name' => 'Google (Gemini)' ],
		];

		$models = [
			[ 'anthropic', 'claude-sonnet-4-6' ],
			[ 'google', 'gemini-flash' ],
			[ 'openai', 'gpt-4' ],
		];

		$providers = \AiConnectorPriority\get_providers_for_task( 'text', $models );

		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayNotHasKey( 'anthropic', $providers );
		$this->assertArrayNotHasKey( 'openai', $providers );
	}

	public function test_providers_for_task_returns_empty_when_no_connectors(): void {
		$GLOBALS['_test_ai_connectors'] = [];

		$models    = [ [ 'anthropic', 'claude-sonnet-4-6' ] ];
		$providers = \AiConnectorPriority\get_providers_for_task( 'text', $models );

		$this->assertEmpty( $providers );
	}

	public function test_providers_have_display_labels(): void {
		$models    = [
			[ 'anthropic', 'claude-sonnet-4-6' ],
			[ 'google', 'gemini-flash' ],
		];
		$providers = \AiConnectorPriority\get_providers_for_task( 'text', $models );

		foreach ( $providers as $label ) {
			$this->assertIsString( $label );
			$this->assertNotEmpty( $label );
		}
	}

	public function test_providers_for_task_returns_non_empty_when_ai_plugin_functions_exist(): void {
		// get_providers_for_task() must return providers when called without an
		// explicit model list — i.e. when it falls through to get_default_models_for_task()
		// which calls the AI plugin's helper functions. If those functions don't exist
		// (or if we call apply_filters with [] instead), providers would be empty and
		// the admin UI shows nothing.
		$providers = \AiConnectorPriority\get_providers_for_task( 'text' );

		$this->assertNotEmpty( $providers );
		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_providers_for_image_task_excludes_providers_not_in_image_model_list(): void {
		// Anthropic has no image models in the stub, so it should not appear for image task.
		$providers = \AiConnectorPriority\get_providers_for_task( 'image' );

		$this->assertNotEmpty( $providers );
		$this->assertArrayNotHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_each_provider_appears_once_regardless_of_model_count(): void {
		$models = [
			[ 'google', 'gemini-flash' ],
			[ 'google', 'gemini-pro' ],
			[ 'openai', 'gpt-4' ],
		];

		$providers = \AiConnectorPriority\get_providers_for_task( 'text', $models );

		$this->assertCount( 2, $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}
}
