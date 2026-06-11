<?php
/**
 * Unit tests for connector discovery and provider-for-task lookup.
 */

namespace Jazzs3quence\AIPriorityManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProvidersTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_test_ai_connectors'] = [
			'anthropic' => [ 'name' => 'Anthropic (Claude)' ],
			'google'    => [ 'name' => 'Google (Gemini)' ],
			'openai'    => [ 'name' => 'OpenAI' ],
		];
		// Pre-populate capability transients so tests don't hit the AiClient.
		// Anthropic supports text + vision but NOT image (no image generation models).
		// Google and OpenAI support all three tasks.
		$GLOBALS['_test_transients'] = [
			'aicp_tasks_anthropic' => [ 'text', 'vision' ],
			'aicp_tasks_google'    => [ 'text', 'image', 'vision' ],
			'aicp_tasks_openai'    => [ 'text', 'image', 'vision' ],
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_ai_connectors'] );
		unset( $GLOBALS['_test_active_connectors'] );
		unset( $GLOBALS['_test_transients'] );
	}

	// -------------------------------------------------------------------------
	// get_active_connectors()
	// -------------------------------------------------------------------------

	public function test_returns_connectors_from_ai_plugin(): void {
		$connectors = \Jazzs3quence\AIPriorityManager\get_active_connectors();

		$this->assertArrayHasKey( 'anthropic', $connectors );
		$this->assertArrayHasKey( 'google', $connectors );
		$this->assertArrayHasKey( 'openai', $connectors );
	}

	public function test_returns_empty_when_no_connectors_registered(): void {
		$GLOBALS['_test_ai_connectors'] = [];

		$this->assertEmpty( \Jazzs3quence\AIPriorityManager\get_active_connectors() );
	}

	public function test_only_returns_connectors_whose_plugin_is_active(): void {
		$GLOBALS['_test_ai_connectors']     = [
			'anthropic' => [ 'name' => 'Anthropic (Claude)' ],
			'google'    => [ 'name' => 'Google (Gemini)' ],
			'openai'    => [ 'name' => 'OpenAI' ],
		];
		$GLOBALS['_test_active_connectors'] = [
			'google' => [ 'name' => 'Google (Gemini)' ],
		];

		$connectors = \Jazzs3quence\AIPriorityManager\get_active_connectors();

		$this->assertArrayHasKey( 'google', $connectors );
		$this->assertArrayNotHasKey( 'anthropic', $connectors );
		$this->assertArrayNotHasKey( 'openai', $connectors );
	}

	// -------------------------------------------------------------------------
	// get_provider_supported_tasks()
	// -------------------------------------------------------------------------

	public function test_returns_tasks_from_transient_cache(): void {
		$GLOBALS['_test_transients']['aicp_tasks_anthropic'] = [ 'text', 'vision' ];

		$tasks = \Jazzs3quence\AIPriorityManager\get_provider_supported_tasks( 'anthropic' );

		$this->assertContains( 'text', $tasks );
		$this->assertContains( 'vision', $tasks );
		$this->assertNotContains( 'image', $tasks );
	}

	public function test_falls_back_to_text_and_vision_when_wp_ai_client_unavailable(): void {
		// No transient cached, and wp_ai_client_prompt doesn't exist in unit tests.
		// Image generation is a specialized capability — the safe default excludes it.
		unset( $GLOBALS['_test_transients']['aicp_tasks_newprovider'] );

		$tasks = \Jazzs3quence\AIPriorityManager\get_provider_supported_tasks( 'newprovider' );

		$this->assertContains( 'text', $tasks );
		$this->assertContains( 'vision', $tasks );
		$this->assertNotContains( 'image', $tasks );
	}

	public function test_caches_result_in_transient(): void {
		unset( $GLOBALS['_test_transients']['aicp_tasks_newprovider'] );

		\Jazzs3quence\AIPriorityManager\get_provider_supported_tasks( 'newprovider' );

		// Result should have been stored in the transient.
		$this->assertArrayHasKey( 'aicp_tasks_newprovider', $GLOBALS['_test_transients'] );
	}

	// -------------------------------------------------------------------------
	// get_providers_for_task()
	// -------------------------------------------------------------------------

	public function test_text_task_shows_all_active_providers_that_support_text(): void {
		$providers = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'text' );

		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_image_task_excludes_providers_that_dont_support_image(): void {
		// Anthropic's cached capabilities: ['text', 'vision'] — no image.
		$providers = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'image' );

		$this->assertArrayNotHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_unconfigured_provider_does_not_appear_for_image_task(): void {
		// A provider active but without credentials falls back to ['text', 'vision'].
		// It must NOT appear for image generation, which is a specialized capability.
		$GLOBALS['_test_ai_connectors']['deepseek'] = [ 'name' => 'DeepSeek' ];
		unset( $GLOBALS['_test_transients']['aicp_tasks_deepseek'] );

		$providers = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'image' );

		$this->assertArrayNotHasKey( 'deepseek', $providers );
	}

	public function test_vision_task_shows_providers_that_support_vision(): void {
		$providers = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'vision' );

		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_inactive_providers_are_excluded_regardless_of_capabilities(): void {
		$GLOBALS['_test_ai_connectors'] = [
			'google' => [ 'name' => 'Google (Gemini)' ],
		];

		$providers = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'text' );

		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayNotHasKey( 'anthropic', $providers );
		$this->assertArrayNotHasKey( 'openai', $providers );
	}

	public function test_returns_empty_when_no_connectors_active(): void {
		$GLOBALS['_test_ai_connectors'] = [];

		$this->assertEmpty( \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'text' ) );
	}

	public function test_providers_have_display_labels(): void {
		$providers = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'text' );

		foreach ( $providers as $label ) {
			$this->assertIsString( $label );
			$this->assertNotEmpty( $label );
		}
	}

	public function test_new_provider_without_cached_capabilities_appears_for_text_and_vision_not_image(): void {
		// A provider without cached capabilities (not yet configured) defaults to
		// ['text', 'vision']. Image generation is specialized — we never assume a
		// provider supports it without a confirmed capability check.
		$GLOBALS['_test_ai_connectors']['deepseek'] = [ 'name' => 'DeepSeek' ];
		unset( $GLOBALS['_test_transients']['aicp_tasks_deepseek'] );

		$text   = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'text' );
		$image  = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'image' );
		$vision = \Jazzs3quence\AIPriorityManager\get_providers_for_task( 'vision' );

		$this->assertArrayHasKey( 'deepseek', $text );
		$this->assertArrayNotHasKey( 'deepseek', $image );
		$this->assertArrayHasKey( 'deepseek', $vision );
	}
}
