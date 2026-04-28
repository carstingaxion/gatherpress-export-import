<?php
/**
 * Integration tests for the ICS Importer.
 *
 * Tests the full ICS import pipeline against a real WordPress
 * environment with GatherPress active, using the EO-export.ics fixture.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.3.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\ICS_Importer;

/**
 * Class ICSImporterIntegrationTest.
 *
 * @since 0.3.0
 * @group ics-importer
 */
class ICSImporterIntegrationTest extends TestCase {

	/**
	 * The ICS Importer instance.
	 *
	 * @since 0.3.0
	 *
	 * @var ICS_Importer
	 */
	private ICS_Importer $importer;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->importer = ICS_Importer::get_instance();
	}

	/**
	 * Cleans up after each test.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Clean up any created events.
		$posts = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// Clean up any created venues.
		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $venues as $venue_id ) {
			wp_delete_post( $venue_id, true );
		}

		parent::tearDown();
	}

	/**
	 * Gets the ICS fixture file path.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	private function get_fixture_path(): string {
		return dirname( __DIR__, 2 ) . '/fixtures/ics/EO-export.ics';
	}

	/**
	 * Invokes a private method on the importer via reflection.
	 *
	 * @since 0.3.0
	 *
	 * @param string $method_name The method name.
	 * @param array  $args        Arguments to pass.
	 * @return mixed
	 */
	private function invoke_private( string $method_name, array $args = [] ) {
		$reflection = new \ReflectionMethod( ICS_Importer::class, $method_name );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $this->importer, $args );
	}

	/**
	 * Parses and creates events from the fixture file.
	 *
	 * @since 0.3.0
	 *
	 * @return int[] Created event post IDs.
	 */
	private function import_fixture(): array {
		$content = file_get_contents( $this->get_fixture_path() );
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );
		return $this->invoke_private( 'create_events', array( $events ) );
	}

	// -----------------------------------------------------------------
	// Event creation
	// -----------------------------------------------------------------

	/**
	 * Tests that importing the fixture creates exactly 3 draft events.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_import_creates_three_draft_events(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$created_ids = $this->import_fixture();
		$this->assertCount( 3, $created_ids, 'Should create exactly 3 events from the EO fixture.' );

		foreach ( $created_ids as $id ) {
			$post = get_post( $id );
			$this->assertSame( 'draft', $post->post_status, 'Imported events should be drafts.' );
			$this->assertSame( 'gatherpress_event', $post->post_type );
		}
	}

	/**
	 * Tests that imported events have the correct titles.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_imported_event_titles(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$created_ids = $this->import_fixture();
		$titles      = array();
		foreach ( $created_ids as $id ) {
			$titles[] = get_post( $id )->post_title;
		}

		$this->assertContains( 'The History of Open Source Software', $titles );
		$this->assertContains( 'Friday Night Live Jazz Session', $titles );
		$this->assertContains( 'WordPress Translation Sprint', $titles );
	}

	/**
	 * Tests that imported events use HTML description (X-ALT-DESC) when available.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_imported_events_use_html_description(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$created_ids = $this->import_fixture();

		// Find the first event.
		$post = get_post( $created_ids[0] );
		$this->assertStringContainsString( '<p>', $post->post_content, 'Should use HTML description from X-ALT-DESC.' );
	}

	// -----------------------------------------------------------------
	// Datetime conversion
	// -----------------------------------------------------------------

	/**
	 * Tests that imported events have datetime data saved via GatherPress.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_imported_events_have_datetime_data(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$created_ids = $this->import_fixture();

		foreach ( $created_ids as $id ) {
			$event    = new \GatherPress\Core\Event( $id );
			$datetime = $event->get_datetime();

			$this->assertNotEmpty(
				$datetime,
				sprintf( 'Event %d should have datetime data.', $id )
			);
			$this->assertArrayHasKey( 'datetime_start', $datetime );
			$this->assertNotEmpty( $datetime['datetime_start'] );
		}
	}

	/**
	 * Tests that the first event has the correct start date.
	 *
	 * The fixture has DTSTART:20250828T183000Z.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_first_event_start_date(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$created_ids = $this->import_fixture();

		// Find the History of Open Source Software event.
		foreach ( $created_ids as $id ) {
			$post = get_post( $id );
			if ( 'The History of Open Source Software' === $post->post_title ) {
				$event    = new \GatherPress\Core\Event( $id );
				$datetime = $event->get_datetime();

				$this->assertStringContainsString( '2025-08-28', $datetime['datetime_start'] );
				return;
			}
		}

		$this->fail( 'Could not find "The History of Open Source Software" event.' );
	}

	/**
	 * Tests that the translation sprint event has correct start date.
	 *
	 * The fixture has DTSTART:20250920T100000Z.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_translation_sprint_start_date(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$created_ids = $this->import_fixture();

		foreach ( $created_ids as $id ) {
			$post = get_post( $id );
			if ( 'WordPress Translation Sprint' === $post->post_title ) {
				$event    = new \GatherPress\Core\Event( $id );
				$datetime = $event->get_datetime();

				$this->assertStringContainsString( '2025-09-20', $datetime['datetime_start'] );
				return;
			}
		}

		$this->fail( 'Could not find "WordPress Translation Sprint" event.' );
	}

	// -----------------------------------------------------------------
	// Category assignment
	// -----------------------------------------------------------------

	/**
	 * Tests that CATEGORIES are assigned as gatherpress_topic terms.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_categories_assigned_as_gatherpress_topic(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( 'gatherpress_topic' ) ) {
			$this->markTestSkipped( 'gatherpress_topic taxonomy not registered.' );
		}

		$created_ids = $this->import_fixture();

		$any_topics = false;
		foreach ( $created_ids as $id ) {
			$topics = wp_get_object_terms( $id, 'gatherpress_topic' );
			if ( ! empty( $topics ) && ! is_wp_error( $topics ) ) {
				$any_topics = true;
				break;
			}
		}

		$this->assertTrue( $any_topics, 'At least one event should have gatherpress_topic terms from CATEGORIES.' );
	}

	/**
	 * Tests that the Lecture category term is created.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_lecture_category_term_created(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( 'gatherpress_topic' ) ) {
			$this->markTestSkipped( 'gatherpress_topic taxonomy not registered.' );
		}

		$this->import_fixture();

		$term = term_exists( 'Lecture', 'gatherpress_topic' );
		$this->assertNotNull( $term, 'Lecture term should be created in gatherpress_topic.' );
	}

	// -----------------------------------------------------------------
	// Venue creation and linking
	// -----------------------------------------------------------------

	/**
	 * Tests that LOCATION values create gatherpress_venue posts.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_locations_create_venue_posts(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->import_fixture();

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 3, $venues, 'Should create 3 venue posts from the 3 unique LOCATIONs.' );

		$titles = wp_list_pluck( $venues, 'post_title' );
		$this->assertContains( 'University Lecture Theatre', $titles );
		$this->assertContains( 'The Jazz Cellar', $titles );
		$this->assertContains( 'Community Hackerspace', $titles );
	}

	/**
	 * Tests that venues have GEO coordinates in venue information JSON.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_venues_have_geo_coordinates(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->import_fixture();

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'any',
				'title'          => 'University Lecture Theatre',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $venues ) ) {
			$this->fail( 'University Lecture Theatre venue not found.' );
		}

		$venue_info = get_post_meta( $venues[0]->ID, 'gatherpress_venue_information', true );
		$decoded    = json_decode( $venue_info, true );

		$this->assertIsArray( $decoded );
		$this->assertSame( '51.522600', $decoded['latitude'] );
		$this->assertSame( '-0.130800', $decoded['longitude'] );
	}

	/**
	 * Tests that events are linked to venues via _gatherpress_venue taxonomy.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_events_linked_to_venues(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy not registered.' );
		}

		$created_ids = $this->import_fixture();

		$linked_count = 0;
		foreach ( $created_ids as $id ) {
			$terms = wp_get_object_terms( $id, '_gatherpress_venue' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				++$linked_count;
			}
		}

		$this->assertSame( 3, $linked_count, 'All 3 events should be linked to venues.' );
	}

	/**
	 * Tests that duplicate LOCATIONs reuse the same venue post.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_duplicate_locations_reuse_venue(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		// Create ICS with two events at the same location.
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\n"
			. "BEGIN:VEVENT\nSUMMARY:Event A\nDTSTART:20250915T090000Z\nLOCATION:Same Venue\nEND:VEVENT\n"
			. "BEGIN:VEVENT\nSUMMARY:Event B\nDTSTART:20250916T090000Z\nLOCATION:Same Venue\nEND:VEVENT\n"
			. "END:VCALENDAR";

		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->invoke_private( 'create_events', array( $events ) );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		$this->assertCount( 1, $venues, 'Two events with same LOCATION should share one venue post.' );
	}

	// -----------------------------------------------------------------
	// Online event link logic
	// -----------------------------------------------------------------

	/**
	 * Tests that URL is NOT saved as online event link when LOCATION is present.
	 *
	 * All fixture events have both LOCATION and URL — the URL should
	 * not be saved as gatherpress_online_event_link.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_url_not_saved_as_online_link_when_location_present(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$created_ids = $this->import_fixture();

		foreach ( $created_ids as $id ) {
			$online_link = get_post_meta( $id, 'gatherpress_online_event_link', true );
			$this->assertEmpty(
				$online_link,
				sprintf( 'Event %d has LOCATION, so URL should NOT be saved as online event link.', $id )
			);
		}
	}

	/**
	 * Tests that URL IS saved as online event link when LOCATION is absent.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_url_saved_as_online_link_when_no_location(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:Online Meetup\nDTSTART:20250915T090000Z\nURL:https://meet.example.com/join\nEND:VEVENT\nEND:VCALENDAR";

		$events     = $this->invoke_private( 'parse_ics', array( $ics ) );
		$created    = $this->invoke_private( 'create_events', array( $events ) );

		$this->assertCount( 1, $created );

		$online_link = get_post_meta( $created[0], 'gatherpress_online_event_link', true );
		$this->assertSame(
			'https://meet.example.com/join',
			$online_link,
			'URL should be saved as online event link when no LOCATION is present.'
		);
	}

	// -----------------------------------------------------------------
	// Edge cases
	// -----------------------------------------------------------------

	/**
	 * Tests that importing an empty events array creates no posts.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_create_events_with_empty_array(): void {
		$created = $this->invoke_private( 'create_events', array( array() ) );
		$this->assertSame( array(), $created );
	}

	/**
	 * Tests importing twice does not duplicate venue posts.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_importing_twice_does_not_duplicate_venues(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$this->import_fixture();
		$this->import_fixture();

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		// Venues should not be duplicated (found by title).
		$venue_titles = wp_list_pluck( $venues, 'post_title' );
		$unique_titles = array_unique( $venue_titles );
		$this->assertCount(
			count( $unique_titles ),
			$venue_titles,
			'Importing twice should not create duplicate venues.'
		);
	}
}