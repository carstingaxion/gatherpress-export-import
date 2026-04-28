<?php
/**
 * Unit tests for the SOC migration classes.
 *
 * Tests adapter registration, type map aggregation, taxonomy map
 * merging, post type rewriting, taxonomy rewriting, and meta stashing
 * via the focused SOC classes accessed through the Migration orchestrator.
 *
 * @package GatherPressExportImport\Tests\Unit
 * @since   0.1.0
 */

use GatherPressExportImport\Migration;
use GatherPressExportImport\Adapter_Registry;
use GatherPressExportImport\Post_Type_Rewriter;
use GatherPressExportImport\Taxonomy_Rewriter;
use GatherPressExportImport\Meta_Stasher;
use GatherPressExportImport\Stash_Processor;

/**
 * Class MigrationClassTest.
 *
 * @since 0.1.0
 * @coversDefaultClass Migration
 */
class MigrationClassTest extends \WP_UnitTestCase {

	/**
	 * The singleton migration instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Migration
	 */
	private Migration $migration;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->migration = Migration::get_instance();
	}

	/**
	 * Tests that the singleton returns the same instance.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_instance
	 * @return void
	 */
	public function test_singleton_returns_same_instance(): void {
		$instance1 = Migration::get_instance();
		$instance2 = Migration::get_instance();
		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Tests that all default adapters are registered.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::get_adapters
	 * @return void
	 */
	public function test_default_adapters_registered(): void {
		$adapters = $this->migration->get_adapters();
		$this->assertNotEmpty( $adapters );

		$names = array_map(
			function ( $adapter ) {
				return $adapter->get_name();
			},
			$adapters
		);

		$this->assertContains( 'The Events Calendar (StellarWP)', $names );
		$this->assertContains( 'Events Manager', $names );
		$this->assertContains( 'Modern Events Calendar', $names );
		$this->assertContains( 'EventON', $names );
		$this->assertContains( 'All-in-One Event Calendar', $names );
		$this->assertContains( 'Event Organiser (Stephen Harris)', $names );
	}

	/**
	 * Tests that SOC class accessors return the correct types.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_soc_class_accessors(): void {
		$this->assertInstanceOf( Adapter_Registry::class, $this->migration->get_registry() );
		$this->assertInstanceOf( Post_Type_Rewriter::class, $this->migration->get_post_type_rewriter() );
		$this->assertInstanceOf( Taxonomy_Rewriter::class, $this->migration->get_taxonomy_rewriter() );
		$this->assertInstanceOf( Meta_Stasher::class, $this->migration->get_meta_stasher() );
		$this->assertInstanceOf( Stash_Processor::class, $this->migration->get_stash_processor() );
	}

	/**
	 * Tests that the event post type map contains expected source types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_event_post_type_map_contains_expected_types(): void {
		$map = $this->migration->get_registry()->get_event_post_type_map();

		$this->assertArrayHasKey( 'tribe_events', $map );
		$this->assertArrayHasKey( 'event', $map );
		$this->assertArrayHasKey( 'mec-events', $map );
		$this->assertArrayHasKey( 'ajde_events', $map );
		$this->assertArrayHasKey( 'ai1ec_event', $map );

		// All should map to gatherpress_event.
		foreach ( $map as $target ) {
			$this->assertSame( 'gatherpress_event', $target );
		}
	}

	/**
	 * Tests that the venue post type map contains expected source types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_venue_post_type_map_contains_expected_types(): void {
		$map = $this->migration->get_registry()->get_venue_post_type_map();

		$this->assertArrayHasKey( 'tribe_venue', $map );
		$this->assertArrayHasKey( 'location', $map );

		foreach ( $map as $target ) {
			$this->assertSame( 'gatherpress_venue', $target );
		}
	}

	/**
	 * Tests that the taxonomy map contains expected mappings.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_taxonomy_map_contains_expected_mappings(): void {
		$map = $this->migration->get_registry()->get_taxonomy_map();

		$this->assertArrayHasKey( 'tribe_events_cat', $map );
		$this->assertSame( 'gatherpress_topic', $map['tribe_events_cat'] );

		$this->assertArrayHasKey( 'event-category', $map );
		$this->assertSame( 'gatherpress_topic', $map['event-category'] );

		$this->assertArrayHasKey( 'event-tag', $map );
		$this->assertSame( 'post_tag', $map['event-tag'] );
	}

	/**
	 * Tests that the stash meta keys include keys from all adapters.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_meta_keys_include_all_adapters(): void {
		$keys = $this->migration->get_registry()->get_stash_meta_keys();

		// TEC keys.
		$this->assertContains( '_EventStartDate', $keys );
		$this->assertContains( '_EventEndDate', $keys );
		$this->assertContains( '_EventTimezone', $keys );

		// EO keys.
		$this->assertContains( '_eventorganiser_schedule_start_datetime', $keys );

		// Events Manager keys.
		$this->assertContains( '_event_start', $keys );

		// MEC keys.
		$this->assertContains( 'mec_start_date', $keys );

		// EventON keys.
		$this->assertContains( 'evcal_srow', $keys );

		// Venue meta keys (TEC).
		$this->assertContains( '_EventVenueID', $keys );
	}

	/**
	 * Tests that rewrite_post_type_on_import() rewrites known event types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_rewrite_event_post_type(): void {
		$rewriter = $this->migration->get_post_type_rewriter();
		$data     = array( 'post_type' => 'tribe_events' );
		$result   = $rewriter->rewrite_post_type_on_import( $data );

		$this->assertSame( 'gatherpress_event', $result['post_type'] );
		$this->assertSame( 'tribe_events', $result['_gpei_source_type'] );
	}

	/**
	 * Tests that rewrite_post_type_on_import() rewrites known venue types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_rewrite_venue_post_type(): void {
		$rewriter = $this->migration->get_post_type_rewriter();
		$data     = array( 'post_type' => 'tribe_venue' );
		$result   = $rewriter->rewrite_post_type_on_import( $data );

		$this->assertSame( 'gatherpress_venue', $result['post_type'] );
		$this->assertSame( 'tribe_venue', $result['_gpei_source_type'] );
	}

	/**
	 * Tests that rewrite_post_type_on_import() does not modify unknown types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_rewrite_does_not_modify_unknown_types(): void {
		$rewriter = $this->migration->get_post_type_rewriter();
		$data     = array( 'post_type' => 'post' );
		$result   = $rewriter->rewrite_post_type_on_import( $data );

		$this->assertSame( 'post', $result['post_type'] );
		$this->assertArrayNotHasKey( '_gpei_source_type', $result );
	}

	/**
	 * Tests taxonomy rewriting in per-post term assignments.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_rewrite_post_terms_taxonomy(): void {
		$rewriter = $this->migration->get_taxonomy_rewriter();
		$terms    = array(
			array(
				'domain' => 'tribe_events_cat',
				'slug'   => 'conference',
				'name'   => 'Conference',
			),
			array(
				'domain' => 'post_tag',
				'slug'   => 'tech',
				'name'   => 'Tech',
			),
		);

		$result = $rewriter->rewrite_post_terms_taxonomy( $terms );

		$this->assertSame( 'gatherpress_topic', $result[0]['domain'] );
		$this->assertSame( 'post_tag', $result[1]['domain'] );
	}
}
