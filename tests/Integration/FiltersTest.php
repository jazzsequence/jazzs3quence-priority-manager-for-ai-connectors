<?php
/**
 * Integration tests verifying filter registration and option storage with real WordPress.
 */

namespace AiConnectorPriority\Tests\Integration;

class FiltersTest extends \WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( \AiConnectorPriority\OPTION_KEY );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Filter registration
	// -------------------------------------------------------------------------

	public function test_text_models_filter_is_registered(): void {
		$this->assertTrue( (bool) has_filter( 'wpai_preferred_text_models' ) );
	}

	public function test_image_models_filter_is_registered(): void {
		$this->assertTrue( (bool) has_filter( 'wpai_preferred_image_models' ) );
	}

	public function test_vision_models_filter_is_registered(): void {
		$this->assertTrue( (bool) has_filter( 'wpai_preferred_vision_models' ) );
	}

	// -------------------------------------------------------------------------
	// Filter output
	// -------------------------------------------------------------------------

	public function test_text_filter_returns_array_of_pairs(): void {
		$models = apply_filters( 'wpai_preferred_text_models', [] );

		$this->assertIsArray( $models );
		$this->assertNotEmpty( $models );
		foreach ( $models as $pair ) {
			$this->assertCount( 2, $pair );
		}
	}

	public function test_image_filter_excludes_anthropic(): void {
		$models = apply_filters( 'wpai_preferred_image_models', [] );

		foreach ( $models as $pair ) {
			$this->assertNotSame( 'anthropic', $pair[0] );
		}
	}

	public function test_vision_filter_returns_non_empty_list(): void {
		$models = apply_filters( 'wpai_preferred_vision_models', [] );

		$this->assertNotEmpty( $models );
	}

	// -------------------------------------------------------------------------
	// Option storage via get_priorities() / save_priorities()
	// -------------------------------------------------------------------------

	public function test_get_priorities_uses_wp_options(): void {
		update_option(
			\AiConnectorPriority\OPTION_KEY,
			[
				'text'   => [ 'openai', 'anthropic', 'google' ],
				'image'  => [ 'google', 'openai' ],
				'vision' => [ 'openai', 'anthropic', 'google' ],
			]
		);

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( 'openai', $priorities['text'][0] );
		$this->assertSame( 'google', $priorities['image'][0] );
	}

	public function test_saved_priority_changes_filter_output(): void {
		update_option(
			\AiConnectorPriority\OPTION_KEY,
			[
				'text'   => [ 'openai', 'google', 'anthropic' ],
				'image'  => [ 'google', 'openai' ],
				'vision' => [ 'openai', 'google', 'anthropic' ],
			]
		);

		$models = apply_filters( 'wpai_preferred_text_models', [] );

		$this->assertSame( 'openai', $models[0][0] );
	}
}
