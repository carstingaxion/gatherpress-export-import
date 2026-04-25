<?php
/**
 * TEC demo data import script for WordPress Playground.
 *
 * Creates sample events, venues, categories, and tags for The Events
 * Calendar migration testing using TEC's own API when available, falling
 * back to direct `wp_insert_post()` with all required meta keys.
 *
 * ## TEC's Internal Data Requirements
 *
 * The Events Calendar stores event metadata as standard WordPress post
 * meta on `tribe_events` and `tribe_venue` post types. However, TEC's
 * admin UI, REST API, and views expect certain meta keys beyond the
 * obvious `_EventStartDate` / `_EventEndDate`:
 *
 * ### Required event meta for TEC to function:
 * - `_EventStartDate`      — Local start datetime (Y-m-d H:i:s)
 * - `_EventEndDate`        — Local end datetime (Y-m-d H:i:s)
 * - `_EventStartDateUTC`   — UTC start datetime (Y-m-d H:i:s)
 * - `_EventEndDateUTC`     — UTC end datetime (Y-m-d H:i:s)
 * - `_EventDuration`       — Duration in seconds (int)
 * - `_EventTimezone`       — PHP timezone string
 * - `_EventTimezoneAbbr`   — Timezone abbreviation (e.g., "PDT")
 * - `_EventVenueID`        — Venue post ID reference
 * - `_EventCost`           — Event cost (empty string for free)
 * - `_EventCurrencySymbol` — Currency symbol (e.g., "$")
 * - `_EventCurrencyCode`   — Currency code (e.g., "USD")
 *
 * ### Preferred approach: TEC's ORM API
 *
 * TEC v5+ provides an ORM layer via `tribe_events()` and `tribe_venues()`
 * that handles all internal meta, caching, and table population. When TEC
 * is active, we use this API. When it's not (e.g., if the plugin failed
 * to activate properly), we fall back to manual `wp_insert_post()` with
 * all required meta keys set explicitly.
 *
 * @see https://developer.developer.developer.developer/ — TEC developer docs (placeholder)
 * @see https://developer.developer.developer.developer/apis/events/ — TEC Events API
 * @see tribe_events()       — TEC Event Repository (ORM)
 * @see tribe_venues()       — TEC Venue Repository (ORM)
 * @see Tribe__Events__API   — Legacy TEC API class
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

/**
 * Converts a local datetime string to UTC using a given timezone.
 *
 * @param string $local_datetime Local datetime in 'Y-m-d H:i:s' format.
 * @param string $timezone       PHP timezone string.
 * @return string UTC datetime in 'Y-m-d H:i:s' format.
 */
function gpei_local_to_utc( $local_datetime, $timezone ) {
	try {
		$tz  = new DateTimeZone( $timezone );
		$utc = new DateTimeZone( 'UTC' );
		$dt  = new DateTime( $local_datetime, $tz );
		$dt->setTimezone( $utc );
		return $dt->format( 'Y-m-d H:i:s' );
	} catch ( Exception $e ) {
		return $local_datetime;
	}
}

/**
 * Gets a timezone abbreviation for a given timezone and datetime.
 *
 * @param string $timezone       PHP timezone string.
 * @param string $local_datetime Local datetime in 'Y-m-d H:i:s' format.
 * @return string Timezone abbreviation (e.g., "PDT", "CDT", "CET").
 */
function gpei_get_timezone_abbr( $timezone, $local_datetime ) {
	try {
		$tz = new DateTimeZone( $timezone );
		$dt = new DateTime( $local_datetime, $tz );
		return $dt->format( 'T' );
	} catch ( Exception $e ) {
		return 'UTC';
	}
}

/**
 * Calculates the duration in seconds between two datetime strings.
 *
 * @param string $start Start datetime in 'Y-m-d H:i:s' format.
 * @param string $end   End datetime in 'Y-m-d H:i:s' format.
 * @return int Duration in seconds.
 */
function gpei_calc_duration( $start, $end ) {
	$start_ts = strtotime( $start );
	$end_ts   = strtotime( $end );
	if ( false === $start_ts || false === $end_ts ) {
		return 0;
	}
	return max( 0, $end_ts - $start_ts );
}

/*
 * -------------------------------------------------------------------------
 * Strategy: Detect whether TEC's API is available and choose the approach.
 * -------------------------------------------------------------------------
 *
 * TEC v5+ provides `Tribe__Events__API::createVenue()` and
 * `Tribe__Events__API::createEvent()` which handle all internal setup.
 * If those aren't available, we use the newer ORM approach via
 * `tribe_venues()->set_args()->create()` / `tribe_events()->...`.
 * As a final fallback, we use wp_insert_post() with explicit meta.
 */
$use_tec_api   = class_exists( 'Tribe__Events__API' )
	&& method_exists( 'Tribe__Events__API', 'createVenue' )
	&& method_exists( 'Tribe__Events__API', 'createEvent' );

$use_tec_orm   = ! $use_tec_api
	&& function_exists( 'tribe_venues' )
	&& function_exists( 'tribe_events' );

error_log( 'GPEI-TEC: TEC API available: ' . ( $use_tec_api ? 'YES' : 'NO' ) );
error_log( 'GPEI-TEC: TEC ORM available: ' . ( $use_tec_orm ? 'YES' : 'NO' ) );

// Ensure taxonomies exist for tax_input to work.
if ( ! taxonomy_exists( 'tribe_events_cat' ) ) {
	register_taxonomy( 'tribe_events_cat', 'tribe_events', array(
		'label'  => 'Events Category',
		'public' => true,
	) );
}

// -------------------------------------------------------------------------
// Create event categories.
// -------------------------------------------------------------------------
if ( ! term_exists( 'Conference', 'tribe_events_cat' ) ) {
	wp_insert_term( 'Conference', 'tribe_events_cat', array( 'slug' => 'conference' ) );
}
if ( ! term_exists( 'Workshop', 'tribe_events_cat' ) ) {
	wp_insert_term( 'Workshop', 'tribe_events_cat', array( 'slug' => 'workshop' ) );
}
if ( ! term_exists( 'Meetup', 'tribe_events_cat' ) ) {
	wp_insert_term( 'Meetup', 'tribe_events_cat', array( 'slug' => 'meetup' ) );
}

// Create event tags.
wp_insert_term( 'networking', 'post_tag' );
wp_insert_term( 'tech', 'post_tag' );
wp_insert_term( 'community', 'post_tag' );

// -------------------------------------------------------------------------
// Create venues.
// -------------------------------------------------------------------------

$venue_data = array(
	array(
		'title'   => 'Downtown Convention Center',
		'address' => '123 Main Street',
		'city'    => 'Portland',
		'state'   => 'OR',
		'zip'     => '97201',
		'country' => 'United States',
		'phone'   => '+1-503-555-0100',
		'url'     => 'https://www.downtownconvention.example.com',
	),
	array(
		'title'   => 'Riverside Community Hall',
		'address' => '456 River Road',
		'city'    => 'Austin',
		'state'   => 'TX',
		'zip'     => '73301',
		'country' => 'United States',
		'phone'   => '+1-512-555-0200',
		'url'     => '',
	),
	array(
		'title'   => 'Innovation Hub Berlin',
		'address' => 'Alexanderplatz 1',
		'city'    => 'Berlin',
		'state'   => '',
		'zip'     => '',
		'country' => 'Germany',
		'phone'   => '',
		'url'     => '',
	),
);

$venue_ids = array();

foreach ( $venue_data as $v ) {
	if ( $use_tec_api ) {
		/*
		 * Use TEC's own API to create venues.
		 *
		 * `Tribe__Events__API::createVenue()` accepts an associative array
		 * and handles all internal meta key population including:
		 * - _VenueAddress, _VenueCity, _VenueState, etc.
		 * - _VenueShowMap, _VenueShowMapLink
		 * - Internal lookup caching
		 *
		 * @see Tribe__Events__API::createVenue() in
		 *      the-events-calendar/src/Tribe/Events/API.php
		 */
		$venue_id = Tribe__Events__API::createVenue( array(
			'Venue'        => $v['title'],
			'Address'      => $v['address'],
			'City'         => $v['city'],
			'State'        => $v['state'],
			'Zip'          => $v['zip'],
			'Country'      => $v['country'],
			'Phone'        => $v['phone'],
			'URL'          => $v['url'],
			'ShowMap'      => true,
			'ShowMapLink'  => true,
		) );

		error_log( 'GPEI-TEC: Created venue via API: ' . $v['title'] . ' (ID: ' . $venue_id . ')' );

	} elseif ( $use_tec_orm ) {
		/*
		 * Use TEC's ORM repository to create venues.
		 *
		 * The ORM approach (TEC v5.5+) provides a fluent interface:
		 *   tribe_venues()->set_args( [...] )->create()
		 *
		 * @see Tribe\Events\Models\Post_Types\Venue
		 */
		$venue_id = tribe_venues()->set_args( array(
			'title'   => $v['title'],
			'status'  => 'publish',
			'address' => $v['address'],
			'city'    => $v['city'],
			'state'   => $v['state'],
			'zip'     => $v['zip'],
			'country' => $v['country'],
			'phone'   => $v['phone'],
			'url'     => $v['url'],
		) )->create()->ID;

		error_log( 'GPEI-TEC: Created venue via ORM: ' . $v['title'] . ' (ID: ' . $venue_id . ')' );

	} else {
		/*
		 * Fallback: Create venue via wp_insert_post() with all required
		 * TEC meta keys explicitly set.
		 *
		 * This ensures the venue is visible in TEC's admin UI and
		 * properly referenced by events, even without TEC's API classes.
		 */
		$venue_id = wp_insert_post( array(
			'post_title'  => $v['title'],
			'post_type'   => 'tribe_venue',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_VenueAddress'     => $v['address'],
				'_VenueCity'        => $v['city'],
				'_VenueState'       => $v['state'],
				'_VenueZip'         => $v['zip'],
				'_VenueCountry'     => $v['country'],
				'_VenuePhone'       => $v['phone'],
				'_VenueURL'         => $v['url'],
				'_VenueShowMap'     => '1',
				'_VenueShowMapLink' => '1',
			),
		) );

		error_log( 'GPEI-TEC: Created venue via wp_insert_post: ' . $v['title'] . ' (ID: ' . $venue_id . ')' );
	}

	$venue_ids[] = $venue_id;
}

// -------------------------------------------------------------------------
// Create events.
// -------------------------------------------------------------------------

$event_data = array(
	array(
		'title'      => 'Annual WordPress Summit 2025',
		'content'    => 'Join us for the biggest WordPress event of the year featuring talks, workshops, and networking opportunities.',
		'start'      => '2025-09-15 09:00:00',
		'end'        => '2025-09-15 17:00:00',
		'timezone'   => 'America/Los_Angeles',
		'venue_idx'  => 0,
		'categories' => array( 'conference' ),
		'tags'       => array( 'tech', 'networking' ),
	),
	array(
		'title'      => 'Block Editor Deep Dive Workshop',
		'content'    => 'A hands-on workshop exploring advanced Gutenberg block development techniques and full site editing.',
		'start'      => '2025-10-03 14:00:00',
		'end'        => '2025-10-03 18:00:00',
		'timezone'   => 'America/Chicago',
		'venue_idx'  => 1,
		'categories' => array( 'workshop' ),
		'tags'       => array( 'tech' ),
	),
	array(
		'title'      => 'Community Contributor Day',
		'content'    => 'A full day dedicated to contributing to WordPress core, plugins, themes, and documentation.',
		'start'      => '2025-11-20 10:00:00',
		'end'        => '2025-11-20 16:00:00',
		'timezone'   => 'Europe/Berlin',
		'venue_idx'  => 2,
		'categories' => array( 'meetup' ),
		'tags'       => array( 'community' ),
	),
	array(
		'title'      => 'Evening Networking Mixer',
		'content'    => 'Casual evening meetup for WordPress professionals and enthusiasts. Appetizers and drinks provided.',
		'start'      => '2025-09-15 19:00:00',
		'end'        => '2025-09-15 22:00:00',
		'timezone'   => 'America/Los_Angeles',
		'venue_idx'  => 0,
		'categories' => array( 'meetup' ),
		'tags'       => array( 'networking', 'community' ),
	),
);

foreach ( $event_data as $e ) {
	$venue_id = isset( $venue_ids[ $e['venue_idx'] ] ) ? $venue_ids[ $e['venue_idx'] ] : 0;

	if ( $use_tec_api ) {
		/*
		 * Use TEC's own API to create events.
		 *
		 * `Tribe__Events__API::createEvent()` accepts an associative array
		 * and handles all internal meta key population including:
		 * - _EventStartDate, _EventEndDate (local)
		 * - _EventStartDateUTC, _EventEndDateUTC (UTC)
		 * - _EventDuration (calculated)
		 * - _EventTimezone, _EventTimezoneAbbr
		 * - _EventVenueID
		 * - Internal date tables and caching
		 *
		 * @see Tribe__Events__API::createEvent() in
		 *      the-events-calendar/src/Tribe/Events/API.php
		 */
		$event_args = array(
			'post_title'   => $e['title'],
			'post_content' => $e['content'],
			'post_status'  => 'publish',
			'EventStartDate'     => substr( $e['start'], 0, 10 ),
			'EventStartHour'     => date( 'h', strtotime( $e['start'] ) ),
			'EventStartMinute'   => date( 'i', strtotime( $e['start'] ) ),
			'EventStartMeridian' => date( 'A', strtotime( $e['start'] ) ),
			'EventEndDate'       => substr( $e['end'], 0, 10 ),
			'EventEndHour'       => date( 'h', strtotime( $e['end'] ) ),
			'EventEndMinute'     => date( 'i', strtotime( $e['end'] ) ),
			'EventEndMeridian'   => date( 'A', strtotime( $e['end'] ) ),
			'EventTimezone'      => $e['timezone'],
			'Venue'              => array( 'VenueID' => $venue_id ),
		);

		$event_id = Tribe__Events__API::createEvent( $event_args );

		if ( $event_id ) {
			wp_set_object_terms( $event_id, $e['categories'], 'tribe_events_cat' );
			wp_set_object_terms( $event_id, $e['tags'], 'post_tag' );
		}

		error_log( 'GPEI-TEC: Created event via API: ' . $e['title'] . ' (ID: ' . $event_id . ')' );

	} elseif ( $use_tec_orm ) {
		/*
		 * Use TEC's ORM repository to create events.
		 *
		 * @see Tribe\Events\Models\Post_Types\Event
		 */
		$event_post = tribe_events()->set_args( array(
			'title'      => $e['title'],
			'content'    => $e['content'],
			'status'     => 'publish',
			'start_date' => $e['start'],
			'end_date'   => $e['end'],
			'timezone'   => $e['timezone'],
			'venue'      => $venue_id,
		) )->create();

		if ( $event_post ) {
			$event_id = $event_post->ID;
			wp_set_object_terms( $event_id, $e['categories'], 'tribe_events_cat' );
			wp_set_object_terms( $event_id, $e['tags'], 'post_tag' );
		}

		error_log( 'GPEI-TEC: Created event via ORM: ' . $e['title'] . ' (ID: ' . ( $event_id ?? 0 ) . ')' );

	} else {
		/*
		 * Fallback: Create event via wp_insert_post() with all required
		 * TEC meta keys explicitly set.
		 *
		 * TEC checks for these meta keys in various places:
		 * - The events list/calendar views read _EventStartDate and
		 *   _EventEndDate for display and ordering.
		 * - The admin "Events" list table sorts by _EventStartDate.
		 * - TEC's REST API v2 reads _EventStartDateUTC / _EventEndDateUTC.
		 * - The event detail view reads _EventDuration and _EventTimezone.
		 *
		 * Without the UTC variants and duration, events may appear but
		 * with incorrect time displays or missing from some views.
		 *
		 * @see Tribe__Events__Event in the-events-calendar/src/Tribe/Events/
		 *      for the full list of meta keys TEC expects.
		 */
		$start_utc     = gpei_local_to_utc( $e['start'], $e['timezone'] );
		$end_utc       = gpei_local_to_utc( $e['end'], $e['timezone'] );
		$duration      = gpei_calc_duration( $e['start'], $e['end'] );
		$timezone_abbr = gpei_get_timezone_abbr( $e['timezone'], $e['start'] );

		$event_id = wp_insert_post( array(
			'post_title'   => $e['title'],
			'post_content' => $e['content'],
			'post_type'    => 'tribe_events',
			'post_status'  => 'publish',
			'meta_input'   => array(
				'_EventStartDate'       => $e['start'],
				'_EventEndDate'         => $e['end'],
				'_EventStartDateUTC'    => $start_utc,
				'_EventEndDateUTC'      => $end_utc,
				'_EventDuration'        => $duration,
				'_EventTimezone'        => $e['timezone'],
				'_EventTimezoneAbbr'    => $timezone_abbr,
				'_EventVenueID'         => $venue_id,
				'_EventCost'            => '',
				'_EventCurrencySymbol'  => '$',
				'_EventCurrencyCode'    => 'USD',
				'_EventCurrencyPosition' => 'prefix',
				'_EventOrganizerID'     => '',
				'_EventShowMap'         => '1',
				'_EventShowMapLink'     => '1',
				'_EventURL'             => '',
				'_EventAllDay'          => '',
				'_EventHideFromUpcoming' => '',
			),
		) );

		if ( $event_id && ! is_wp_error( $event_id ) ) {
			wp_set_object_terms( $event_id, $e['categories'], 'tribe_events_cat' );
			wp_set_object_terms( $event_id, $e['tags'], 'post_tag' );
		}

		error_log( 'GPEI-TEC: Created event via wp_insert_post: ' . $e['title'] . ' (ID: ' . $event_id . ')' );
	}
}

flush_rewrite_rules();

error_log( 'GPEI-TEC: Demo data import complete.' );
