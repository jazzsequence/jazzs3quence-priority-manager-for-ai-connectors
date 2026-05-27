<?php
/**
 * Unit tests for priority retrieval and model list building.
 */

namespace AiConnectorPriority\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PrioritiesTest extends TestCase {

	protected function setUp(): void {
		unset( $GLOBALS['_test_wp_options'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_test_wp_options'] );
	}

	// -------------------------------------------------------------------------
	// get_priorities()
	// -------------------------------------------------------------------------

	public function test_returns_defaults_when_no_option_stored(): void {
		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( [ 'anthropic', 'google', 'openai' ], $priorities['text'] );
		$this->assertSame( [ 'openai', 'google' ], $priorities['image'] );
		$this->assertSame( [ 'anthropic', 'google', 'openai' ], $priorities['vision'] );
	}

	public function test_saved_text_priority_overrides_default(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'openai', 'google', 'anthropic' ],
		];

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( [ 'openai', 'google', 'anthropic' ], $priorities['text'] );
	}

	public function test_partial_saved_option_fills_missing_tasks_with_defaults(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text' => [ 'openai', 'google', 'anthropic' ],
		];

		$priorities = \AiConnectorPriority\get_priorities();

		// image and vision were not saved, so defaults apply.
		$this->assertSame( [ 'openai', 'google' ], $priorities['image'] );
		$this->assertSame( [ 'anthropic', 'google', 'openai' ], $priorities['vision'] );
	}

	public function test_priorities_has_all_three_task_keys(): void {
		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertArrayHasKey( 'text', $priorities );
		$this->assertArrayHasKey( 'image', $priorities );
		$this->assertArrayHasKey( 'vision', $priorities );
	}

	// -------------------------------------------------------------------------
	// build_model_list()
	// -------------------------------------------------------------------------

	public function test_text_list_starts_with_anthropic_by_default(): void {
		$models = \AiConnectorPriority\build_model_list( 'text' );

		$this->assertNotEmpty( $models );
		$this->assertSame( 'anthropic', $models[0][0] );
	}

	public function test_image_list_contains_no_anthropic_models(): void {
		$models = \AiConnectorPriority\build_model_list( 'image' );

		foreach ( $models as $pair ) {
			$this->assertNotSame( 'anthropic', $pair[0] );
		}
	}

	public function test_image_list_starts_with_openai_by_default(): void {
		$models = \AiConnectorPriority\build_model_list( 'image' );

		$this->assertNotEmpty( $models );
		$this->assertSame( 'openai', $models[0][0] );
	}

	public function test_saved_priority_reorders_model_list(): void {
		$GLOBALS['_test_wp_options'][ \AiConnectorPriority\OPTION_KEY ] = [
			'text'   => [ 'openai', 'google', 'anthropic' ],
			'image'  => [ 'google', 'openai' ],
			'vision' => [ 'openai', 'google', 'anthropic' ],
		];

		$text_models = \AiConnectorPriority\build_model_list( 'text' );
		$this->assertSame( 'openai', $text_models[0][0] );

		$image_models = \AiConnectorPriority\build_model_list( 'image' );
		$this->assertSame( 'google', $image_models[0][0] );
	}

	public function test_model_list_includes_all_providers_in_order(): void {
		$models     = \AiConnectorPriority\build_model_list( 'text' );
		$providers  = array_column( $models, 0 );
		$seen_order = array_values( array_unique( $providers ) );

		// Default order: anthropic, google, openai.
		$this->assertSame( 'anthropic', $seen_order[0] );
		$this->assertSame( 'google', $seen_order[1] );
		$this->assertSame( 'openai', $seen_order[2] );
	}
}
