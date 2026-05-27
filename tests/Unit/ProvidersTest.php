<?php
/**
 * Unit tests for provider and model lookup functions.
 */

namespace AiConnectorPriority\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProvidersTest extends TestCase {

	// -------------------------------------------------------------------------
	// get_providers_for_task()
	// -------------------------------------------------------------------------

	public function test_text_task_includes_all_providers(): void {
		$providers = \AiConnectorPriority\get_providers_for_task( 'text' );

		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_image_task_excludes_anthropic(): void {
		$providers = \AiConnectorPriority\get_providers_for_task( 'image' );

		$this->assertArrayNotHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_vision_task_includes_all_providers(): void {
		$providers = \AiConnectorPriority\get_providers_for_task( 'vision' );

		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'google', $providers );
		$this->assertArrayHasKey( 'openai', $providers );
	}

	public function test_providers_have_display_labels(): void {
		$providers = \AiConnectorPriority\get_providers_for_task( 'text' );

		foreach ( $providers as $label ) {
			$this->assertIsString( $label );
			$this->assertNotEmpty( $label );
		}
	}

	// -------------------------------------------------------------------------
	// get_models_for_provider()
	// -------------------------------------------------------------------------

	public function test_anthropic_text_models_are_anthropic_provider(): void {
		$models = \AiConnectorPriority\get_models_for_provider( 'anthropic', 'text' );

		$this->assertNotEmpty( $models );
		foreach ( $models as $pair ) {
			$this->assertSame( 'anthropic', $pair[0] );
		}
	}

	public function test_anthropic_image_models_is_empty(): void {
		$models = \AiConnectorPriority\get_models_for_provider( 'anthropic', 'image' );

		$this->assertEmpty( $models );
	}

	public function test_anthropic_vision_models_are_anthropic_provider(): void {
		$models = \AiConnectorPriority\get_models_for_provider( 'anthropic', 'vision' );

		$this->assertNotEmpty( $models );
		foreach ( $models as $pair ) {
			$this->assertSame( 'anthropic', $pair[0] );
		}
	}

	public function test_google_image_models_are_google_provider(): void {
		$models = \AiConnectorPriority\get_models_for_provider( 'google', 'image' );

		$this->assertNotEmpty( $models );
		foreach ( $models as $pair ) {
			$this->assertSame( 'google', $pair[0] );
		}
	}

	public function test_openai_image_models_are_openai_provider(): void {
		$models = \AiConnectorPriority\get_models_for_provider( 'openai', 'image' );

		$this->assertNotEmpty( $models );
		foreach ( $models as $pair ) {
			$this->assertSame( 'openai', $pair[0] );
		}
	}

	public function test_unknown_provider_returns_empty(): void {
		$models = \AiConnectorPriority\get_models_for_provider( 'unknown', 'text' );

		$this->assertEmpty( $models );
	}

	public function test_models_are_two_element_pairs(): void {
		$models = \AiConnectorPriority\get_models_for_provider( 'google', 'text' );

		$this->assertNotEmpty( $models );
		foreach ( $models as $pair ) {
			$this->assertCount( 2, $pair );
			$this->assertIsString( $pair[0] );
			$this->assertIsString( $pair[1] );
		}
	}
}
