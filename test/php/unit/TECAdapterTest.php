<?php
/**
 * Unit tests for The Events Calendar adapter.
 *
 * Tests adapter configuration methods (post type maps, meta keys,
 * pseudopostmetas, venue detail meta map) in isolation.
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
	}

	/**
	 * Tests that stash meta keys include event datetime and venue detail keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys(): void {
		$keys = $this->adapter->get_stash_meta_keys();

		// Event datetime keys.
		$this->assertContains( '_EventStartDate', $keys );
		$this->assertContains( '_EventEndDate', $keys );
		$this->assertContains( '_EventTimezone', $keys );

		// Venue detail keys.
		$this->assertContains( '_VenueAddress', $keys );
		$this->assertContains( '_VenueCity', $keys );
		$this->assertContains( '_VenueState', $keys );
		$this->assertContains( '_VenueZip', $keys );
		$this->assertContains( '_VenueCountry', $keys );
		$this->assertContains( '_VenuePhone', $keys );
		$this->assertContains( '_VenueURL', $keys );
	}

	/**
	 * Tests that pseudopostmetas include both event and venue keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_pseudopostmetas
	 * @return void
	 */
	public function test_get_pseudopostmetas(): void {
		$pseudometas = $this->adapter->get_pseudopostmetas();

		// Event keys.
		$this->assertArrayHasKey( '_EventStartDate', $pseudometas );
		$this->assertArrayHasKey( '_EventEndDate', $pseudometas );
		$this->assertArrayHasKey( '_EventTimezone', $pseudometas );
		$this->assertArrayHasKey( '_EventVenueID', $pseudometas );

		$this->assertSame( 'gatherpress_event', $pseudometas['_EventStartDate']['post_type'] );

		// Venue detail keys.
		$this->assertArrayHasKey( '_VenueAddress', $pseudometas );
		$this->assertArrayHasKey( '_VenueCity', $pseudometas );
		$this->assertSame( 'gatherpress_venue', $pseudometas['_VenueAddress']['post_type'] );
	}

	/**
	 * Tests that can_handle() returns true for TEC data.
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
		);
		$this->assertTrue( $this->adapter->can_handle( $stash ) );
	}

	/**
	 * Tests that can_handle() returns false for non-TEC data.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_returns_false_for_other_plugins(): void {
		$this->assertFalse( $this->adapter->can_handle( array( '_event_start' => '2025-07-18' ) ) );
		$this->assertFalse( $this->adapter->can_handle( array( 'mec_start_date' => '2025-08-20' ) ) );
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
	}

	/**
	 * Tests that the noop callback does nothing without errors.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::noop_callback
	 * @return void
	 */
	public function test_noop_callback_does_nothing(): void {
		$this->adapter->noop_callback( 123, 'some_value' );
		$this->assertTrue( true );
	}
}
