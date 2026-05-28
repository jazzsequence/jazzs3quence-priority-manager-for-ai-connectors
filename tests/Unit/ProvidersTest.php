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

	public function test_returns_empty_when_no_connectors_active(): void {
		$GLOBALS['_test_ai_connectors'] = [];

		$this->assertEmpty( \AiConnectorPriority\get_active_connectors() );
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
