<?php
/**
 * Compatibility tests for required GatherPress API methods.
 *
 * Verifies that the GatherPress methods relied upon by this plugin
 * are still available. These tests act as an early-warning system:
 * if a future GatherPress release removes or renames a method, the
 * test suite will fail before any runtime error can occur.
 *
 * These tests rely on the bootstrap's `init:20` hook, which caches
 * the GatherPress registration state into `$GLOBALS['gpei_test_init20_cache']`.
 * This avoids calling `do_action( 'init' )` inside test classes.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.2.0
 */

namespace GatherPressExportImport\Tests\Integration;

/**
 * Class GatherPressCompatibilityTest.
 *
 * @since 0.2.0
 * @group gatherpress-compat
 */
class GatherPressCompatibilityTest extends TestCase {

	/**
	 * Tests that the GatherPress Event class exists.
	 *
	 * The migration plugin instantiates `\GatherPress\Core\Event`
	 * in every adapter's `convert_datetimes()` call chain via the
	 * `Datetime_Helper::save_gatherpress_datetimes()` method.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_gatherpress_event_class_exists(): void {
		$this->assertTrue(
			class_exists( '\GatherPress\Core\Event' ),
			'GatherPress\Core\Event class must be available. '
			. 'Ensure the GatherPress plugin is installed and activated in the test environment.'
		);
	}

	/**
	 * Tests that the GatherPress Event class has the save_datetimes() method.
	 *
	 * The `save_datetimes()` method is called by the `Datetime_Helper` trait
	 * to persist converted event datetimes into the `gp_event_extended`
	 * table. If GatherPress removes or renames this method, all datetime
	 * conversion will break at runtime.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_gatherpress_event_has_save_datetimes_method(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$event_id = $this->create_event( 'Compatibility Check Event' );
		$event    = new \GatherPress\Core\Event( $event_id );

		$this->assertTrue(
			method_exists( $event, 'save_datetimes' ),
			'GatherPress\Core\Event must have a save_datetimes() method. '
			. 'The migration plugin depends on this method to persist converted datetimes.'
		);
	}

	/**
	 * Tests that the GatherPress Event class has the get_datetime() method.
	 *
	 * The `get_datetime()` method is used in integration tests to verify
	 * that datetimes were correctly saved. It is also part of the public
	 * GatherPress API that end users rely on.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_gatherpress_event_has_get_datetime_method(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$event_id = $this->create_event( 'Datetime Getter Check Event' );
		$event    = new \GatherPress\Core\Event( $event_id );

		$this->assertTrue(
			method_exists( $event, 'get_datetime' ),
			'GatherPress\Core\Event must have a get_datetime() method.'
		);
	}

	/**
	 * Tests that the _gatherpress_venue shadow taxonomy is registered.
	 *
	 * The migration plugin relies on this taxonomy to link events to
	 * venues. GatherPress auto-registers it when the plugin is active.
	 *
	 * Uses the init:20 cache from the bootstrap when available.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_gatherpress_venue_shadow_taxonomy_exists(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$cache = isset( $GLOBALS['gpei_test_init20_cache'] ) ? $GLOBALS['gpei_test_init20_cache'] : null;

		if ( is_array( $cache ) ) {
			$this->assertTrue(
				$cache['taxonomy_exists_venue'],
				'The _gatherpress_venue shadow taxonomy must be registered by GatherPress (checked at init:20).'
			);
		} else {
			$this->assertTrue(
				taxonomy_exists( '_gatherpress_venue' ),
				'The _gatherpress_venue shadow taxonomy must be registered by GatherPress.'
			);
		}
	}

	/**
	 * Tests that the gatherpress_venue post type is registered.
	 *
	 * The migration plugin rewrites third-party venue post types to
	 * `gatherpress_venue`. This post type must exist for venues to be
	 * created during import.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_gatherpress_venue_post_type_exists(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$cache = isset( $GLOBALS['gpei_test_init20_cache'] ) ? $GLOBALS['gpei_test_init20_cache'] : null;

		if ( is_array( $cache ) ) {
			$this->assertTrue(
				$cache['post_type_exists_venue'],
				'The gatherpress_venue post type must be registered by GatherPress (checked at init:20).'
			);
		} else {
			$this->assertTrue(
				post_type_exists( 'gatherpress_venue' ),
				'The gatherpress_venue post type must be registered by GatherPress.'
			);
		}
	}

	/**
	 * Tests that the gatherpress_event post type is registered.
	 *
	 * The migration plugin rewrites third-party event post types to
	 * `gatherpress_event`. This post type must exist for events to be
	 * created during import.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_gatherpress_event_post_type_exists(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$cache = isset( $GLOBALS['gpei_test_init20_cache'] ) ? $GLOBALS['gpei_test_init20_cache'] : null;

		if ( is_array( $cache ) ) {
			$this->assertTrue(
				$cache['post_type_exists_event'],
				'The gatherpress_event post type must be registered by GatherPress (checked at init:20).'
			);
		} else {
			$this->assertTrue(
				post_type_exists( 'gatherpress_event' ),
				'The gatherpress_event post type must be registered by GatherPress.'
			);
		}
	}

	/**
	 * Tests that save_datetimes() accepts the expected parameter format.
	 *
	 * Performs an actual datetime save and verifies it succeeds, ensuring
	 * the method signature hasn't changed in a way that would silently
	 * break the migration.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_save_datetimes_accepts_expected_params(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$event_id = $this->create_event( 'Param Format Check Event' );
		$event    = new \GatherPress\Core\Event( $event_id );

		$params = array(
			'datetime_start' => '2025-12-01 10:00:00',
			'datetime_end'   => '2025-12-01 12:00:00',
			'timezone'       => 'America/New_York',
		);

		$result = $event->save_datetimes( $params );

		$this->assertNotFalse(
			$result,
			'Event::save_datetimes() must accept an array with datetime_start, datetime_end, and timezone keys.'
		);

		// Verify the data was actually persisted.
		$datetime = $event->get_datetime();
		$this->assertNotEmpty( $datetime, 'Saved datetime should be retrievable via get_datetime().' );
		$this->assertArrayHasKey( 'datetime_start', $datetime );
		$this->assertStringContainsString( '2025-12-01', $datetime['datetime_start'] );
	}
}
