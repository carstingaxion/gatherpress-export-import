<?php
/**
 * TEC demo data import script for WordPress Playground.
 *
 * Creates sample events, venues, categories, and tags
 * for The Events Calendar migration testing.
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

// Create event categories.
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

// Create venues.
$venue1 = wp_insert_post( array(
	'post_title'  => 'Downtown Convention Center',
	'post_type'   => 'tribe_venue',
	'post_status' => 'publish',
	'meta_input'  => array(
		'_VenueAddress' => '123 Main Street',
		'_VenueCity'    => 'Portland',
		'_VenueState'   => 'OR',
		'_VenueZip'     => '97201',
		'_VenueCountry' => 'United States',
		'_VenuePhone'   => '+1-503-555-0100',
		'_VenueURL'     => 'https://www.downtownconvention.example.com',
	),
) );

$venue2 = wp_insert_post( array(
	'post_title'  => 'Riverside Community Hall',
	'post_type'   => 'tribe_venue',
	'post_status' => 'publish',
	'meta_input'  => array(
		'_VenueAddress' => '456 River Road',
		'_VenueCity'    => 'Austin',
		'_VenueState'   => 'TX',
		'_VenueZip'     => '73301',
		'_VenueCountry' => 'United States',
		'_VenuePhone'   => '+1-512-555-0200',
	),
) );

$venue3 = wp_insert_post( array(
	'post_title'  => 'Innovation Hub Berlin',
	'post_type'   => 'tribe_venue',
	'post_status' => 'publish',
	'meta_input'  => array(
		'_VenueAddress' => 'Alexanderplatz 1',
		'_VenueCity'    => 'Berlin',
		'_VenueCountry' => 'Germany',
	),
) );

// Create events.
wp_insert_post( array(
	'post_title'   => 'Annual WordPress Summit 2025',
	'post_content' => 'Join us for the biggest WordPress event of the year featuring talks, workshops, and networking opportunities.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_EventStartDate' => '2025-09-15 09:00:00',
		'_EventEndDate'   => '2025-09-15 17:00:00',
		'_EventTimezone'  => 'America/Los_Angeles',
		'_EventVenueID'   => $venue1,
	),
	'tax_input'    => array(
		'tribe_events_cat' => array( 'conference' ),
		'post_tag'         => array( 'tech', 'networking' ),
	),
) );

wp_insert_post( array(
	'post_title'   => 'Block Editor Deep Dive Workshop',
	'post_content' => 'A hands-on workshop exploring advanced Gutenberg block development techniques and full site editing.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_EventStartDate' => '2025-10-03 14:00:00',
		'_EventEndDate'   => '2025-10-03 18:00:00',
		'_EventTimezone'  => 'America/Chicago',
		'_EventVenueID'   => $venue2,
	),
	'tax_input'    => array(
		'tribe_events_cat' => array( 'workshop' ),
		'post_tag'         => array( 'tech' ),
	),
) );

wp_insert_post( array(
	'post_title'   => 'Community Contributor Day',
	'post_content' => 'A full day dedicated to contributing to WordPress core, plugins, themes, and documentation.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_EventStartDate' => '2025-11-20 10:00:00',
		'_EventEndDate'   => '2025-11-20 16:00:00',
		'_EventTimezone'  => 'Europe/Berlin',
		'_EventVenueID'   => $venue3,
	),
	'tax_input'    => array(
		'tribe_events_cat' => array( 'meetup' ),
		'post_tag'         => array( 'community' ),
	),
) );

wp_insert_post( array(
	'post_title'   => 'Evening Networking Mixer',
	'post_content' => 'Casual evening meetup for WordPress professionals and enthusiasts. Appetizers and drinks provided.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_EventStartDate' => '2025-09-15 19:00:00',
		'_EventEndDate'   => '2025-09-15 22:00:00',
		'_EventTimezone'  => 'America/Los_Angeles',
		'_EventVenueID'   => $venue1,
	),
	'tax_input'    => array(
		'tribe_events_cat' => array( 'meetup' ),
		'post_tag'         => array( 'networking', 'community' ),
	),
) );

flush_rewrite_rules();
