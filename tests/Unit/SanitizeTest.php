<?php
/**
 * Unit tests for sanitize_provider_order().
 */

namespace AiConnectorPriority\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SanitizeTest extends TestCase {

	private array $valid = [ 'anthropic', 'google', 'openai' ];

	// -------------------------------------------------------------------------
	// sanitize_provider_order()
	// -------------------------------------------------------------------------

	public function test_valid_complete_order_is_preserved(): void {
		$result = \AiConnectorPriority\sanitize_provider_order( $this->valid, $this->valid );

		$this->assertSame( $this->valid, $result );
	}

	public function test_invalid_provider_is_removed(): void {
		$result = \AiConnectorPriority\sanitize_provider_order(
			[ 'anthropic', 'evil', 'openai' ],
			$this->valid
		);

		$this->assertNotContains( 'evil', $result );
	}

	public function test_missing_valid_provider_is_appended(): void {
		$result = \AiConnectorPriority\sanitize_provider_order( [ 'anthropic' ], $this->valid );

		$this->assertContains( 'google', $result );
		$this->assertContains( 'openai', $result );
		$this->assertCount( 3, $result );
	}

	public function test_result_always_contains_every_valid_provider(): void {
		$result = \AiConnectorPriority\sanitize_provider_order( [], $this->valid );

		foreach ( $this->valid as $provider ) {
			$this->assertContains( $provider, $result );
		}
	}

	public function test_duplicate_provider_appears_once(): void {
		$result = \AiConnectorPriority\sanitize_provider_order(
			[ 'anthropic', 'anthropic', 'google' ],
			$this->valid
		);

		$this->assertSame( 1, array_count_values( $result )['anthropic'] );
	}

	public function test_first_occurrence_of_duplicate_wins(): void {
		$result = \AiConnectorPriority\sanitize_provider_order(
			[ 'openai', 'anthropic', 'openai', 'google' ],
			$this->valid
		);

		$this->assertSame( 'openai', $result[0] );
	}

	public function test_empty_valid_list_returns_empty(): void {
		$result = \AiConnectorPriority\sanitize_provider_order(
			[ 'anthropic', 'google' ],
			[]
		);

		$this->assertEmpty( $result );
	}

	public function test_injected_key_chars_are_sanitized_away(): void {
		$result = \AiConnectorPriority\sanitize_provider_order(
			[ '<script>anthropic</script>' ],
			$this->valid
		);

		$this->assertNotContains( '<script>anthropic</script>', $result );
	}
}
