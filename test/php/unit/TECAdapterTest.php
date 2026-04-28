<?php
/**
 * Unit tests for The Events Calendar adapter.
 *
 * Tests adapter configuration methods (post type maps, meta keys,
 * pseudopostmetas, venue detail meta map, taxonomy map) and data
 * detection in isolation without a running WordPress environment.
 *
 * @package GatherPressExportImport\Tests\Unit
 * @since   0.1.0
 */

use GatherPressExportImport\TEC_Adapter;
use GatherPressExportImport\Source_Adapter;
use GatherPressExportImport\Hookable_Adapter;

/**
 * Class TECAdapterTest.
 *
 * @since 0.1.0
 * @coversDefaultClass TEC_Adapter
 */
class TECAdapterTest extends \WP_UnitTestCase {

	/**
	 * The adapter instance under test.
	 *
	 * @since 0.1.0
	 *
	 * @var TEC_Adapter
	 */
	private TEC_Adapter $adapter;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->adapter = new TEC_Adapter();
	}

	/**
	 * Tests that the adapter returns the correct source plugin name.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_name
	 * @return void
	 */
	public function test_get_name(): void {
		$this->assertSame( 'The Events Calendar (StellarWP)', $this->adapter->get_name() );
	}

	/**
	 * Tests that the adapter implements all required interfaces.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_implements_interfaces(): void {
		$this->assertInstanceOf( Source_Adapter::class, $this->adapter );
		$this->assertInstanceOf( Hookable_Adapter::class, $this->adapter );
	}

	/**
	 * Tests that the adapter maps tribe_events to gatherpress_event.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_event_post_type_map
	 * @return void
	 */
	public function test_get_event_post_type_map(): void {
		$map = $this->adapter->get_event_post_type_map();
		$this->assertArrayHasKey( 'tribe_events', $map );
		$this->assertSame( 'gatherpress_event', $map['tribe_events'] );
		$this->assertCount( 1, $map );
	}

	/**
	 * Tests that the adapter maps tribe_venue to gatherpress_venue.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_venue_post_type_map
	 * @return void
	 */
	public function test_get_venue_post_type_map(): void {
		$map = $this->adapter->get_venue_post_type_map();
		$this->assertArrayHasKey( 'tribe_venue', $map );
		$this->assertSame( 'gatherpress_venue', $map['tribe_venue'] );
		$this->assertCount( 1, $map );
	}

	/**
	 * Tests that stash meta keys include the primary event datetime keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys_includes_event_datetime_keys(): void {
		$keys = $this->adapter->get_stash_meta_keys();

		$this->assertContains( '_EventStartDate', $keys );
		$this->assertContains( '_EventEndDate', $keys );
		$this->assertContains( '_EventTimezone', $keys );
	}

	/**
	 * Tests that stash meta keys include venue detail keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys_includes_venue_detail_keys(): void {
		$keys = $this->adapter->get_stash_meta_keys();

		$this->assertContains( '_VenueAddress', $keys );
		$this->assertContains( '_VenueCity', $keys );
		$this->assertContains( '_VenueState', $keys );
		$this->assertContains( '_VenueStateProvince', $keys );
		$this->assertContains( '_VenueZip', $keys );
		$this->assertContains( '_VenueCountry', $keys );
		$this->assertContains( '_VenuePhone', $keys );
		$this->assertContains( '_VenueURL', $keys );
	}

	/**
	 * Tests that stash meta keys include additional TEC venue meta keys.
	 *
	 * Real TEC WXR exports contain venue meta keys like `_VenueOrigin`,
	 * `_VenueShowMap`, and `_VenueShowMapLink` that must be intercepted
	 * to prevent them from polluting `wp_postmeta` on converted posts.
	 *
	 * @since 0.2.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys_includes_additional_venue_keys(): void {
		$keys = $this->adapter->get_stash_meta_keys();

		$this->assertContains( '_VenueOrigin', $keys );
		$this->assertContains( '_VenueShowMap', $keys );
		$this->assertContains( '_VenueShowMapLink', $keys );
	}

	/**
	 * Tests that stash meta keys include additional TEC event meta keys.
	 *
	 * Real TEC WXR exports contain many internal event meta keys that
	 * must be intercepted to prevent them from being saved as raw post
	 * meta on the converted `gatherpress_event` posts.
	 *
	 * @since 0.2.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys_includes_additional_event_keys(): void {
		$keys = $this->adapter->get_stash_meta_keys();

		$this->assertContains( '_EventStartDateUTC', $keys );
		$this->assertContains( '_EventEndDateUTC', $keys );
		$this->assertContains( '_EventDuration', $keys );
		$this->assertContains( '_EventTimezoneAbbr', $keys );
		$this->assertContains( '_EventCost', $keys );
		$this->assertContains( '_EventCurrencySymbol', $keys );
		$this->assertContains( '_EventCurrencyCode', $keys );
		$this->assertContains( '_EventCurrencyPosition', $keys );
		$this->assertContains( '_EventURL', $keys );
		$this->assertContains( '_EventOrganizerID', $keys );
		$this->assertContains( '_EventAllDay', $keys );
		$this->assertContains( '_EventHideFromUpcoming', $keys );
		$this->assertContains( '_EventOrigin', $keys );
		$this->assertContains( '_EventShowMap', $keys );
		$this->assertContains( '_EventShowMapLink', $keys );
	}

	/**
	 * Tests that stash meta keys contain no duplicates.
	 *
	 * @since 0.2.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys_has_no_duplicates(): void {
		$keys = $this->adapter->get_stash_meta_keys();

		$this->assertSame(
			count( $keys ),
			count( array_unique( $keys ) ),
			'Stash meta keys should contain no duplicates.'
		);
	}

	/**
	 * Tests that pseudopostmetas include the primary event datetime and venue keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_pseudopostmetas
	 * @return void
	 */
	public function test_get_pseudopostmetas_includes_core_keys(): void {
		$pseudometas = $this->adapter->get_pseudopostmetas();

		// Event datetime keys.
		$this->assertArrayHasKey( '_EventStartDate', $pseudometas );
		$this->assertArrayHasKey( '_EventEndDate', $pseudometas );
		$this->assertArrayHasKey( '_EventTimezone', $pseudometas );
		$this->assertArrayHasKey( '_EventVenueID', $pseudometas );

		$this->assertSame( 'gatherpress_event', $pseudometas['_EventStartDate']['post_type'] );
		$this->assertSame( 'gatherpress_event', $pseudometas['_EventVenueID']['post_type'] );
		$this->assertIsCallable( $pseudometas['_EventStartDate']['import_callback'] );

		// Venue detail keys.
		$this->assertArrayHasKey( '_VenueAddress', $pseudometas );
		$this->assertArrayHasKey( '_VenueCity', $pseudometas );
		$this->assertArrayHasKey( '_VenueStateProvince', $pseudometas );
		$this->assertSame( 'gatherpress_venue', $pseudometas['_VenueAddress']['post_type'] );
	}

	/**
	 * Tests that pseudopostmetas include additional TEC event meta keys.
	 *
	 * @since 0.2.0
	 *
	 * @covers ::get_pseudopostmetas
	 * @return void
	 */
	public function test_get_pseudopostmetas_includes_additional_event_keys(): void {
		$pseudometas = $this->adapter->get_pseudopostmetas();

		$additional_event_keys = array(
			'_EventStartDateUTC',
			'_EventEndDateUTC',
			'_EventDuration',
			'_EventTimezoneAbbr',
			'_EventCost',
			'_EventCurrencySymbol',
			'_EventCurrencyCode',
			'_EventCurrencyPosition',
			'_EventURL',
			'_EventOrganizerID',
			'_EventAllDay',
			'_EventHideFromUpcoming',
			'_EventOrigin',
			'_EventShowMap',
			'_EventShowMapLink',
		);

		foreach ( $additional_event_keys as $key ) {
			$this->assertArrayHasKey( $key, $pseudometas, "Pseudopostmetas should include {$key}." );
			$this->assertSame( 'gatherpress_event', $pseudometas[ $key ]['post_type'] );
			$this->assertIsCallable( $pseudometas[ $key ]['import_callback'] );
		}
	}

	/**
	 * Tests that pseudopostmetas include additional TEC venue meta keys.
	 *
	 * @since 0.2.0
	 *
	 * @covers ::get_pseudopostmetas
	 * @return void
	 */
	public function test_get_pseudopostmetas_includes_additional_venue_keys(): void {
		$pseudometas = $this->adapter->get_pseudopostmetas();

		$this->assertArrayHasKey( '_VenueOrigin', $pseudometas );
		$this->assertArrayHasKey( '_VenueShowMap', $pseudometas );
		$this->assertArrayHasKey( '_VenueShowMapLink', $pseudometas );

		$this->assertSame( 'gatherpress_venue', $pseudometas['_VenueOrigin']['post_type'] );
		$this->assertSame( 'gatherpress_venue', $pseudometas['_VenueShowMap']['post_type'] );
		$this->assertSame( 'gatherpress_venue', $pseudometas['_VenueShowMapLink']['post_type'] );
	}

	/**
	 * Tests that can_handle() returns true for TEC stash data.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_with_tec_meta(): void {
		$stash = array(
			'_EventStartDate' => '2025-09-15 09:00:00',
			'_EventEndDate'   => '2025-09-15 17:00:00',
			'_EventTimezone'  => 'America/Los_Angeles',
		);
		$this->assertTrue( $this->adapter->can_handle( $stash ) );
	}

	/**
	 * Tests that can_handle() returns true with minimal TEC data.
	 *
	 * @since 0.2.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_with_minimal_tec_meta(): void {
		$stash = array( '_EventStartDate' => '2025-09-15 09:00:00' );
		$this->assertTrue( $this->adapter->can_handle( $stash ) );
	}

	/**
	 * Tests that can_handle() returns false for non-TEC stash data.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_returns_false_for_other_plugins(): void {
		// Events Manager data.
		$this->assertFalse( $this->adapter->can_handle( array( '_event_start' => '2025-07-18' ) ) );

		// MEC data.
		$this->assertFalse( $this->adapter->can_handle( array( 'mec_start_date' => '2025-08-20' ) ) );

		// Event Organiser data.
		$this->assertFalse( $this->adapter->can_handle( array( '_eventorganiser_schedule_start_datetime' => '2025-08-28 18:30:00' ) ) );

		// EventON data.
		$this->assertFalse( $this->adapter->can_handle( array( 'evcal_srow' => '1725000000' ) ) );

		// Empty stash.
		$this->assertFalse( $this->adapter->can_handle( array() ) );
	}

	/**
	 * Tests that the venue meta key is _EventVenueID.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_venue_meta_key
	 * @return void
	 */
	public function test_get_venue_meta_key(): void {
		$this->assertSame( '_EventVenueID', $this->adapter->get_venue_meta_key() );
	}

	/**
	 * Tests the taxonomy map for TEC.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_taxonomy_map
	 * @return void
	 */
	public function test_get_taxonomy_map(): void {
		$map = $this->adapter->get_taxonomy_map();
		$this->assertArrayHasKey( 'tribe_events_cat', $map );
		$this->assertSame( 'gatherpress_topic', $map['tribe_events_cat'] );
		$this->assertCount( 1, $map );
	}

	/**
	 * Tests that the noop callback is available via the Datetime_Helper trait.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_noop_callback_available_and_does_nothing(): void {
		$this->adapter->noop_callback();
		$this->assertTrue( true );
	}
}