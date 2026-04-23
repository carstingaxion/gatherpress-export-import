<?php
/**
 * WXR Import integration tests for The Events Calendar adapter.
 *
 * Tests the import pipeline using a real WXR fixture file with
 * TEC venue and event data, verifying post type rewrites, datetime
 * conversion, and venue linking.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.1.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\Tests\WXRImportHelper;

/**
 * Class WXRImportTECTest.
 *
 * @since 0.1.0
 * @group tec-wxr
 * @group wxr-import
 */
class WXRImportTECTest extends TestCase {

	use WXRImportHelper;

	/**
	 * Tests that TEC venue posts are converted to gatherpress_venue.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_venues_imported_as_gatherpress_venue(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThanOrEqual( 2, count( $venues ), 'At least 2 gatherpress_venue posts should be created from TEC venues.' );

		$venue_titles = wp_list_pluck( $venues, 'post_title' );
		$this->assertContains( 'Downtown Convention Center', $venue_titles );
		$this->assertContains( 'Riverside Community Hall', $venue_titles );
	}

	/**
	 * Tests that TEC events are converted to gatherpress_event.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_events_imported_as_gatherpress_event(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThanOrEqual( 2, count( $events ), 'At least 2 gatherpress_event posts should be created from TEC events.' );

		$event_titles = wp_list_pluck( $events, 'post_title' );
		$this->assertContains( 'Annual WordPress Summit 2025', $event_titles );
		$this->assertContains( 'Block Editor Deep Dive Workshop', $event_titles );
	}

	/**
	 * Tests that no tribe_events or tribe_venue posts remain after import.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_no_source_post_types_remain(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		// tribe_events and tribe_venue should not exist as post types.
		$tribe_events = get_posts(
			array(
				'post_type'      => 'tribe_events',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
		$this->assertCount( 0, $tribe_events, 'No tribe_events posts should remain.' );

		$tribe_venues = get_posts(
			array(
				'post_type'      => 'tribe_venue',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
		$this->assertCount( 0, $tribe_venues, 'No tribe_venue posts should remain.' );
	}

	/**
	 * Tests that TEC events have their datetimes converted.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_event_datetimes_are_converted(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		// Find the summit event.
		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'annual-wordpress-summit-2025',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'Event "Annual WordPress Summit 2025" was not created.' );
		}

		$event    = new \GatherPress\Core\Event( $events[0]->ID );
		$datetime = $event->get_datetime();

		$this->assertNotEmpty( $datetime, 'Event should have datetime data after import.' );
		$this->assertArrayHasKey( 'datetime_start', $datetime );
		$this->assertStringContainsString( '2025-09-15', $datetime['datetime_start'] );
	}

	/**
	 * Tests that TEC events are linked to converted venues.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_events_linked_to_venues(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		// Find the summit event.
		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'annual-wordpress-summit-2025',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'Event "Annual WordPress Summit 2025" was not created.' );
		}

		$terms = wp_get_object_terms( $events[0]->ID, '_gatherpress_venue' );

		$this->assertNotEmpty( $terms, 'TEC event should be linked to a venue via _gatherpress_venue taxonomy.' );
		$this->assertSame( '_downtown-convention-center', $terms[0]->slug );
	}

	/**
	 * Tests that TEC venue details are saved as gatherpress_venue_information.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_venue_details_saved_as_venue_information(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		// Find the Downtown Convention Center venue.
		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'name'           => 'downtown-convention-center',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $venues ) ) {
			$this->fail( 'Venue "Downtown Convention Center" was not created.' );
		}

		$venue_id   = $venues[0]->ID;
		$venue_info = get_post_meta( $venue_id, 'gatherpress_venue_information', true );

		$this->assertNotEmpty( $venue_info, 'Venue should have gatherpress_venue_information meta.' );

		$decoded = json_decode( $venue_info, true );
		$this->assertIsArray( $decoded, 'Venue information should be valid JSON.' );
		$this->assertArrayHasKey( 'fullAddress', $decoded );
		$this->assertStringContainsString( '123 Main Street', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'Portland', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'OR', $decoded['fullAddress'] );
		$this->assertSame( '+1-503-555-0100', $decoded['phoneNumber'] );
		$this->assertSame( 'https://www.downtownconvention.example.com', $decoded['website'] );
	}

	/**
	 * Tests that TEC venue details with partial data are handled correctly.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_venue_partial_details_saved(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		// Find the Riverside Community Hall venue (no phone/URL in fixture).
		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'name'           => 'riverside-community-hall',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $venues ) ) {
			$this->fail( 'Venue "Riverside Community Hall" was not created.' );
		}

		$venue_id   = $venues[0]->ID;
		$venue_info = get_post_meta( $venue_id, 'gatherpress_venue_information', true );

		$this->assertNotEmpty( $venue_info, 'Venue should have gatherpress_venue_information meta.' );

		$decoded = json_decode( $venue_info, true );
		$this->assertIsArray( $decoded );
		$this->assertStringContainsString( '456 River Road', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'Austin', $decoded['fullAddress'] );
		$this->assertSame( '', $decoded['phoneNumber'] );
		$this->assertSame( '', $decoded['website'] );
	}

	/**
	 * Tests that no TEC venue meta keys leak into wp_postmeta.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_venue_meta_keys_not_in_postmeta(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$tec_meta_keys = array(
			'_VenueAddress',
			'_VenueCity',
			'_VenueState',
			'_VenueZip',
			'_VenueCountry',
			'_VenuePhone',
			'_VenueURL',
		);

		foreach ( $venues as $venue ) {
			foreach ( $tec_meta_keys as $key ) {
				$value = get_post_meta( $venue->ID, $key, true );
				$this->assertEmpty( $value, "TEC meta key '{$key}' should not be saved on venue post {$venue->ID}." );
			}
		}
	}

	/**
	 * Tests that TEC taxonomy terms are rewritten to GatherPress topics.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_taxonomy_terms_rewritten(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( 'gatherpress_topic' ) ) {
			$this->markTestSkipped( 'gatherpress_topic taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		// Check that gatherpress_topic terms were created.
		$conference = term_exists( 'Conference', 'gatherpress_topic' );
		$this->assertNotNull( $conference, 'Conference term should exist in gatherpress_topic taxonomy.' );

		$workshop = term_exists( 'Workshop', 'gatherpress_topic' );
		$this->assertNotNull( $workshop, 'Workshop term should exist in gatherpress_topic taxonomy.' );
	}
}
