<?php
/**
 * Integration tests for the main Migration class.
 *
 * Tests the migration orchestrator against a real WordPress environment
 * with GatherPress active.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.1.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\Migration;

/**
 * Class MigrationIntegrationTest.
 *
 * @since 0.1.0
 * @group migration
 */
class MigrationIntegrationTest extends TestCase {

	/**
	 * Tests that the migration singleton is initialized.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_migration_singleton_exists(): void {
		$migration = $this->get_migration_instance();
		$this->assertInstanceOf( Migration::class, $migration );
	}

	/**
	 * Tests that all six adapters are registered.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_all_adapters_registered(): void {
		$migration = $this->get_migration_instance();
		$adapters  = $migration->get_adapters();
		$this->assertCount( 6, $adapters );
	}

	/**
	 * Tests post type rewriting for all known event types.
	 *
	 * @since 0.1.0
	 *
	 * @dataProvider event_post_type_provider
	 *
	 * @param string $source_type The source event post type.
	 * @return void
	 */
	public function test_event_post_type_rewriting( string $source_type ): void {
		$migration = $this->get_migration_instance();
		$data      = array( 'post_type' => $source_type );
		$result    = $migration->rewrite_post_type_on_import( $data );

		$this->assertSame( 'gatherpress_event', $result['post_type'] );
		$this->assertSame( $source_type, $result['_gpei_source_type'] );
	}

	/**
	 * Data provider for event post type rewriting tests.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array{string}> Test cases.
	 */
	public function event_post_type_provider(): array {
		return array(
			'TEC'            => array( 'tribe_events' ),
			'Events Manager' => array( 'event' ),
			'MEC'            => array( 'mec-events' ),
			'EventON'        => array( 'ajde_events' ),
			'AIOEC'          => array( 'ai1ec_event' ),
		);
	}

	/**
	 * Tests post type rewriting for venue types.
	 *
	 * @since 0.1.0
	 *
	 * @dataProvider venue_post_type_provider
	 *
	 * @param string $source_type The source venue post type.
	 * @return void
	 */
	public function test_venue_post_type_rewriting( string $source_type ): void {
		$migration = $this->get_migration_instance();
		$data      = array( 'post_type' => $source_type );
		$result    = $migration->rewrite_post_type_on_import( $data );

		$this->assertSame( 'gatherpress_venue', $result['post_type'] );
	}

	/**
	 * Data provider for venue post type rewriting tests.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array{string}> Test cases.
	 */
	public function venue_post_type_provider(): array {
		return array(
			'TEC venue'            => array( 'tribe_venue' ),
			'Events Manager venue' => array( 'location' ),
		);
	}

	/**
	 * Tests that standard post types are not rewritten.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_standard_post_types_not_rewritten(): void {
		$migration = $this->get_migration_instance();

		$types_to_test = array( 'post', 'page', 'attachment', 'nav_menu_item', 'wp_template' );

		foreach ( $types_to_test as $type ) {
			$data   = array( 'post_type' => $type );
			$result = $migration->rewrite_post_type_on_import( $data );
			$this->assertSame( $type, $result['post_type'], "Post type '{$type}' should not be rewritten." );
		}
	}

	/**
	 * Tests meta stashing for a gatherpress_event post.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_meta_stashing_for_event(): void {
		$event_id = $this->create_event( 'Meta Stash Integration Test' );

		$migration = $this->get_migration_instance();

		// Stash TEC start date.
		$result = $migration->stash_meta_on_import(
			null,
			$event_id,
			'_EventStartDate',
			'2025-09-15 09:00:00',
			false
		);
		$this->assertTrue( $result );

		// Stash TEC end date.
		$result = $migration->stash_meta_on_import(
			null,
			$event_id,
			'_EventEndDate',
			'2025-09-15 17:00:00',
			false
		);
		$this->assertTrue( $result );

		// Verify both values in the transient.
		$stash = get_transient( 'gpei_meta_stash_' . $event_id );
		$this->assertIsArray( $stash );
		$this->assertSame( '2025-09-15 09:00:00', $stash['_EventStartDate'] );
		$this->assertSame( '2025-09-15 17:00:00', $stash['_EventEndDate'] );

		// Clean up.
		delete_transient( 'gpei_meta_stash_' . $event_id );
	}

	/**
	 * Tests that register_pseudopostmetas merges adapter definitions.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_pseudopostmetas_registration(): void {
		$migration = $this->get_migration_instance();
		$result    = $migration->register_pseudopostmetas( array() );

		// Should contain keys from all adapters.
		$this->assertArrayHasKey( '_EventStartDate', $result );
		$this->assertArrayHasKey( '_eventorganiser_schedule_start_datetime', $result );
		$this->assertArrayHasKey( '_event_start', $result );
		$this->assertArrayHasKey( 'mec_start_date', $result );
		$this->assertArrayHasKey( 'evcal_srow', $result );
	}

	/**
	 * Tests the complete filter_post_meta_on_import flow.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_filter_post_meta_records_pending_event(): void {
		$event_id = $this->create_event( 'Pending Event Test' );

		$migration = $this->get_migration_instance();

		$postmeta = array(
			array(
				'key'   => '_eventorganiser_schedule_start_datetime',
				'value' => '2025-08-28 18:30:00',
			),
		);

		$migration->filter_post_meta_on_import( $postmeta, $event_id, array() );

		$pending = get_transient( 'gpei_pending_event_ids' );
		$this->assertIsArray( $pending );
		$this->assertContains( $event_id, $pending );

		// Clean up.
		delete_transient( 'gpei_pending_event_ids' );
	}
}
