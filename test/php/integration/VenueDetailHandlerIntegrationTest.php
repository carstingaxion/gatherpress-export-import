<?php
/**
 * Integration tests for the Venue_Detail_Handler trait.
 *
 * Tests the full stash-and-process pipeline for venue detail meta
 * against a real WordPress environment with GatherPress active.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.1.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\TEC_Adapter;
use GatherPressExportImport\Events_Manager_Adapter;

/**
 * Class VenueDetailHandlerIntegrationTest.
 *
 * @since 0.1.0
 * @group venue-detail-handler
 */
class VenueDetailHandlerIntegrationTest extends TestCase {

	/**
	 * Cleans up transients after each test.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		delete_transient( 'gpei_pending_venue_ids' );
		parent::tearDown();
	}

	/**
	 * Tests that TEC venue detail meta is stashed and processed into venue information.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_tec_venue_detail_stash_and_process(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		$venue_id = $this->create_venue( 'TEC Integration Venue', 'tec-integration-venue' );

		// Simulate the importer adding venue meta via add_post_metadata filter.
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueAddress', '123 Main Street', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueCity', 'Portland', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueState', 'OR', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueZip', '97201', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueCountry', 'United States', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenuePhone', '+1-503-555-0100', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueURL', 'https://example.com', false );

		// Process the stashed meta (simulates import_end).
		$adapter->vdh_process_stashed_venue_meta();

		// Verify the venue information meta.
		$venue_info = get_post_meta( $venue_id, 'gatherpress_venue_information', true );
		$this->assertNotEmpty( $venue_info );

		$decoded = json_decode( $venue_info, true );
		$this->assertIsArray( $decoded );
		$this->assertStringContainsString( '123 Main Street', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'Portland', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'OR', $decoded['fullAddress'] );
		$this->assertStringContainsString( '97201', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'United States', $decoded['fullAddress'] );
		$this->assertSame( '+1-503-555-0100', $decoded['phoneNumber'] );
		$this->assertSame( 'https://example.com', $decoded['website'] );
		$this->assertSame( '', $decoded['latitude'] );
		$this->assertSame( '', $decoded['longitude'] );
	}

	/**
	 * Tests that Events Manager venue detail meta is stashed and processed.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_em_venue_detail_stash_and_process(): void {
		$adapter = new Events_Manager_Adapter();
		$adapter->setup_import_hooks();

		$venue_id = $this->create_venue( 'EM Integration Venue', 'em-integration-venue' );

		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_location_address', '830 5th Ave', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_location_town', 'New York', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_location_state', 'NY', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_location_postcode', '10065', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_location_country', 'US', false );

		$adapter->vdh_process_stashed_venue_meta();

		$venue_info = get_post_meta( $venue_id, 'gatherpress_venue_information', true );
		$this->assertNotEmpty( $venue_info );

		$decoded = json_decode( $venue_info, true );
		$this->assertIsArray( $decoded );
		$this->assertStringContainsString( '830 5th Ave', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'New York', $decoded['fullAddress'] );
		$this->assertSame( '', $decoded['phoneNumber'] );
		$this->assertSame( '', $decoded['website'] );
	}

	/**
	 * Tests venue details with partial data (no phone, no website).
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_partial_venue_details(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		$venue_id = $this->create_venue( 'Partial Venue', 'partial-venue' );

		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueAddress', 'Alexanderplatz 1', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueCity', 'Berlin', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueCountry', 'Germany', false );

		$adapter->vdh_process_stashed_venue_meta();

		$decoded = json_decode( get_post_meta( $venue_id, 'gatherpress_venue_information', true ), true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'Alexanderplatz 1, Berlin, Germany', $decoded['fullAddress'] );
		$this->assertSame( '', $decoded['phoneNumber'] );
		$this->assertSame( '', $decoded['website'] );
	}

	/**
	 * Tests multiple venues processed in a single import run.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_multiple_venues_processed(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		$venue1_id = $this->create_venue( 'Venue Alpha', 'venue-alpha' );
		$venue2_id = $this->create_venue( 'Venue Beta', 'venue-beta' );

		$adapter->vdh_stash_venue_meta_on_import( null, $venue1_id, '_VenueAddress', '100 Alpha St', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue1_id, '_VenueCity', 'Alpha City', false );

		$adapter->vdh_stash_venue_meta_on_import( null, $venue2_id, '_VenueAddress', '200 Beta Blvd', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue2_id, '_VenueCity', 'Beta Town', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue2_id, '_VenuePhone', '+1-555-BETA', false );

		$adapter->vdh_process_stashed_venue_meta();

		// Venue Alpha.
		$decoded1 = json_decode( get_post_meta( $venue1_id, 'gatherpress_venue_information', true ), true );
		$this->assertSame( '100 Alpha St, Alpha City', $decoded1['fullAddress'] );
		$this->assertSame( '', $decoded1['phoneNumber'] );

		// Venue Beta.
		$decoded2 = json_decode( get_post_meta( $venue2_id, 'gatherpress_venue_information', true ), true );
		$this->assertSame( '200 Beta Blvd, Beta Town', $decoded2['fullAddress'] );
		$this->assertSame( '+1-555-BETA', $decoded2['phoneNumber'] );
	}

	/**
	 * Tests that stash does not intercept non-venue-detail meta on venue posts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_non_venue_detail_meta_passes_through(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		$venue_id = $this->create_venue( 'Pass Through Venue', 'pass-through-venue' );

		// A meta key that is NOT in the venue detail map should pass through.
		$result = $adapter->vdh_stash_venue_meta_on_import(
			null,
			$venue_id,
			'_some_random_meta',
			'random_value',
			false
		);

		$this->assertNull( $result, 'Non-venue-detail meta should pass through.' );
	}

	/**
	 * Tests that stash does not intercept venue detail keys on event posts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_venue_detail_meta_ignored_on_event_posts(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		$event_id = $this->create_event( 'Some Event' );

		$result = $adapter->vdh_stash_venue_meta_on_import(
			null,
			$event_id,
			'_VenueAddress',
			'123 Main Street',
			false
		);

		$this->assertNull( $result, 'Venue detail meta should not be intercepted on event posts.' );
	}

	/**
	 * Tests that the process method cleans up transients.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_process_cleans_up_transients(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		$venue_id = $this->create_venue( 'Cleanup Venue', 'cleanup-venue' );

		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueAddress', '123 Main St', false );

		// Verify transients exist before processing.
		$this->assertNotFalse( get_transient( 'gpei_venue_meta_stash_' . $venue_id ) );
		$this->assertNotFalse( get_transient( 'gpei_pending_venue_ids' ) );

		$adapter->vdh_process_stashed_venue_meta();

		// Transients should be cleaned up after processing.
		$this->assertFalse( get_transient( 'gpei_venue_meta_stash_' . $venue_id ) );
		$this->assertFalse( get_transient( 'gpei_pending_venue_ids' ) );
	}

	/**
	 * Tests that process does nothing when there are no pending venues.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_process_with_no_pending_venues(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		// Should not throw or cause errors.
		$adapter->vdh_process_stashed_venue_meta();
		$this->assertTrue( true );
	}

	/**
	 * Tests that TEC venue meta keys do not leak into wp_postmeta.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_venue_meta_keys_blocked_from_postmeta(): void {
		$adapter = new TEC_Adapter();
		$adapter->setup_import_hooks();

		$venue_id = $this->create_venue( 'No Leak Venue', 'no-leak-venue' );

		// Stash all TEC venue meta.
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueAddress', '123 Main St', false );
		$adapter->vdh_stash_venue_meta_on_import( null, $venue_id, '_VenueCity', 'Portland', false );

		// Process.
		$adapter->vdh_process_stashed_venue_meta();

		// The original TEC meta keys should NOT be in wp_postmeta.
		$this->assertEmpty( get_post_meta( $venue_id, '_VenueAddress', true ) );
		$this->assertEmpty( get_post_meta( $venue_id, '_VenueCity', true ) );

		// But gatherpress_venue_information should be present.
		$this->assertNotEmpty( get_post_meta( $venue_id, 'gatherpress_venue_information', true ) );
	}
}
