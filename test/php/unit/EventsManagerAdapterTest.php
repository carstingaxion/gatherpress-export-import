<?php
/**
 * Unit tests for the Events Manager adapter.
 *
 * Tests adapter configuration methods (post type maps, meta keys,
 * pseudopostmetas, venue detail meta map) in isolation.
 *
 * @package GatherPressExportImport\Tests\Unit
 * @since   0.1.0
 */

use GatherPressExportImport\Events_Manager_Adapter;
use GatherPressExportImport\Source_Adapter;
use GatherPressExportImport\Hookable_Adapter;

/**
 * Class EventsManagerAdapterTest.
 *
 * @since 0.1.0
 * @coversDefaultClass Events_Manager_Adapter
 */
class EventsManagerAdapterTest extends \WP_UnitTestCase {

	/**
	 * The adapter instance under test.
	 *
	 * @since 0.1.0
	 *
	 * @var Events_Manager_Adapter
	 */
	private Events_Manager_Adapter $adapter;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->adapter = new Events_Manager_Adapter();
	}

	/**
	 * Tests the adapter name.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_name
	 * @return void
	 */
	public function test_get_name(): void {
		$this->assertSame( 'Events Manager', $this->adapter->get_name() );
	}

	/**
	 * Tests that the adapter implements the required interfaces.
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
	 * Tests the event post type map.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_event_post_type_map
	 * @return void
	 */
	public function test_get_event_post_type_map(): void {
		$map = $this->adapter->get_event_post_type_map();
		$this->assertArrayHasKey( 'event', $map );
		$this->assertSame( 'gatherpress_event', $map['event'] );
	}

	/**
	 * Tests the venue post type map.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_venue_post_type_map
	 * @return void
	 */
	public function test_get_venue_post_type_map(): void {
		$map = $this->adapter->get_venue_post_type_map();
		$this->assertArrayHasKey( 'location', $map );
		$this->assertSame( 'gatherpress_venue', $map['location'] );
	}

	/**
	 * Tests that stash meta keys include event, venue link, and venue detail keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys(): void {
		$keys = $this->adapter->get_stash_meta_keys();

		// Event datetime keys.
		$this->assertContains( '_event_start', $keys );
		$this->assertContains( '_event_end', $keys );
		$this->assertContains( '_event_timezone', $keys );

		// Venue link key.
		$this->assertContains( '_location_id', $keys );

		// Venue detail keys.
		$this->assertContains( '_location_address', $keys );
		$this->assertContains( '_location_town', $keys );
		$this->assertContains( '_location_state', $keys );
		$this->assertContains( '_location_postcode', $keys );
		$this->assertContains( '_location_country', $keys );
	}

	/**
	 * Tests pseudopostmeta definitions.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_pseudopostmetas
	 * @return void
	 */
	public function test_get_pseudopostmetas(): void {
		$pseudometas = $this->adapter->get_pseudopostmetas();

		// Event keys.
		$this->assertArrayHasKey( '_event_start', $pseudometas );
		$this->assertArrayHasKey( '_event_end', $pseudometas );
		$this->assertArrayHasKey( '_location_id', $pseudometas );
		$this->assertSame( 'gatherpress_event', $pseudometas['_event_start']['post_type'] );
		$this->assertSame( 'gatherpress_event', $pseudometas['_location_id']['post_type'] );

		// Venue detail keys.
		$this->assertArrayHasKey( '_location_address', $pseudometas );
		$this->assertSame( 'gatherpress_venue', $pseudometas['_location_address']['post_type'] );
	}

	/**
	 * Tests that can_handle detects Events Manager data.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_with_em_meta(): void {
		$stash = array(
			'_event_start' => '2025-07-18 18:00:00',
			'_event_end'   => '2025-07-18 23:00:00',
		);
		$this->assertTrue( $this->adapter->can_handle( $stash ) );
	}

	/**
	 * Tests that can_handle rejects non-EM data.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_returns_false_for_other_plugins(): void {
		$this->assertFalse( $this->adapter->can_handle( array( '_EventStartDate' => '2025-09-15' ) ) );
		$this->assertFalse( $this->adapter->can_handle( array( 'evcal_srow' => '1725000000' ) ) );
		$this->assertFalse( $this->adapter->can_handle( array() ) );
	}

	/**
	 * Tests that the venue meta key is _location_id.
	 *
	 * Events Manager stores the venue reference as `_location_id` post meta
	 * on event posts, containing the original location post ID.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_venue_meta_key
	 * @return void
	 */
	public function test_get_venue_meta_key(): void {
		$this->assertSame( '_location_id', $this->adapter->get_venue_meta_key() );
	}

	/**
	 * Tests the taxonomy map.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_taxonomy_map
	 * @return void
	 */
	public function test_get_taxonomy_map(): void {
		$map = $this->adapter->get_taxonomy_map();

		$this->assertArrayHasKey( 'event-categories', $map );
		$this->assertSame( 'gatherpress_topic', $map['event-categories'] );

		$this->assertArrayHasKey( 'event-tags', $map );
		$this->assertSame( 'post_tag', $map['event-tags'] );
	}

	/**
	 * Tests that the noop callback does nothing.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::noop_callback
	 * @return void
	 */
	public function test_noop_callback_does_nothing(): void {
		$this->adapter->noop_callback( 123, 'value' );
		$this->assertTrue( true );
	}
}
