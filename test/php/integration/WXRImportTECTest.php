<?php
/**
 * WXR Import integration tests for The Events Calendar adapter.
 *
 * Tests the full import pipeline using the TEC WXR fixture file,
 * verifying post type rewrites, datetime conversion, venue detail
 * meta conversion, venue linking, and taxonomy term rewriting.
 *
 * The fixture contains:
 * - 3 venues: Downtown Convention Center (Portland, OR),
 *   Riverside Community Hall (Austin, TX), Innovation Hub Berlin (Berlin, DE)
 * - 4 events: Annual WordPress Summit 2025 (venue 201),
 *   Block Editor Deep Dive Workshop (venue 202),
 *   Community Contributor Day (venue 203),
 *   Evening Networking Mixer (venue 201)
 * - 3 category terms: Conference, Workshop, Meetup (tribe_events_cat)
 * - 3 tag terms: tech, networking, community (post_tag)
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
	 * The fixture contains 3 venues: Downtown Convention Center,
	 * Riverside Community Hall, and Innovation Hub Berlin.
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

		$this->assertCount( 3, $venues, 'Exactly 3 gatherpress_venue posts should be created from TEC venues.' );

		$venue_titles = wp_list_pluck( $venues, 'post_title' );
		$this->assertContains( 'Downtown Convention Center', $venue_titles );
		$this->assertContains( 'Riverside Community Hall', $venue_titles );
		$this->assertContains( 'Innovation Hub Berlin', $venue_titles );
	}

	/**
	 * Tests that TEC events are converted to gatherpress_event.
	 *
	 * The fixture contains 4 events.
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

		$this->assertCount( 4, $events, 'Exactly 4 gatherpress_event posts should be created from TEC events.' );

		$event_titles = wp_list_pluck( $events, 'post_title' );
		$this->assertContains( 'Annual WordPress Summit 2025', $event_titles );
		$this->assertContains( 'Block Editor Deep Dive Workshop', $event_titles );
		$this->assertContains( 'Community Contributor Day', $event_titles );
		$this->assertContains( 'Evening Networking Mixer', $event_titles );
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
	 * Tests that the Annual WordPress Summit 2025 event has the correct start date.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_summit_event_datetime_is_converted(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

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
	 * Tests that all 4 TEC events have datetime data.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_all_tec_events_have_datetimes(): void {
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

		$this->assertCount( 4, $events, 'All 4 events should be created.' );

		$events_with_datetimes = 0;
		foreach ( $events as $event_post ) {
			$event    = new \GatherPress\Core\Event( $event_post->ID );
			$datetime = $event->get_datetime();

			if ( ! empty( $datetime ) && ! empty( $datetime['datetime_start'] ) ) {
				++$events_with_datetimes;
			}
		}

		$this->assertSame(
			4,
			$events_with_datetimes,
			'All 4 imported TEC events should have datetime data.'
		);
	}

	/**
	 * Tests that the Evening Networking Mixer has the correct start date.
	 *
	 * The fixture sets this event to 2025-09-15 19:00:00 — 22:00:00
	 * in the America/Los_Angeles timezone.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_evening_mixer_datetime(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'evening-networking-mixer',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'Event "Evening Networking Mixer" was not created.' );
		}

		$event    = new \GatherPress\Core\Event( $events[0]->ID );
		$datetime = $event->get_datetime();

		$this->assertNotEmpty( $datetime, 'Evening Networking Mixer should have datetime data.' );
		$this->assertStringContainsString( '2025-09-15', $datetime['datetime_start'] );
	}

	/**
	 * Tests that the Annual WordPress Summit 2025 is linked to Downtown Convention Center.
	 *
	 * Fixture: event 301 references venue 201 via _EventVenueID.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_summit_event_linked_to_downtown_convention_center(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

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

		$this->assertNotEmpty( $terms, 'Summit event should be linked to a venue via _gatherpress_venue taxonomy.' );
		$this->assertSame( '_downtown-convention-center', $terms[0]->slug );
	}

	/**
	 * Tests that the Community Contributor Day is linked to Innovation Hub Berlin.
	 *
	 * Fixture: event 303 references venue 203 via _EventVenueID.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_contributor_day_linked_to_innovation_hub(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'community-contributor-day',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'Event "Community Contributor Day" was not created.' );
		}

		$terms = wp_get_object_terms( $events[0]->ID, '_gatherpress_venue' );

		$this->assertNotEmpty( $terms, 'Community Contributor Day should be linked to a venue.' );
		$this->assertSame( '_innovation-hub-berlin', $terms[0]->slug );
	}

	/**
	 * Tests that the Evening Networking Mixer shares a venue with the Summit.
	 *
	 * Both events reference venue ID 201 (Downtown Convention Center).
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_evening_mixer_shares_venue_with_summit(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			$this->markTestSkipped( '_gatherpress_venue taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$mixer_events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'evening-networking-mixer',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $mixer_events ) ) {
			$this->fail( 'Event "Evening Networking Mixer" was not created.' );
		}

		$terms = wp_get_object_terms( $mixer_events[0]->ID, '_gatherpress_venue' );

		$this->assertNotEmpty( $terms, 'Evening Networking Mixer should be linked to a venue.' );
		$this->assertSame(
			'_downtown-convention-center',
			$terms[0]->slug,
			'Evening Networking Mixer should share the Downtown Convention Center venue with the Summit.'
		);
	}

	/**
	 * Tests that the Downtown Convention Center venue has full address details.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_downtown_convention_center_venue_details(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

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
		$this->assertStringContainsString( '123 Main Street', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'Portland', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'OR', $decoded['fullAddress'] );
		$this->assertSame( '+1-503-555-0100', $decoded['phoneNumber'] );
		$this->assertSame( 'https://www.downtownconvention.example.com', $decoded['website'] );
	}

	/**
	 * Tests that the Riverside Community Hall venue has correct details.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_riverside_community_hall_venue_details(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

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
		$this->assertStringContainsString( 'TX', $decoded['fullAddress'] );
	}

	/**
	 * Tests that the Innovation Hub Berlin has partial venue details.
	 *
	 * The fixture venue has no phone, no website, no state, and no ZIP — only
	 * address, city, and country.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_innovation_hub_berlin_partial_venue_details(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$venues = get_posts(
			array(
				'post_type'      => 'gatherpress_venue',
				'post_status'    => 'publish',
				'name'           => 'innovation-hub-berlin',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $venues ) ) {
			$this->fail( 'Venue "Innovation Hub Berlin" was not created.' );
		}

		$venue_id   = $venues[0]->ID;
		$venue_info = get_post_meta( $venue_id, 'gatherpress_venue_information', true );

		$this->assertNotEmpty( $venue_info, 'Venue should have gatherpress_venue_information meta.' );

		$decoded = json_decode( $venue_info, true );
		$this->assertIsArray( $decoded );
		$this->assertStringContainsString( 'Alexanderplatz 1', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'Berlin', $decoded['fullAddress'] );
		$this->assertStringContainsString( 'Germany', $decoded['fullAddress'] );
		$this->assertSame( '', $decoded['phoneNumber'] );
		$this->assertSame( '', $decoded['website'] );
	}

	/**
	 * Tests that all 3 venues have gatherpress_venue_information meta.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_all_tec_venues_have_venue_information(): void {
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

		$this->assertCount( 3, $venues, 'All 3 venues should be created.' );

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

		$this->assertSame(
			3,
			$venues_with_info,
			'All 3 venue posts should have populated gatherpress_venue_information meta.'
		);
	}

	/**
	 * Tests that no TEC venue meta keys leak into wp_postmeta.
	 *
	 * Checks both the venue detail keys (address, city, etc.) and
	 * the additional TEC-internal venue keys (origin, map toggles).
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
			'_VenueStateProvince',
			'_VenueZip',
			'_VenueCountry',
			'_VenuePhone',
			'_VenueURL',
			'_VenueOrigin',
			'_VenueShowMap',
			'_VenueShowMapLink',
		);

		foreach ( $venues as $venue ) {
			foreach ( $tec_meta_keys as $key ) {
				$value = get_post_meta( $venue->ID, $key, true );
				$this->assertEmpty( $value, "TEC meta key '{$key}' should not be saved on venue post {$venue->ID}." );
			}
		}
	}

	/**
	 * Tests that no additional TEC event meta keys leak into wp_postmeta.
	 *
	 * Real TEC WXR exports contain many internal meta keys beyond the
	 * primary datetime and venue ID keys. All should be intercepted.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_tec_event_meta_keys_not_in_postmeta(): void {
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

		$tec_event_meta_keys = array(
			'_EventStartDate',
			'_EventEndDate',
			'_EventTimezone',
			'_EventVenueID',
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

		foreach ( $events as $event_post ) {
			foreach ( $tec_event_meta_keys as $key ) {
				$value = get_post_meta( $event_post->ID, $key, true );
				$this->assertEmpty(
					$value,
					sprintf( "TEC meta key '%s' should not be saved on event post %d (%s).", $key, $event_post->ID, $event_post->post_title )
				);
			}
		}
	}

	/**
	 * Tests that TEC taxonomy terms are rewritten to gatherpress_topic.
	 *
	 * The fixture includes Conference, Workshop, and Meetup category terms.
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

		$conference = term_exists( 'Conference', 'gatherpress_topic' );
		$this->assertNotNull( $conference, 'Conference term should exist in gatherpress_topic taxonomy.' );

		$workshop = term_exists( 'Workshop', 'gatherpress_topic' );
		$this->assertNotNull( $workshop, 'Workshop term should exist in gatherpress_topic taxonomy.' );

		$meetup = term_exists( 'Meetup', 'gatherpress_topic' );
		$this->assertNotNull( $meetup, 'Meetup term should exist in gatherpress_topic taxonomy.' );
	}

	/**
	 * Tests that the Community Contributor Day event has the Meetup category.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_contributor_day_has_meetup_category(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		if ( ! taxonomy_exists( 'gatherpress_topic' ) ) {
			$this->markTestSkipped( 'gatherpress_topic taxonomy is not registered.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'community-contributor-day',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'Event "Community Contributor Day" was not created.' );
		}

		$topics = wp_get_object_terms( $events[0]->ID, 'gatherpress_topic' );
		$this->assertNotEmpty( $topics, 'Community Contributor Day should have gatherpress_topic terms.' );

		$topic_slugs = wp_list_pluck( $topics, 'slug' );
		$this->assertContains( 'meetup', $topic_slugs, 'Community Contributor Day should have the Meetup topic.' );
	}

	/**
	 * Tests that TEC events preserve their post_tag assignments.
	 *
	 * The fixture assigns standard post_tag terms (tech, networking, community)
	 * to events. These should pass through without rewriting.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_tec_events_preserve_post_tags(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		// The Summit event should have 'tech' and 'networking' tags.
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

		$tags = wp_get_object_terms( $events[0]->ID, 'post_tag' );
		$this->assertNotEmpty( $tags, 'Summit event should have post_tag terms.' );

		$tag_slugs = wp_list_pluck( $tags, 'slug' );
		$this->assertContains( 'tech', $tag_slugs );
		$this->assertContains( 'networking', $tag_slugs );
	}

	/**
	 * Tests that _EventURL is not saved as raw post meta (it should be
	 * mapped to gatherpress_online_event_link instead).
	 *
	 * The TEC fixture events have empty _EventURL values, so the online
	 * link meta should not be created. This test verifies the raw TEC key
	 * does not leak.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_event_url_not_in_raw_postmeta(): void {
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

		foreach ( $events as $event_post ) {
			$raw_url = get_post_meta( $event_post->ID, '_EventURL', true );
			$this->assertEmpty(
				$raw_url,
				sprintf( '_EventURL should not be saved as raw meta on event %d (%s).', $event_post->ID, $event_post->post_title )
			);
		}
	}

	/**
	 * Tests that imported events preserve their original content.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function test_tec_events_preserve_content(): void {
		if ( ! $this->is_gatherpress_active() ) {
			$this->markTestSkipped( 'GatherPress is not active.' );
		}

		$wxr_file = $this->get_wxr_fixture_path( 'tec.xml' );
		$this->import_wxr( $wxr_file );

		$events = get_posts(
			array(
				'post_type'      => 'gatherpress_event',
				'post_status'    => 'publish',
				'name'           => 'community-contributor-day',
				'posts_per_page' => 1,
			)
		);

		if ( empty( $events ) ) {
			$this->fail( 'Event "Community Contributor Day" was not created.' );
		}

		$this->assertStringContainsString(
			'contributing to WordPress core',
			$events[0]->post_content,
			'Event content should be preserved during import.'
		);
	}
}
