<?php
/**
 * WXR Import integration tests for the Event Organiser adapter.
 *
 * Tests the full import pipeline using a real WXR fixture file,
 * verifying that the two-pass strategy works end-to-end:
 * - Pass 1 creates venues from taxonomy terms and skips events
 * - Pass 2 imports events with datetimes and links them to venues
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.1.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\Tests\WXRImportHelper;

/**
 * Class WXRImportEOTest.
 *
 * @since 0.1.0
 * @group eo-wxr
 * @group wxr-import
 */
class WXRImportEOTest extends TestCase {

	use WXRImportHelper;

	/**
	 * Tests that Pass 1 creates gatherpress_venue posts from event-venue terms.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_pass1_creates_venue_posts(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'event-organiser.xml' );
		$this->import_wxr( $wxr_file );

		// Verify venue posts were created.
		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThanOrEqual( 3, count( $venues ), 'At least 3 venue posts should be created in Pass 1.' );

		$venue_slugs = wp_list_pluck( $venues, 'post_name' );
		$this->assertContains( 'university-lecture-theatre', $venue_slugs );
		$this->assertContains( 'the-jazz-cellar', $venue_slugs );
		$this->assertContains( 'community-hackerspace', $venue_slugs );
	}

	/**
	 * Tests that Pass 1 does not create gatherpress_event posts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_pass1_skips_events(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'event-organiser.xml' );
		$this->import_wxr( $wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 0, $events, 'No gatherpress_event posts should exist after Pass 1.' );
	}

	/**
	 * Tests that Pass 1 does not leave skip posts behind.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_pass1_cleans_up_skip_posts(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'event-organiser.xml' );
		$this->import_wxr( $wxr_file );

		$skip_posts = get_posts(
			array(
				'post_type'      => '_gpei_skip',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 0, $skip_posts, 'No _gpei_skip posts should remain after Pass 1 cleanup.' );
	}

	/**
	 * Tests that Pass 1 stores the source venue term slug meta.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_pass1_stores_source_venue_term_slug_meta(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'event-organiser.xml' );
		$this->import_wxr( $wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'meta_key'       => '_gpei_source_venue_term_slug',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThanOrEqual( 3, count( $venues ), 'Venue posts should have _gpei_source_venue_term_slug meta.' );
	}

	/**
	 * Tests the full two-pass import: Pass 1 venues, Pass 2 events.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_two_pass_import_creates_events_with_venues(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'event-organiser.xml' );

		// Pass 1: Create venues.
		$this->import_wxr( $wxr_file );

		$venues_after_pass1 = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$this->assertGreaterThanOrEqual( 3, count( $venues_after_pass1 ), 'Pass 1 should create venue posts.' );

		// Pass 2: Import events.
		$this->import_wxr( $wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThanOrEqual( 3, count( $events ), 'Pass 2 should create gatherpress_event posts.' );

		$event_titles = wp_list_pluck( $events, 'post_title' );
		$this->assertContains( 'The History of Open Source Software', $event_titles );
		$this->assertContains( 'Friday Night Live Jazz Session', $event_titles );
		$this->assertContains( 'WordPress Translation Sprint', $event_titles );
	}

	/**
	 * Tests that Pass 2 links events to venues via _gatherpress_venue taxonomy.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_two_pass_import_links_events_to_venues(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'event-organiser.xml' );

		// Pass 1: Create venues.
		$this->import_wxr( $wxr_file );

		// Pass 2: Import events.
		$this->import_wxr( $wxr_file );

		// Find "The History of Open Source Software" event.
		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'the-history-of-open-source-software',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'Event "The History of Open Source Software" was not created.' );
		}

		$event_id = $events[0]->ID;
		$terms    = wp_get_object_terms( $event_id, '_gatherpress_venue' );

		$this->assertNotEmpty( $terms, 'Event should be linked to a venue via _gatherpress_venue taxonomy.' );
		$this->assertSame( '_university-lecture-theatre', $terms[0]->slug, 'Event should be linked to the correct venue shadow term.' );
	}

	/**
	 * Tests that Pass 2 cleans up the source venue term slug meta after linking.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_two_pass_import_cleans_up_venue_meta(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'event-organiser.xml' );

		// Pass 1 + Pass 2.
		$this->import_wxr( $wxr_file );
		$this->import_wxr( $wxr_file );

		// After successful linking, the meta should be cleaned up.
		$venues_with_meta = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'meta_key'       => '_gpei_source_venue_term_slug',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 0, $venues_with_meta, 'Source venue term slug meta should be cleaned up after successful linking.' );
	}
}
