<?php
/**
 * TEC demo data import script for WordPress Playground.
 *
 * Creates sample events, venues, categories, and tags
 * for The Events Calendar migration testing.
 *
 * @package GatherPressExportImport
 */

require_once 'wordpress/wp-load.php';

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
) );
if ( $venue1 ) {
	update_post_meta( $venue1, '_VenueAddress', '123 Main Street' );
	update_post_meta( $venue1, '_VenueCity', 'Portland' );
	update_post_meta( $venue1, '_VenueState', 'OR' );
	update_post_meta( $venue1, '_VenueZip', '97201' );
	update_post_meta( $venue1, '_VenueCountry', 'United States' );
	update_post_meta( $venue1, '_VenuePhone', '+1-503-555-0100' );
	update_post_meta( $venue1, '_VenueURL', 'https://www.downtownconvention.example.com' );
}

$venue2 = wp_insert_post( array(
	'post_title'  => 'Riverside Community Hall',
	'post_type'   => 'tribe_venue',
	'post_status' => 'publish',
) );
if ( $venue2 ) {
	update_post_meta( $venue2, '_VenueAddress', '456 River Road' );
	update_post_meta( $venue2, '_VenueCity', 'Austin' );
	update_post_meta( $venue2, '_VenueState', 'TX' );
	update_post_meta( $venue2, '_VenueZip', '73301' );
	update_post_meta( $venue2, '_VenueCountry', 'United States' );
	update_post_meta( $venue2, '_VenuePhone', '+1-512-555-0200' );
}

$venue3 = wp_insert_post( array(
	'post_title'  => 'Innovation Hub Berlin',
	'post_type'   => 'tribe_venue',
	'post_status' => 'publish',
) );
if ( $venue3 ) {
	update_post_meta( $venue3, '_VenueAddress', 'Alexanderplatz 1' );
	update_post_meta( $venue3, '_VenueCity', 'Berlin' );
	update_post_meta( $venue3, '_VenueCountry', 'Germany' );
}

// Create events.
$event1 = wp_insert_post( array(
	'post_title'   => 'Annual WordPress Summit 2025',
	'post_content' => 'Join us for the biggest WordPress event of the year featuring talks, workshops, and networking opportunities.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
) );
if ( $event1 ) {
	update_post_meta( $event1, '_EventStartDate', '2025-09-15 09:00:00' );
	update_post_meta( $event1, '_EventEndDate', '2025-09-15 17:00:00' );
	update_post_meta( $event1, '_EventTimezone', 'America/Los_Angeles' );
	update_post_meta( $event1, '_EventVenueID', $venue1 );
	wp_set_object_terms( $event1, array( 'conference' ), 'tribe_events_cat' );
	wp_set_object_terms( $event1, array( 'tech', 'networking' ), 'post_tag' );
}

$event2 = wp_insert_post( array(
	'post_title'   => 'Block Editor Deep Dive Workshop',
	'post_content' => 'A hands-on workshop exploring advanced Gutenberg block development techniques and full site editing.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
) );
if ( $event2 ) {
	update_post_meta( $event2, '_EventStartDate', '2025-10-03 14:00:00' );
	update_post_meta( $event2, '_EventEndDate', '2025-10-03 18:00:00' );
	update_post_meta( $event2, '_EventTimezone', 'America/Chicago' );
	update_post_meta( $event2, '_EventVenueID', $venue2 );
	wp_set_object_terms( $event2, array( 'workshop' ), 'tribe_events_cat' );
	wp_set_object_terms( $event2, array( 'tech' ), 'post_tag' );
}

$event3 = wp_insert_post( array(
	'post_title'   => 'Community Contributor Day',
	'post_content' => 'A full day dedicated to contributing to WordPress core, plugins, themes, and documentation.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
) );
if ( $event3 ) {
	update_post_meta( $event3, '_EventStartDate', '2025-11-20 10:00:00' );
	update_post_meta( $event3, '_EventEndDate', '2025-11-20 16:00:00' );
	update_post_meta( $event3, '_EventTimezone', 'Europe/Berlin' );
	update_post_meta( $event3, '_EventVenueID', $venue3 );
	wp_set_object_terms( $event3, array( 'meetup' ), 'tribe_events_cat' );
	wp_set_object_terms( $event3, array( 'community' ), 'post_tag' );
}

$event4 = wp_insert_post( array(
	'post_title'   => 'Evening Networking Mixer',
	'post_content' => 'Casual evening meetup for WordPress professionals and enthusiasts. Appetizers and drinks provided.',
	'post_type'    => 'tribe_events',
	'post_status'  => 'publish',
) );
if ( $event4 ) {
	update_post_meta( $event4, '_EventStartDate', '2025-09-15 19:00:00' );
	update_post_meta( $event4, '_EventEndDate', '2025-09-15 22:00:00' );
	update_post_meta( $event4, '_EventTimezone', 'America/Los_Angeles' );
	update_post_meta( $event4, '_EventVenueID', $venue1 );
	wp_set_object_terms( $event4, array( 'meetup' ), 'tribe_events_cat' );
	wp_set_object_terms( $event4, array( 'networking', 'community' ), 'post_tag' );
}

flush_rewrite_rules();
