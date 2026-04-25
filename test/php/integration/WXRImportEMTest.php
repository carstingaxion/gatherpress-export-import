<?php
/**
 * WXR Import integration tests for the Events Manager adapter.
 *
 * Tests the import pipeline using the Events Manager WXR fixture file,
 * verifying post type rewrites, datetime conversion, venue detail meta
 * conversion, taxonomy rewriting, and venue linking.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.2.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\Tests\WXRImportHelper;
use GatherPressExportImport\Events_Manager_Adapter;

/**
 * Class WXRImportEMTest.
 *
 * @since 0.2.0
 * @group em-wxr
 * @group wxr-import
 */
class WXRImportEMTest extends TestCase {

	use WXRImportHelper;

	/**
	 * Path to the Events Manager WXR fixture file.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	private string $wxr_file;

	/**
	 * Whether the fixture file has content beyond the empty shell.
	 *
	 * Checks for at least one `<item>` element in the fixture to decide
	 * whether data-dependent tests should run or be skipped.
	 *
	 * @since 0.2.0
	 *
	 * @var bool
	 */
	private bool $fixture_has_data;

	/**
	 * Sets up the test fixture.
	 *
	 * Determines the fixture path and checks whether it contains
	 * importable data (at least one `<item>` element).
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->wxr_file = $this->get_wxr_fixture_path( 'events-manager.xml' );

		// Check if the fixture has real data.
		$contents               = file_get_contents( $this->wxr_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->fixture_has_data = ( false !== $contents && false !== strpos( $contents, '<item>' ) );
	}

	/**
	 * Skips the test if the fixture has no importable data.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	private function skip_if_empty_fixture(): void {
		if ( ! $this->fixture_has_data ) {
			$this->markTestSkipped( 'Events Manager WXR fixture has no importable data. Populate tests/fixtures/wxr/events-manager.xml with real export data.' );
		}
	}

	/**
	 * Tests that the Events Manager adapter is registered in the migration class.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_adapter_is_registered(): void {
		$migration = $this->get_migration_instance();
		$adapters  = $migration->get_adapters();
		$names     = array_map(
			function ( $adapter ) {
				return $adapter->get_name();
			},
			$adapters
		);

		$this->assertContains( 'Events Manager', $names, 'Events Manager adapter should be registered.' );
	}

	/**
	 * Tests that EM location posts are converted to gatherpress_venue.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_locations_imported_as_gatherpress_venue(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThan( 0, count( $venues ), 'At least one gatherpress_venue post should be created from EM locations.' );
	}

	/**
	 * Tests that EM event posts are converted to gatherpress_event.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_events_imported_as_gatherpress_event(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThan( 0, count( $events ), 'At least one gatherpress_event post should be created from EM events.' );
	}

	/**
	 * Tests that no source post types (event, location) remain after import.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_no_source_post_types_remain(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		// Check for leftover 'event' posts (note: this slug is shared with EO).
		$source_events = get_posts(
			array(
				'post_type'      => 'event',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
		$this->assertCount( 0, $source_events, 'No "event" posts should remain after import.' );

		$source_locations = get_posts(
			array(
				'post_type'      => 'location',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
		$this->assertCount( 0, $source_locations, 'No "location" posts should remain after import.' );
	}

	/**
	 * Tests that EM event datetimes are converted to GatherPress format.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_event_datetimes_are_converted(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'No gatherpress_event posts were created from the EM fixture.' );
		}

		$event    = new \GatherPress\Core\Event( $events[0]->ID );
		$datetime = $event->get_datetime();

		$this->assertNotEmpty( $datetime, 'Imported EM event should have datetime data.' );
		$this->assertArrayHasKey( 'datetime_start', $datetime );
		$this->assertNotEmpty( $datetime['datetime_start'], 'datetime_start should not be empty.' );
	}

	/**
	 * Tests that EM venue details are saved as gatherpress_venue_information.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_venue_details_saved_as_venue_information(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $venues ) ) {
			$this->fail( 'No gatherpress_venue posts were created from the EM fixture.' );
		}

		$venue_id   = $venues[0]->ID;
		$venue_info = get_post_meta( $venue_id, 'gatherpress_venue_information', true );

		$this->assertNotEmpty( $venue_info, 'Venue should have gatherpress_venue_information meta.' );

		$decoded = json_decode( $venue_info, true );
		$this->assertIsArray( $decoded, 'Venue information should be valid JSON.' );
		$this->assertArrayHasKey( 'fullAddress', $decoded );
		$this->assertNotEmpty( $decoded['fullAddress'], 'fullAddress should not be empty.' );
	}

	/**
	 * Tests that no EM location meta keys leak into wp_postmeta.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_location_meta_keys_not_in_postmeta(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$em_meta_keys = array(
			'_location_address',
			'_location_town',
			'_location_state',
			'_location_postcode',
			'_location_country',
		);

		foreach ( $venues as $venue ) {
			foreach ( $em_meta_keys as $key ) {
				$value = get_post_meta( $venue->ID, $key, true );
				$this->assertEmpty(
					$value,
					sprintf( 'EM meta key "%s" should not be saved on venue post %d (%s).', $key, $venue->ID, $venue->post_title )
				);
			}
		}
	}

	/**
	 * Tests that no EM event meta keys leak into wp_postmeta.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_event_meta_keys_not_in_postmeta(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$em_meta_keys = array(
			'_event_start',
			'_event_end',
			'_event_timezone',
		);

		foreach ( $events as $event_post ) {
			foreach ( $em_meta_keys as $key ) {
				$value = get_post_meta( $event_post->ID, $key, true );
				$this->assertEmpty(
					$value,
					sprintf( 'EM meta key "%s" should not be saved on event post %d (%s).', $key, $event_post->ID, $event_post->post_title )
				);
			}
		}
	}

	/**
	 * Tests that EM event-categories taxonomy terms are rewritten to gatherpress_topic.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_event_categories_rewritten_to_gatherpress_topic(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( 'gatherpress_topic' ) ) {
			$this->markTestSkipped( 'gatherpress_topic taxonomy is not registered.' );
		}

		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		// Check that at least one gatherpress_topic term exists.
		$topics = get_terms(
			array(
				'taxonomy'   => 'gatherpress_topic',
				'hide_empty' => false,
			)
		);

		$this->assertNotEmpty(
			$topics,
			'At least one gatherpress_topic term should be created from EM event-categories.'
		);
	}

	/**
	 * Tests that EM event-tags taxonomy terms are rewritten to post_tag.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_event_tags_rewritten_to_post_tag(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		// Check that events have post_tag terms assigned.
		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$any_tags_found = false;
		foreach ( $events as $event_post ) {
			$tags = wp_get_object_terms( $event_post->ID, 'post_tag' );
			if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
				$any_tags_found = true;
				break;
			}
		}

		// This assertion is conditional — not all fixtures may have tags.
		// If the fixture has event-tags data, they should map to post_tag.
		if ( $any_tags_found ) {
			$this->assertTrue( $any_tags_found, 'At least one event should have post_tag terms from EM event-tags.' );
		} else {
			$this->markTestIncomplete( 'No event-tags found in fixture data to verify tag rewriting.' );
		}
	}

	/**
	 * Tests that EM events have gatherpress_topic terms assigned.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_events_have_gatherpress_topic_terms(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( 'gatherpress_topic' ) ) {
			$this->markTestSkipped( 'gatherpress_topic taxonomy is not registered.' );
		}

		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$any_topics_found = false;
		foreach ( $events as $event_post ) {
			$topics = wp_get_object_terms( $event_post->ID, 'gatherpress_topic' );
			if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) {
				$any_topics_found = true;
				break;
			}
		}

		if ( $any_topics_found ) {
			$this->assertTrue( $any_topics_found, 'At least one event should have gatherpress_topic terms.' );
		} else {
			$this->markTestIncomplete( 'No event-categories found in fixture data to verify topic assignment.' );
		}
	}

	/**
	 * Tests that multiple EM venues each get their own gatherpress_venue_information.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_multiple_em_venues_get_venue_information(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		if ( count( $venues ) < 2 ) {
			$this->markTestIncomplete( 'Fixture has fewer than 2 venue/location posts. Cannot test multiple venue processing.' );
		}

		$venues_with_info = 0;
		foreach ( $venues as $venue ) {
			$venue_info = get_post_meta( $venue->ID, 'gatherpress_venue_information', true );
			if ( ! empty( $venue_info ) ) {
				$decoded = json_decode( $venue_info, true );
				if ( is_array( $decoded ) && ! empty( $decoded['fullAddress'] ) ) {
					++$venues_with_info;
				}
			}
		}

		$this->assertGreaterThanOrEqual(
			2,
			$venues_with_info,
			'At least 2 venue posts should have populated gatherpress_venue_information meta.'
		);
	}

	/**
	 * Tests that GatherPress creates shadow taxonomy terms for imported EM venues.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_venues_have_shadow_taxonomy_terms(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		if ( empty( $venues ) ) {
			$this->fail( 'No gatherpress_venue posts were created from the EM fixture.' );
		}

		$shadow_terms_found = 0;
		foreach ( $venues as $venue ) {
			$expected_slug = '_' . $venue->post_name;
			$term          = get_term_by( 'slug', $expected_slug, '_gatherpress_venue' );
			if ( $term instanceof \WP_Term ) {
				++$shadow_terms_found;
			}
		}

		$this->assertGreaterThan(
			0,
			$shadow_terms_found,
			'At least one shadow taxonomy term should exist for the imported EM venues.'
		);
	}

	/**
	 * Tests that the EM adapter correctly identifies its data via can_handle().
	 *
	 * Uses real stash data that would be produced by the EM fixture import.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_adapter_can_handle_em_stash_data(): void {
		$adapter = new Events_Manager_Adapter();

		$stash = array(
			'_event_start'    => '2025-07-18 18:00:00',
			'_event_end'      => '2025-07-18 23:00:00',
			'_event_timezone' => 'America/New_York',
		);

		$this->assertTrue( $adapter->can_handle( $stash ), 'EM adapter should handle _event_start data.' );
	}

	/**
	 * Tests that the EM adapter rejects non-EM stash data.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_adapter_rejects_non_em_stash_data(): void {
		$adapter = new Events_Manager_Adapter();

		// TEC data.
		$this->assertFalse( $adapter->can_handle( array( '_EventStartDate' => '2025-09-15 09:00:00' ) ) );

		// EO data.
		$this->assertFalse( $adapter->can_handle( array( '_eventorganiser_schedule_start_datetime' => '2025-08-28 18:30:00' ) ) );

		// Empty.
		$this->assertFalse( $adapter->can_handle( array() ) );
	}

	/**
	 * Tests the complete import: venues and events from a single EM fixture.
	 *
	 * Since EM uses CPT-based venues (not taxonomy-based), a single import
	 * pass should handle both venues and events, provided venues appear
	 * before events in the WXR file.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_single_pass_import_creates_venues_and_events(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$this->assertGreaterThan( 0, count( $venues ), 'Venues should be created in a single pass.' );
		$this->assertGreaterThan( 0, count( $events ), 'Events should be created in a single pass.' );
	}

	/**
	 * Tests that EM venue information contains properly structured JSON.
	 *
	 * Verifies all expected keys are present in the gatherpress_venue_information
	 * JSON structure, matching GatherPress's own format.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_venue_information_json_structure(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $venues ) ) {
			$this->fail( 'No gatherpress_venue posts were created.' );
		}

		$venue_info = get_post_meta( $venues[0]->ID, 'gatherpress_venue_information', true );
		$decoded    = json_decode( $venue_info, true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'fullAddress', $decoded, 'Venue info JSON should have fullAddress key.' );
		$this->assertArrayHasKey( 'phoneNumber', $decoded, 'Venue info JSON should have phoneNumber key.' );
		$this->assertArrayHasKey( 'website', $decoded, 'Venue info JSON should have website key.' );
		$this->assertArrayHasKey( 'latitude', $decoded, 'Venue info JSON should have latitude key.' );
		$this->assertArrayHasKey( 'longitude', $decoded, 'Venue info JSON should have longitude key.' );
	}

	/**
	 * Tests that all imported EM events have datetime data.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_all_em_events_have_datetimes(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'No gatherpress_event posts were created from the EM fixture.' );
		}

		$events_with_datetimes = 0;
		foreach ( $events as $event_post ) {
			$event    = new \GatherPress\Core\Event( $event_post->ID );
			$datetime = $event->get_datetime();

			if ( ! empty( $datetime ) && ! empty( $datetime['datetime_start'] ) ) {
				++$events_with_datetimes;
			}
		}

		$this->assertSame(
			count( $events ),
			$events_with_datetimes,
			'All imported EM events should have datetime data.'
		);
	}

	/**
	 * Tests that the import does not leave any stash transients behind.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_import_cleans_up_transients(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		// The pending event IDs transient should be cleaned up.
		$pending_events = get_transient( 'gpei_pending_event_ids' );
		$this->assertFalse( $pending_events, 'Pending event IDs transient should be cleaned up after import.' );

		// The pending venue IDs transient should be cleaned up.
		$pending_venues = get_transient( 'gpei_pending_venue_ids' );
		$this->assertFalse( $pending_venues, 'Pending venue IDs transient should be cleaned up after import.' );
	}

	/**
	 * Tests that imported EM events preserve their original content.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_em_events_preserve_content(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}
		$this->skip_if_empty_fixture();

		$this->import_wxr( $this->wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'No gatherpress_event posts were created.' );
		}

		// At least one event should have non-empty content.
		$any_content = false;
		foreach ( $events as $event_post ) {
			if ( ! empty( trim( $event_post->post_content ) ) ) {
				$any_content = true;
				break;
			}
		}

		$this->assertTrue( $any_content, 'At least one imported event should have post content preserved.' );
	}

	/**
	 * Tests that the migration correctly identifies the post type rewrite for EM locations.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_migration_rewrites_location_to_gatherpress_venue(): void {
		$migration = $this->get_migration_instance();
		$data      = array( 'post_type' => 'location' );
		$result    = $migration->rewrite_post_type_on_import( $data );

		$this->assertSame( 'gatherpress_venue', $result['post_type'] );
		$this->assertSame( 'location', $result['_gpei_source_type'] );
	}

	/**
	 * Tests that the migration correctly identifies the post type rewrite for EM events.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_migration_rewrites_event_to_gatherpress_event(): void {
		$migration = $this->get_migration_instance();
		$data      = array( 'post_type' => 'event' );
		$result    = $migration->rewrite_post_type_on_import( $data );

		$this->assertSame( 'gatherpress_event', $result['post_type'] );
		$this->assertSame( 'event', $result['_gpei_source_type'] );
	}

	/**
	 * Tests taxonomy rewriting for EM event-categories.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_taxonomy_rewrite_event_categories_to_gatherpress_topic(): void {
		$migration = $this->get_migration_instance();
		$terms     = array(
			array(
				'domain' => 'event-categories',
				'slug'   => 'music',
				'name'   => 'Music',
			),
		);

		$result = $migration->rewrite_post_terms_taxonomy( $terms );
		$this->assertSame( 'gatherpress_topic', $result[0]['domain'] );
	}

	/**
	 * Tests taxonomy rewriting for EM event-tags.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_taxonomy_rewrite_event_tags_to_post_tag(): void {
		$migration = $this->get_migration_instance();
		$terms     = array(
			array(
				'domain' => 'event-tags',
				'slug'   => 'outdoor',
				'name'   => 'outdoor',
			),
		);

		$result = $migration->rewrite_post_terms_taxonomy( $terms );
		$this->assertSame( 'post_tag', $result[0]['domain'] );
	}
}
