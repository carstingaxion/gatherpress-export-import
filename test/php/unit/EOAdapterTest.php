<?php
/**
 * Unit tests for the Event Organiser adapter.
 *
 * Tests adapter configuration methods (post type maps, meta keys,
 * taxonomy maps, pseudopostmetas) and data detection in isolation
 * without a running WordPress environment.
 *
 * @package TelexGatherpressMigration\Tests\Unit
 * @since   0.1.0
 */

/**
 * Class EOAdapterTest.
 *
 * @since 0.1.0
 * @coversDefaultClass \Telex_GPM_Event_Organiser_Adapter
 */
class EOAdapterTest extends \WP_UnitTestCase {

	/**
	 * The adapter instance under test.
	 *
	 * @since 0.1.0
	 *
	 * @var \Telex_GPM_Event_Organiser_Adapter
	 */
	private \Telex_GPM_Event_Organiser_Adapter $adapter;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->adapter = new \Telex_GPM_Event_Organiser_Adapter();
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
		$this->assertSame( 'Event Organiser (Stephen Harris)', $this->adapter->get_name() );
	}

	/**
	 * Tests that the adapter maps the 'event' post type to 'gatherpress_event'.
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
	 * Tests that the adapter returns an empty venue post type map.
	 *
	 * Event Organiser uses taxonomy terms for venues, not a CPT.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_venue_post_type_map
	 * @return void
	 */
	public function test_get_venue_post_type_map_is_empty(): void {
		$this->assertEmpty( $this->adapter->get_venue_post_type_map() );
	}

	/**
	 * Tests that the adapter returns the correct stash meta keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_stash_meta_keys
	 * @return void
	 */
	public function test_get_stash_meta_keys(): void {
		$keys = $this->adapter->get_stash_meta_keys();
		$this->assertContains( '_eventorganiser_schedule_start_datetime', $keys );
		$this->assertContains( '_eventorganiser_schedule_end_datetime', $keys );
		$this->assertContains( '_eventorganiser_schedule_start_finish', $keys );
		$this->assertContains( '_eventorganiser_schedule_last_start', $keys );
		$this->assertContains( '_eventorganiser_schedule_last_finish', $keys );
		$this->assertCount( 5, $keys );
	}

	/**
	 * Tests that pseudopostmeta definitions include all schedule meta keys.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_pseudopostmetas
	 * @return void
	 */
	public function test_get_pseudopostmetas(): void {
		$pseudometas = $this->adapter->get_pseudopostmetas();
		$this->assertArrayHasKey( '_eventorganiser_schedule_start_datetime', $pseudometas );
		$this->assertArrayHasKey( '_eventorganiser_schedule_end_datetime', $pseudometas );
		$this->assertArrayHasKey( '_eventorganiser_schedule_start_finish', $pseudometas );
		$this->assertArrayHasKey( '_eventorganiser_schedule_last_start', $pseudometas );
		$this->assertArrayHasKey( '_eventorganiser_schedule_last_finish', $pseudometas );

		foreach ( $pseudometas as $key => $config ) {
			$this->assertSame( 'gatherpress_event', $config['post_type'] );
			$this->assertIsCallable( $config['import_callback'] );
		}
	}

	/**
	 * Tests that can_handle() returns true for EO stash data.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_with_eo_meta(): void {
		$stash = array(
			'_eventorganiser_schedule_start_datetime' => '2025-08-28 18:30:00',
			'_eventorganiser_schedule_end_datetime'   => '2025-08-28 20:30:00',
		);

		$this->assertTrue( $this->adapter->can_handle( $stash ) );
	}

	/**
	 * Tests that can_handle() returns false for non-EO stash data.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::can_handle
	 * @return void
	 */
	public function test_can_handle_returns_false_for_other_plugins(): void {
		// TEC data.
		$this->assertFalse( $this->adapter->can_handle( array( '_EventStartDate' => '2025-09-15 09:00:00' ) ) );

		// Events Manager data.
		$this->assertFalse( $this->adapter->can_handle( array( '_event_start' => '2025-07-18 18:00:00' ) ) );

		// Empty stash.
		$this->assertFalse( $this->adapter->can_handle( array() ) );
	}

	/**
	 * Tests that the venue meta key is null.
	 *
	 * Event Organiser uses taxonomy terms, not a meta key for venues.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_venue_meta_key
	 * @return void
	 */
	public function test_get_venue_meta_key_is_null(): void {
		$this->assertNull( $this->adapter->get_venue_meta_key() );
	}

	/**
	 * Tests the taxonomy map for Event Organiser.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_taxonomy_map
	 * @return void
	 */
	public function test_get_taxonomy_map(): void {
		$map = $this->adapter->get_taxonomy_map();

		$this->assertArrayHasKey( 'event-category', $map );
		$this->assertSame( 'gatherpress_topic', $map['event-category'] );

		$this->assertArrayHasKey( 'event-tag', $map );
		$this->assertSame( 'post_tag', $map['event-tag'] );

		// event-venue should NOT be in the taxonomy map.
		$this->assertArrayNotHasKey( 'event-venue', $map );
	}

	/**
	 * Tests the venue taxonomy slug required by the taxonomy venue adapter interface.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_venue_taxonomy_slug
	 * @return void
	 */
	public function test_get_venue_taxonomy_slug(): void {
		$this->assertSame( 'event-venue', $this->adapter->get_venue_taxonomy_slug() );
	}

	/**
	 * Tests the skippable event post types.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_skippable_event_post_types
	 * @return void
	 */
	public function test_get_skippable_event_post_types(): void {
		$types = $this->adapter->get_skippable_event_post_types();
		$this->assertContains( 'event', $types );
		$this->assertCount( 1, $types );
	}

	/**
	 * Tests that the adapter implements all required interfaces.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_implements_interfaces(): void {
		$this->assertInstanceOf( \Telex_GPM_Source_Adapter::class, $this->adapter );
		$this->assertInstanceOf( \Telex_GPM_Hookable_Adapter::class, $this->adapter );
		$this->assertInstanceOf( \Telex_GPM_Taxonomy_Venue_Adapter::class, $this->adapter );
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
		// Should not throw or produce side effects.
		$this->adapter->noop_callback( 123, 'some_value' );
		$this->assertTrue( true ); // If we get here, no error occurred.
	}
}
