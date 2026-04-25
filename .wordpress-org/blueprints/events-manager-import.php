<?php
/**
 * Events Manager demo data import script for WordPress Playground.
 *
 * Creates sample events, locations, categories, and tags
 * for Events Manager migration testing.
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

// Create event categories (Events Manager uses event-categories taxonomy).
if ( ! term_exists( 'Music', 'event-categories' ) ) {
	wp_insert_term( 'Music', 'event-categories', array( 'slug' => 'music' ) );
}
if ( ! term_exists( 'Sports', 'event-categories' ) ) {
	wp_insert_term( 'Sports', 'event-categories', array( 'slug' => 'sports' ) );
}
if ( ! term_exists( 'Education', 'event-categories' ) ) {
	wp_insert_term( 'Education', 'event-categories', array( 'slug' => 'education' ) );
}

// Create event tags.
if ( ! term_exists( 'outdoor', 'event-tags' ) ) {
	wp_insert_term( 'outdoor', 'event-tags', array( 'slug' => 'outdoor' ) );
}
if ( ! term_exists( 'family-friendly', 'event-tags' ) ) {
	wp_insert_term( 'family-friendly', 'event-tags', array( 'slug' => 'family-friendly' ) );
}
if ( ! term_exists( 'free-entry', 'event-tags' ) ) {
	wp_insert_term( 'free-entry', 'event-tags', array( 'slug' => 'free-entry' ) );
}

// Create locations (venue post type).
$loc1 = wp_insert_post( array(
	'post_title'  => 'Central Park Amphitheatre',
	'post_type'   => 'location',
	'post_status' => 'publish',
) );
if ( $loc1 ) {
	update_post_meta( $loc1, '_location_address', '830 5th Ave' );
	update_post_meta( $loc1, '_location_town', 'New York' );
	update_post_meta( $loc1, '_location_state', 'NY' );
	update_post_meta( $loc1, '_location_postcode', '10065' );
	update_post_meta( $loc1, '_location_country', 'US' );
}

$loc2 = wp_insert_post( array(
	'post_title'  => 'Lakeside Sports Complex',
	'post_type'   => 'location',
	'post_status' => 'publish',
) );
if ( $loc2 ) {
	update_post_meta( $loc2, '_location_address', '200 Lake Shore Blvd' );
	update_post_meta( $loc2, '_location_town', 'Chicago' );
	update_post_meta( $loc2, '_location_state', 'IL' );
	update_post_meta( $loc2, '_location_postcode', '60601' );
	update_post_meta( $loc2, '_location_country', 'US' );
}

$loc3 = wp_insert_post( array(
	'post_title'  => 'Public Library Main Hall',
	'post_type'   => 'location',
	'post_status' => 'publish',
) );
if ( $loc3 ) {
	update_post_meta( $loc3, '_location_address', '42 Library Lane' );
	update_post_meta( $loc3, '_location_town', 'San Francisco' );
	update_post_meta( $loc3, '_location_state', 'CA' );
	update_post_meta( $loc3, '_location_postcode', '94102' );
	update_post_meta( $loc3, '_location_country', 'US' );
}

// Create events.
$ev1 = wp_insert_post( array(
	'post_title'   => 'Summer Jazz Festival',
	'post_content' => 'An evening of live jazz performances from local and international artists under the stars.',
	'post_type'    => 'event',
	'post_status'  => 'publish',
) );
if ( $ev1 ) {
	update_post_meta( $ev1, '_event_start', '2025-07-18 18:00:00' );
	update_post_meta( $ev1, '_event_end', '2025-07-18 23:00:00' );
	update_post_meta( $ev1, '_event_timezone', 'America/New_York' );
	update_post_meta( $ev1, '_location_id', $loc1 );
	wp_set_object_terms( $ev1, array( 'music' ), 'event-categories' );
	wp_set_object_terms( $ev1, array( 'outdoor', 'family-friendly' ), 'event-tags' );
}

$ev2 = wp_insert_post( array(
	'post_title'   => 'Community 5K Fun Run',
	'post_content' => 'Join the annual community fun run around the lakefront. All fitness levels welcome!',
	'post_type'    => 'event',
	'post_status'  => 'publish',
) );
if ( $ev2 ) {
	update_post_meta( $ev2, '_event_start', '2025-08-10 08:00:00' );
	update_post_meta( $ev2, '_event_end', '2025-08-10 11:00:00' );
	update_post_meta( $ev2, '_event_timezone', 'America/Chicago' );
	update_post_meta( $ev2, '_location_id', $loc2 );
	wp_set_object_terms( $ev2, array( 'sports' ), 'event-categories' );
	wp_set_object_terms( $ev2, array( 'outdoor', 'free-entry' ), 'event-tags' );
}

$ev3 = wp_insert_post( array(
	'post_title'   => 'Open Source Book Club Kickoff',
	'post_content' => 'First meeting of our monthly book club focused on open source culture, history, and innovation.',
	'post_type'    => 'event',
	'post_status'  => 'publish',
) );
if ( $ev3 ) {
	update_post_meta( $ev3, '_event_start', '2025-09-05 19:00:00' );
	update_post_meta( $ev3, '_event_end', '2025-09-05 21:00:00' );
	update_post_meta( $ev3, '_event_timezone', 'America/Los_Angeles' );
	update_post_meta( $ev3, '_location_id', $loc3 );
	wp_set_object_terms( $ev3, array( 'education' ), 'event-categories' );
	wp_set_object_terms( $ev3, array( 'free-entry' ), 'event-tags' );
}

flush_rewrite_rules();
