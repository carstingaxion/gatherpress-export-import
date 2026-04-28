<?php
/**
 * Integration tests for the Event Organiser adapter.
 *
 * Tests the EO adapter's two-pass import strategy, venue creation,
 * datetime conversion, and venue linking against a real WordPress
 * environment with GatherPress active.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.1.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\Event_Organiser_Adapter;

/**
 * Class EOAdapterIntegrationTest.
 *
 * @since 0.1.0
 * @group eo-adapter
 */
class EOAdapterIntegrationTest extends TestCase {

	/**
	 * Tests that the EO adapter is registered in the migration class.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_eo_adapter_is_registered(): void {
		$adapter = $this->get_eo_adapter();
		$this->assertNotNull( $adapter, 'EO adapter should be registered.' );
		$this->assertInstanceOf( Event_Organiser_Adapter::class, $adapter );
	}

	/**
	 * Tests that the post type rewriter rewrites 'event' to 'gatherpress_event'.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_event_post_type_rewrite(): void {
		$rewriter = $this->get_migration_instance()->get_post_type_rewriter();
		$data     = array( 'post_type' => 'event' );
		$result   = $rewriter->rewrite_post_type_on_import( $data );

		$this->assertSame( 'gatherpress_event', $result['post_type'] );
	}

	/**
	 * Tests that the taxonomy rewriter rewrites event-category to gatherpress_topic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_taxonomy_rewrite_event_category(): void {
		$rewriter = $this->get_migration_instance()->get_taxonomy_rewriter();
		$terms    = array(
			array(
				'domain' => 'event-category',
				'slug'   => 'lecture',
				'name'   => 'Lecture',
			),
		);

		$result = $rewriter->rewrite_post_terms_taxonomy( $terms );
		$this->assertSame( 'gatherpress_topic', $result[0]['domain'] );
	}

	/**
	 * Tests that the taxonomy rewriter rewrites event-tag to post_tag.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_taxonomy_rewrite_event_tag(): void {
		$rewriter = $this->get_migration_instance()->get_taxonomy_rewriter();
		$terms    = array(
			array(
				'domain' => 'event-tag',
				'slug'   => 'evening',
				'name'   => 'Evening',
			),
		);

		$result = $rewriter->rewrite_post_terms_taxonomy( $terms );
		$this->assertSame( 'post_tag', $result[0]['domain'] );
	}

	/**
	 * Tests that the meta stasher intercepts EO schedule meta keys.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_meta_intercepts_eo_keys(): void {
		$event_id = $this->create_event( 'Test Event for Meta Stash' );

		$stasher = $this->get_migration_instance()->get_meta_stasher();

		// The stash_meta_on_import() filter returns true to block the meta from saving.
		$result = $stasher->stash_meta_on_import(
			null,
			$event_id,
			'_eventorganiser_schedule_start_datetime',
			'2025-08-28 18:30:00',
			false
		);

		$this->assertTrue( $result, 'stash_meta_on_import should return true to block meta saving.' );

		// Verify the transient was set.
		$stash = get_transient( 'gpei_meta_stash_' . $event_id );
		$this->assertIsArray( $stash );
		$this->assertArrayHasKey( '_eventorganiser_schedule_start_datetime', $stash );
		$this->assertSame( '2025-08-28 18:30:00', $stash['_eventorganiser_schedule_start_datetime'] );

		// Clean up.
		delete_transient( 'gpei_meta_stash_' . $event_id );
	}

	/**
	 * Tests that the meta stasher ignores non-event post types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_meta_ignores_non_event_post_types(): void {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );

		$stasher = $this->get_migration_instance()->get_meta_stasher();

		$result = $stasher->stash_meta_on_import(
			null,
			$post_id,
			'_eventorganiser_schedule_start_datetime',
			'2025-08-28 18:30:00',
			false
		);

		$this->assertNull( $result, 'stash_meta_on_import should pass through for non-event posts.' );
	}

	/**
	 * Tests that the meta stasher ignores non-stash meta keys.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_meta_ignores_non_stash_keys(): void {
		$event_id = $this->create_event( 'Test Event for Non-Stash Key' );

		$stasher = $this->get_migration_instance()->get_meta_stasher();

		$result = $stasher->stash_meta_on_import(
			null,
			$event_id,
			'_some_random_meta_key',
			'random_value',
			false
		);

		$this->assertNull( $result, 'stash_meta_on_import should pass through for unknown keys.' );
	}

	/**
	 * Tests that EO adapter correctly converts datetimes.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_eo_convert_datetimes(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$event_id = $this->create_event( 'Datetime Conversion Test' );
		$adapter  = $this->get_eo_adapter();

		$stash = array(
			'_eventorganiser_schedule_start_datetime' => '2025-08-28 18:30:00',
			'_eventorganiser_schedule_end_datetime'   => '2025-08-28 20:30:00',
		);

		$adapter->convert_datetimes( $event_id, $stash );

		// Verify the datetime was saved by checking the GatherPress Event object.
		$event    = new \GatherPress\Core\Event( $event_id );
		$datetime = $event->get_datetime();

		$this->assertNotEmpty( $datetime, 'GatherPress event should have datetime data.' );
		$this->assertArrayHasKey( 'datetime_start', $datetime );
		$this->assertStringContains( '2025-08-28', $datetime['datetime_start'] );
	}

	/**
	 * Tests that EO convert_datetimes() falls back to start_finish key.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_eo_convert_datetimes_fallback_to_start_finish(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$event_id = $this->create_event( 'Fallback Datetime Test' );
		$adapter  = $this->get_eo_adapter();

		$stash = array(
			'_eventorganiser_schedule_start_datetime' => '2025-09-05 20:00:00',
			'_eventorganiser_schedule_start_finish'   => '2025-09-05 23:00:00',
			// Intentionally omitting _eventorganiser_schedule_end_datetime.
		);

		$adapter->convert_datetimes( $event_id, $stash );

		$event    = new \GatherPress\Core\Event( $event_id );
		$datetime = $event->get_datetime();

		$this->assertNotEmpty( $datetime );
	}

	/**
	 * Tests that EO convert_datetimes() does nothing with empty start.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_eo_convert_datetimes_empty_start_does_nothing(): void {
		$event_id = $this->create_event( 'Empty Start Test' );
		$adapter  = $this->get_eo_adapter();

		// Should not throw or cause errors.
		$adapter->convert_datetimes( $event_id, array() );
		$this->assertTrue( true );
	}

	/**
	 * Tests venue creation from a gatherpress_venue post.
	 *
	 * Verifies that GatherPress creates a shadow taxonomy term
	 * when a gatherpress_venue post is created.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_venue_post_creates_shadow_taxonomy_term(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$this->create_venue( 'Integration Test Venue', 'integration-test-venue' );

		// GatherPress should have created a shadow term.
		$expected_slug = '_integration-test-venue';
		$term          = get_term_by( 'slug', $expected_slug, '_gatherpress_venue' );

		$this->assertNotFalse( $term, 'Shadow taxonomy term should exist after venue creation.' );
		$this->assertSame( $expected_slug, $term->slug );
	}

	/**
	 * Tests venue linking via the Datetime Helper trait's link_venue() method.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_link_venue_assigns_shadow_taxonomy_term(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$venue_id = $this->create_venue( 'Linkable Venue', 'linkable-venue' );
		$event_id = $this->create_event( 'Event to Link' );

		$adapter = $this->get_eo_adapter();
		$adapter->link_venue( $event_id, $venue_id );

		$terms = wp_get_object_terms( $event_id, '_gatherpress_venue' );
		$this->assertNotEmpty( $terms, 'Event should have a _gatherpress_venue term assigned.' );
		$this->assertSame( '_linkable-venue', $terms[0]->slug );
	}

	/**
	 * Tests that the EO adapter's filter removes event-venue terms from assignments.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_eo_filter_removes_event_venue_terms(): void {
		$adapter = $this->get_eo_adapter();

		$terms = array(
			array(
				'domain' => 'event-venue',
				'slug'   => 'some-venue',
				'name'   => 'Some Venue',
			),
			array(
				'domain' => 'event-category',
				'slug'   => 'lecture',
				'name'   => 'Lecture',
			),
		);

		$result = $adapter->tvh_filter_venue_terms( $terms );

		// Only the non-venue term should remain.
		$this->assertCount( 1, $result );
		$this->assertSame( 'event-category', $result[0]['domain'] );
	}

	/**
	 * Tests that the skip post type is registered.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_skip_post_type_is_registered(): void {
		$this->assertTrue(
			post_type_exists( '_gpei_skip' ),
			'The _gpei_skip post type should be registered.'
		);
	}

	/**
	 * Tests that the stash processor correctly identifies the EO adapter.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_process_stashed_meta_finds_eo_adapter(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$event_id = $this->create_event( 'Process Stash Test' );

		// Set up the stash transient with EO data.
		set_transient(
			'gpei_meta_stash_' . $event_id,
			array(
				'_eventorganiser_schedule_start_datetime' => '2025-08-28 18:30:00',
				'_eventorganiser_schedule_end_datetime'   => '2025-08-28 20:30:00',
			),
			HOUR_IN_SECONDS
		);

		$processor = $this->get_migration_instance()->get_stash_processor();
		$processor->process_stashed_meta( $event_id );

		// The transient should be consumed (deleted).
		$stash = get_transient( 'gpei_meta_stash_' . $event_id );
		$this->assertFalse( $stash, 'Stash transient should be deleted after processing.' );
	}

	/**
	 * Helper method to check if a string contains a substring.
	 *
	 * Provides a compatibility layer for PHPUnit versions that
	 * don't have assertStringContainsString().
	 *
	 * @since 0.1.0
	 *
	 * @param string $needle   The substring to search for.
	 * @param string $haystack The string to search in.
	 * @return void
	 */
	protected function assertStringContains( string $needle, string $haystack ): void {
		$this->assertStringContainsString( $needle, $haystack );
	}
}
