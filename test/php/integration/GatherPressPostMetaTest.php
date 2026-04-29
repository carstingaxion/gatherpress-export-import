<?php
/**
 * Tests for GatherPress registered post meta keys.
 *
 * Validates that the registered post meta keys documented in
 * `docs/gatherpress-post-meta.md` match the actual meta keys
 * registered by the GatherPress plugin. Acts as an early-warning
 * system: if GatherPress adds, removes, or renames a registered
 * meta key, these tests will fail.
 *
 * These tests rely on the bootstrap's `init:20` hook, which caches
 * the registered meta keys into `$GLOBALS['gpei_test_init20_cache']`.
 * This avoids calling `do_action( 'init' )` inside test classes.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.2.0
 */

namespace GatherPressExportImport\Tests\Integration;

/**
 * Class GatherPressPostMetaTest.
 *
 * GatherPress registers its post types and post meta during the `init` hook
 * (at priority 10–12). The bootstrap hooks into `init` at priority 20 to
 * cache all registered meta keys into a global. This test class reads from
 * that cache, ensuring no `do_action( 'init' )` call is ever needed.
 *
 * @since 0.2.0
 * @group gatherpress-compat
 * @group post-meta
 */
class GatherPressPostMetaTest extends TestCase {

	/**
	 * Expected registered meta keys for the gatherpress_event post type.
	 *
	 * This list must match the keys documented in docs/gatherpress-post-meta.md.
	 * If GatherPress changes its registered meta, update both this array and
	 * the documentation file.
	 *
	 * @since 0.2.0
	 *
	 * @var string[]
	 */
	private const EXPECTED_EVENT_META_KEYS = array(
		'gatherpress_datetime',
		'gatherpress_datetime_start',
		'gatherpress_datetime_start_gmt',
		'gatherpress_datetime_end',
		'gatherpress_datetime_end_gmt',
		'gatherpress_timezone',
		'gatherpress_max_guest_limit',
		'gatherpress_enable_anonymous_rsvp',
		'gatherpress_online_event_link',
		'gatherpress_max_attendance_limit',
	);

	/**
	 * Expected registered meta keys for the gatherpress_venue post type.
	 *
	 * @since 0.2.0
	 *
	 * @var string[]
	 */
	private const EXPECTED_VENUE_META_KEYS = array(
		'gatherpress_venue_information',
	);

	/**
	 * Cached registered meta keys for gatherpress_event, populated from
	 * the bootstrap's init:20 hook.
	 *
	 * @since 0.2.0
	 *
	 * @var array<string, array>
	 */
	private array $event_meta_registry = array();

	/**
	 * Cached registered meta keys for gatherpress_venue, populated from
	 * the bootstrap's init:20 hook.
	 *
	 * @since 0.2.0
	 *
	 * @var array<string, array>
	 */
	private array $venue_meta_registry = array();

	/**
	 * Sets up the test fixture.
	 *
	 * Reads the init:20 cache populated by the bootstrap. If the cache
	 * is empty (indicating init:20 never fired or GatherPress didn't
	 * register meta), the test will skip gracefully.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$cache = isset( $GLOBALS['gpei_test_init20_cache'] ) ? $GLOBALS['gpei_test_init20_cache'] : null;

		if ( is_array( $cache ) && ! empty( $cache['event_meta_keys'] ) ) {
			$this->event_meta_registry = $cache['event_meta_keys'];
			$this->venue_meta_registry = $cache['venue_meta_keys'];
		} else {
			// Fallback: try reading directly (works if init has fully completed).
			$this->event_meta_registry = get_registered_meta_keys( 'post', 'gatherpress_event' );
			$this->venue_meta_registry = get_registered_meta_keys( 'post', 'gatherpress_venue' );
		}
	}

	/**
	 * Gets the registered meta key names for gatherpress_event.
	 *
	 * @since 0.2.0
	 *
	 * @return string[] Array of registered meta key names.
	 */
	private function get_event_meta_key_names(): array {
		return array_keys( $this->event_meta_registry );
	}

	/**
	 * Gets the registered meta key names for gatherpress_venue.
	 *
	 * @since 0.2.0
	 *
	 * @return string[] Array of registered meta key names.
	 */
	private function get_venue_meta_key_names(): array {
		return array_keys( $this->venue_meta_registry );
	}

	/**
	 * Tests that GatherPress is active before running meta tests.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_gatherpress_is_active(): void {
		$this->assertTrue(
			$this->is_gatherpress_active(),
			'GatherPress must be active for post meta tests to run.'
		);
	}

	/**
	 * Tests that the init:20 cache was populated.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_init20_cache_was_populated(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertNotEmpty(
			$this->event_meta_registry,
			'The init:20 cache for gatherpress_event meta keys should not be empty. '
			. 'Ensure the bootstrap init:20 hook fired correctly.'
		);
	}

	/**
	 * Tests that all expected event meta keys are registered.
	 *
	 * Verifies that every key listed in EXPECTED_EVENT_META_KEYS is
	 * actually registered by GatherPress for the gatherpress_event
	 * post type. Failure indicates GatherPress removed or renamed a key.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_all_expected_event_meta_keys_are_registered(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$registered = $this->get_event_meta_key_names();

		foreach ( self::EXPECTED_EVENT_META_KEYS as $expected_key ) {
			$this->assertContains(
				$expected_key,
				$registered,
				sprintf(
					'Expected meta key "%s" is not registered for gatherpress_event. '
					. 'GatherPress may have removed or renamed this key. '
					. 'Update docs/gatherpress-post-meta.md and EXPECTED_EVENT_META_KEYS accordingly.',
					$expected_key
				)
			);
		}
	}

	/**
	 * Tests that no unexpected event meta keys are registered.
	 *
	 * Verifies that GatherPress has not added new registered meta keys
	 * for gatherpress_event that are not yet documented. Failure indicates
	 * a new key was added and should be evaluated for import support.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_no_unexpected_event_meta_keys_registered(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$registered = $this->get_event_meta_key_names();

		// Filter to only GatherPress-prefixed keys (ignore keys from other plugins).
		$gp_registered = array_filter(
			$registered,
			function ( string $key ): bool {
				return 0 === strpos( $key, 'gatherpress_' );
			}
		);

		$unexpected = array_diff( $gp_registered, self::EXPECTED_EVENT_META_KEYS );

		$this->assertEmpty(
			$unexpected,
			sprintf(
				'GatherPress registered new meta key(s) for gatherpress_event that are not yet documented: %s. '
				. 'Evaluate these for import support and update docs/gatherpress-post-meta.md and EXPECTED_EVENT_META_KEYS.',
				implode( ', ', $unexpected )
			)
		);
	}

	/**
	 * Tests that all expected venue meta keys are registered.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_all_expected_venue_meta_keys_are_registered(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$registered = $this->get_venue_meta_key_names();

		foreach ( self::EXPECTED_VENUE_META_KEYS as $expected_key ) {
			$this->assertContains(
				$expected_key,
				$registered,
				sprintf(
					'Expected meta key "%s" is not registered for gatherpress_venue. '
					. 'GatherPress may have removed or renamed this key. '
					. 'Update docs/gatherpress-post-meta.md and EXPECTED_VENUE_META_KEYS accordingly.',
					$expected_key
				)
			);
		}
	}

	/**
	 * Tests that no unexpected venue meta keys are registered.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_no_unexpected_venue_meta_keys_registered(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$registered = $this->get_venue_meta_key_names();

		// Filter to only GatherPress-prefixed keys.
		$gp_registered = array_filter(
			$registered,
			function ( string $key ): bool {
				return 0 === strpos( $key, 'gatherpress_' );
			}
		);

		$unexpected = array_diff( $gp_registered, self::EXPECTED_VENUE_META_KEYS );

		$this->assertEmpty(
			$unexpected,
			sprintf(
				'GatherPress registered new meta key(s) for gatherpress_venue that are not yet documented: %s. '
				. 'Evaluate these for import support and update docs/gatherpress-post-meta.md and EXPECTED_VENUE_META_KEYS.',
				implode( ', ', $unexpected )
			)
		);
	}

	/**
	 * Tests the expected type of the gatherpress_online_event_link meta key.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_online_event_link_meta_type(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertArrayHasKey( 'gatherpress_online_event_link', $this->event_meta_registry );
		$this->assertSame(
			'string',
			$this->event_meta_registry['gatherpress_online_event_link']['type'],
			'gatherpress_online_event_link should be registered as type "string".'
		);
	}

	/**
	 * Tests the expected type of the gatherpress_enable_anonymous_rsvp meta key.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_enable_anonymous_rsvp_meta_type(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertArrayHasKey( 'gatherpress_enable_anonymous_rsvp', $this->event_meta_registry );
		$this->assertSame(
			'boolean',
			$this->event_meta_registry['gatherpress_enable_anonymous_rsvp']['type'],
			'gatherpress_enable_anonymous_rsvp should be registered as type "boolean".'
		);
	}

	/**
	 * Tests the expected type of the gatherpress_max_guest_limit meta key.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_max_guest_limit_meta_type(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertArrayHasKey( 'gatherpress_max_guest_limit', $this->event_meta_registry );
		$this->assertSame(
			'integer',
			$this->event_meta_registry['gatherpress_max_guest_limit']['type'],
			'gatherpress_max_guest_limit should be registered as type "integer".'
		);
	}

	/**
	 * Tests the expected type of the gatherpress_max_attendance_limit meta key.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_max_attendance_limit_meta_type(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertArrayHasKey( 'gatherpress_max_attendance_limit', $this->event_meta_registry );
		$this->assertSame(
			'integer',
			$this->event_meta_registry['gatherpress_max_attendance_limit']['type'],
			'gatherpress_max_attendance_limit should be registered as type "integer".'
		);
	}

	/**
	 * Tests the expected type of the gatherpress_venue_information meta key.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_venue_information_meta_type(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertArrayHasKey( 'gatherpress_venue_information', $this->venue_meta_registry );
		$this->assertSame(
			'string',
			$this->venue_meta_registry['gatherpress_venue_information']['type'],
			'gatherpress_venue_information should be registered as type "string".'
		);
	}

	/**
	 * Tests that gatherpress_venue_information is shown in REST API.
	 *
	 * The venue information meta should be accessible via the REST API
	 * for GatherPress's block editor integration.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_venue_information_meta_is_in_rest(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->assertArrayHasKey( 'gatherpress_venue_information', $this->venue_meta_registry );
		$this->assertTrue(
			$this->venue_meta_registry['gatherpress_venue_information']['show_in_rest'],
			'gatherpress_venue_information should be exposed in the REST API.'
		);
	}

	/**
	 * Tests that event meta keys are shown in REST API.
	 *
	 * All GatherPress event meta keys should be accessible via REST
	 * for the block editor.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_event_meta_keys_are_in_rest(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		foreach ( self::EXPECTED_EVENT_META_KEYS as $key ) {
			if ( ! isset( $this->event_meta_registry[ $key ] ) ) {
				continue; // Handled by the existence test above.
			}

			$this->assertTrue(
				$this->event_meta_registry[ $key ]['show_in_rest'],
				sprintf( 'Event meta key "%s" should be exposed in the REST API.', $key )
			);
		}
	}

	/**
	 * Tests that the gatherpress_venue_information meta can store valid JSON.
	 *
	 * Creates a venue post and saves the expected JSON structure to verify
	 * the meta key accepts the format used by the migration plugin's
	 * `save_venue_information()` method.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_venue_information_meta_accepts_json(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$venue_id = $this->create_venue( 'JSON Test Venue', 'json-test-venue' );

		$json = wp_json_encode(
			array(
				'fullAddress' => '123 Main St, Portland, OR, 97201, United States',
				'phoneNumber' => '+1-503-555-0100',
				'website'     => 'https://example.com',
				'latitude'    => '45.5231',
				'longitude'   => '-122.6765',
			)
		);

		$result = update_post_meta( $venue_id, 'gatherpress_venue_information', $json );

		$this->assertNotFalse( $result, 'Should be able to save JSON to gatherpress_venue_information meta.' );

		$saved = get_post_meta( $venue_id, 'gatherpress_venue_information', true );
		$this->assertSame( $json, $saved, 'Saved JSON should match the input.' );

		$decoded = json_decode( $saved, true );
		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'fullAddress', $decoded );
		$this->assertArrayHasKey( 'phoneNumber', $decoded );
		$this->assertArrayHasKey( 'website', $decoded );
		$this->assertArrayHasKey( 'latitude', $decoded );
		$this->assertArrayHasKey( 'longitude', $decoded );
	}

	/**
	 * Tests that the total count of GatherPress event meta keys matches expectations.
	 *
	 * This is a broader check that catches both additions and removals
	 * in a single assertion. The per-key tests above provide specific
	 * failure messages.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_event_meta_key_count_matches(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$registered = $this->get_event_meta_key_names();

		// Filter to only GatherPress-prefixed keys.
		$gp_registered = array_filter(
			$registered,
			function ( string $key ): bool {
				return 0 === strpos( $key, 'gatherpress_' );
			}
		);

		$this->assertCount(
			count( self::EXPECTED_EVENT_META_KEYS ),
			$gp_registered,
			sprintf(
				'Expected %d GatherPress meta keys for gatherpress_event, found %d. '
				. 'Registered keys: %s',
				count( self::EXPECTED_EVENT_META_KEYS ),
				count( $gp_registered ),
				implode( ', ', $gp_registered )
			)
		);
	}

	/**
	 * Tests that the total count of GatherPress venue meta keys matches expectations.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_venue_meta_key_count_matches(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$registered = $this->get_venue_meta_key_names();

		// Filter to only GatherPress-prefixed keys.
		$gp_registered = array_filter(
			$registered,
			function ( string $key ): bool {
				return 0 === strpos( $key, 'gatherpress_' );
			}
		);

		$this->assertCount(
			count( self::EXPECTED_VENUE_META_KEYS ),
			$gp_registered,
			sprintf(
				'Expected %d GatherPress meta keys for gatherpress_venue, found %d. '
				. 'Registered keys: %s',
				count( self::EXPECTED_VENUE_META_KEYS ),
				count( $gp_registered ),
				implode( ', ', $gp_registered )
			)
		);
	}
}
