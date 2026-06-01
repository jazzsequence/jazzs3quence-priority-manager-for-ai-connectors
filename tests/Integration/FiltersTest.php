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

	public function test_plugin_action_links_filter_is_registered(): void {
		$basename = plugin_basename( dirname( __DIR__, 2 ) . '/ai-connector-priority.php' );
		$this->assertTrue( (bool) has_filter( 'plugin_action_links_' . $basename ) );
	}

	public function test_plugin_action_links_includes_configure_link(): void {
		$basename = plugin_basename( dirname( __DIR__, 2 ) . '/ai-connector-priority.php' );
		$links    = apply_filters( 'plugin_action_links_' . $basename, [] );

		$this->assertArrayHasKey( 'configure', $links );
		$this->assertStringContainsString( 'options-general.php', $links['configure'] );
		$this->assertStringContainsString( \AiConnectorPriority\PAGE_SLUG, $links['configure'] );
	}

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
				'text'   => 'openai',
				'image'  => 'google',
				'vision' => 'openai',
			]
		);

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( 'openai', $priorities['text'] );
		$this->assertSame( 'google', $priorities['image'] );
	}

	public function test_get_priorities_migrates_old_array_format(): void {
		update_option(
			\AiConnectorPriority\OPTION_KEY,
			[
				'text'   => [ 'openai', 'google', 'anthropic' ],
				'image'  => [ 'google', 'openai' ],
				'vision' => [ 'openai', 'google', 'anthropic' ],
			]
		);

		$priorities = \AiConnectorPriority\get_priorities();

		$this->assertSame( 'openai', $priorities['text'] );
		$this->assertSame( 'google', $priorities['image'] );
	}

	public function test_saved_provider_changes_filter_output(): void {
		update_option(
			\AiConnectorPriority\OPTION_KEY,
			[ 'text' => 'openai' ]
		);

		$models = apply_filters( 'wpai_preferred_text_models', [] );

		$this->assertSame( 'openai', $models[0][0] );
	}
}
