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
	'meta_input'  => array(
		'_location_address'  => '830 5th Ave',
		'_location_town'     => 'New York',
		'_location_state'    => 'NY',
		'_location_postcode' => '10065',
		'_location_country'  => 'US',
	),
) );

$loc2 = wp_insert_post( array(
	'post_title'  => 'Lakeside Sports Complex',
	'post_type'   => 'location',
	'post_status' => 'publish',
	'meta_input'  => array(
		'_location_address'  => '200 Lake Shore Blvd',
		'_location_town'     => 'Chicago',
		'_location_state'    => 'IL',
		'_location_postcode' => '60601',
		'_location_country'  => 'US',
	),
) );

$loc3 = wp_insert_post( array(
	'post_title'  => 'Public Library Main Hall',
	'post_type'   => 'location',
	'post_status' => 'publish',
	'meta_input'  => array(
		'_location_address'  => '42 Library Lane',
		'_location_town'     => 'San Francisco',
		'_location_state'    => 'CA',
		'_location_postcode' => '94102',
		'_location_country'  => 'US',
	),
) );

// Create events.
wp_insert_post( array(
	'post_title'   => 'Summer Jazz Festival',
	'post_content' => 'An evening of live jazz performances from local and international artists under the stars.',
	'post_type'    => 'event',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_event_start'    => '2025-07-18 18:00:00',
		'_event_end'      => '2025-07-18 23:00:00',
		'_event_timezone' => 'America/New_York',
		'_location_id'    => $loc1,
	),
	'tax_input'    => array(
		'event-categories' => array( 'music' ),
		'event-tags'       => array( 'outdoor', 'family-friendly' ),
	),
) );

wp_insert_post( array(
	'post_title'   => 'Community 5K Fun Run',
	'post_content' => 'Join the annual community fun run around the lakefront. All fitness levels welcome!',
	'post_type'    => 'event',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_event_start'    => '2025-08-10 08:00:00',
		'_event_end'      => '2025-08-10 11:00:00',
		'_event_timezone' => 'America/Chicago',
		'_location_id'    => $loc2,
	),
	'tax_input'    => array(
		'event-categories' => array( 'sports' ),
		'event-tags'       => array( 'outdoor', 'free-entry' ),
	),
) );

wp_insert_post( array(
	'post_title'   => 'Open Source Book Club Kickoff',
	'post_content' => 'First meeting of our monthly book club focused on open source culture, history, and innovation.',
	'post_type'    => 'event',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_event_start'    => '2025-09-05 19:00:00',
		'_event_end'      => '2025-09-05 21:00:00',
		'_event_timezone' => 'America/Los_Angeles',
		'_location_id'    => $loc3,
	),
	'tax_input'    => array(
		'event-categories' => array( 'education' ),
		'event-tags'       => array( 'free-entry' ),
	),
) );

flush_rewrite_rules();
