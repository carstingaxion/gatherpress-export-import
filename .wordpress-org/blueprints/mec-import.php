<?php
/**
 * Modern Events Calendar demo data import script for WordPress Playground.
 *
 * Creates sample events, locations, categories, labels, and organizers
 * for MEC migration testing.
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

// Create MEC categories.
if ( ! term_exists( 'Webinar', 'mec_category' ) ) {
	wp_insert_term( 'Webinar', 'mec_category', array( 'slug' => 'webinar' ) );
}
if ( ! term_exists( 'Seminar', 'mec_category' ) ) {
	wp_insert_term( 'Seminar', 'mec_category', array( 'slug' => 'seminar' ) );
}
if ( ! term_exists( 'Social', 'mec_category' ) ) {
	wp_insert_term( 'Social', 'mec_category', array( 'slug' => 'social' ) );
}

// Create MEC labels.
if ( ! term_exists( 'Featured', 'mec_label' ) ) {
	wp_insert_term( 'Featured', 'mec_label', array( 'slug' => 'featured' ) );
}
if ( ! term_exists( 'New', 'mec_label' ) ) {
	wp_insert_term( 'New', 'mec_label', array( 'slug' => 'new' ) );
}

// Create MEC locations (taxonomy terms).
$loc1_term = wp_insert_term( 'TechHub Coworking Space', 'mec_location', array( 'slug' => 'techhub-coworking' ) );
$loc2_term = wp_insert_term( 'Grand Hotel Ballroom', 'mec_location', array( 'slug' => 'grand-hotel-ballroom' ) );
$loc3_term = wp_insert_term( 'Online (Zoom)', 'mec_location', array( 'slug' => 'online-zoom' ) );

// Create MEC organizers (taxonomy terms).
$org1_term = wp_insert_term( 'WordPress Community Team', 'mec_organizer', array( 'slug' => 'wp-community-team' ) );
$org2_term = wp_insert_term( 'Developer Academy', 'mec_organizer', array( 'slug' => 'developer-academy' ) );

// Helper to extract term ID from wp_insert_term result.
$get_term_id = function ( $result ) {
	if ( is_wp_error( $result ) ) {
		return array();
	}
	return array( intval( is_array( $result ) ? $result['term_id'] : $result ) );
};

// Create events.
wp_insert_post(
	array(
		'post_title'   => 'Introduction to Full Site Editing',
		'post_content' => 'Learn the fundamentals of WordPress Full Site Editing, including template editing, global styles, and block patterns.',
		'post_type'    => 'mec-events',
		'post_status'  => 'publish',
		'meta_input'   => array(
			'mec_start_date'         => '2025-08-20',
			'mec_end_date'           => '2025-08-20',
			'mec_start_time_hour'    => '2',
			'mec_start_time_minutes' => '00',
			'mec_start_time_ampm'    => 'PM',
			'mec_end_time_hour'      => '4',
			'mec_end_time_minutes'   => '00',
			'mec_end_time_ampm'      => 'PM',
		),
		'tax_input'    => array(
			'mec_category'  => array( 'webinar' ),
			'mec_label'     => array( 'featured' ),
			'mec_location'  => $get_term_id( $loc3_term ),
			'mec_organizer' => $get_term_id( $org1_term ),
		),
	) 
);

wp_insert_post(
	array(
		'post_title'   => 'Advanced Custom Blocks Seminar',
		'post_content' => 'Deep dive into creating custom blocks with React, PHP rendering, and the WordPress Interactivity API.',
		'post_type'    => 'mec-events',
		'post_status'  => 'publish',
		'meta_input'   => array(
			'mec_start_date'         => '2025-09-12',
			'mec_end_date'           => '2025-09-12',
			'mec_start_time_hour'    => '10',
			'mec_start_time_minutes' => '00',
			'mec_start_time_ampm'    => 'AM',
			'mec_end_time_hour'      => '1',
			'mec_end_time_minutes'   => '00',
			'mec_end_time_ampm'      => 'PM',
		),
		'tax_input'    => array(
			'mec_category'  => array( 'seminar' ),
			'mec_label'     => array( 'new' ),
			'mec_location'  => $get_term_id( $loc1_term ),
			'mec_organizer' => $get_term_id( $org2_term ),
		),
	) 
);

wp_insert_post(
	array(
		'post_title'   => 'WP Community Social Dinner',
		'post_content' => 'An informal dinner for WordPress enthusiasts to connect, share stories, and enjoy great food together.',
		'post_type'    => 'mec-events',
		'post_status'  => 'publish',
		'meta_input'   => array(
			'mec_start_date'         => '2025-10-08',
			'mec_end_date'           => '2025-10-08',
			'mec_start_time_hour'    => '7',
			'mec_start_time_minutes' => '30',
			'mec_start_time_ampm'    => 'PM',
			'mec_end_time_hour'      => '10',
			'mec_end_time_minutes'   => '00',
			'mec_end_time_ampm'      => 'PM',
		),
		'tax_input'    => array(
			'mec_category'  => array( 'social' ),
			'mec_location'  => $get_term_id( $loc2_term ),
			'mec_organizer' => $get_term_id( $org1_term ),
		),
	) 
);

flush_rewrite_rules();
