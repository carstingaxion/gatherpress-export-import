<?php
/**
 * Event Organiser demo data import script for WordPress Playground.
 *
 * Creates sample events, venues, categories, and tags
 * for Event Organiser migration testing.
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

// Create event categories (Event Organiser uses event-category taxonomy).
if ( ! term_exists( 'Lecture', 'event-category' ) ) {
	wp_insert_term( 'Lecture', 'event-category', array( 'slug' => 'lecture' ) );
}
if ( ! term_exists( 'Performance', 'event-category' ) ) {
	wp_insert_term( 'Performance', 'event-category', array( 'slug' => 'performance' ) );
}
if ( ! term_exists( 'Sprint', 'event-category' ) ) {
	wp_insert_term( 'Sprint', 'event-category', array( 'slug' => 'sprint' ) );
}

// Create event tags.
if ( ! term_exists( 'beginner-friendly', 'event-tag' ) ) {
	wp_insert_term( 'Beginner Friendly', 'event-tag', array( 'slug' => 'beginner-friendly' ) );
}
if ( ! term_exists( 'evening', 'event-tag' ) ) {
	wp_insert_term( 'Evening', 'event-tag', array( 'slug' => 'evening' ) );
}
if ( ! term_exists( 'daytime', 'event-tag' ) ) {
	wp_insert_term( 'Daytime', 'event-tag', array( 'slug' => 'daytime' ) );
}

// Create event-venue taxonomy terms.
$venue1_term = wp_insert_term(
	'University Lecture Theatre',
	'event-venue',
	array(
		'slug'        => 'university-lecture-theatre',
		'description' => 'University of London, Malet Street, London WC1E 7HU',
	) 
);
$venue2_term = wp_insert_term(
	'The Jazz Cellar',
	'event-venue',
	array(
		'slug'        => 'the-jazz-cellar',
		'description' => '42 Dean Street, Soho, London W1D 4PZ',
	) 
);
$venue3_term = wp_insert_term(
	'Community Hackerspace',
	'event-venue',
	array(
		'slug'        => 'community-hackerspace',
		'description' => '18 Brick Lane, London E1 6RF',
	) 
);

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
		'post_title'   => 'The History of Open Source Software',
		'post_content' => 'A fascinating lecture tracing the origins and evolution of open source software from the 1970s to today.',
		'post_type'    => 'event',
		'post_status'  => 'publish',
		'meta_input'   => array(
			'_eventorganiser_schedule_start_datetime' => '2025-08-28 18:30:00',
			'_eventorganiser_schedule_end_datetime'   => '2025-08-28 20:30:00',
			'_eventorganiser_schedule_start_finish'   => '2025-08-28 20:30:00',
			'_eventorganiser_schedule_last_start'     => '2025-08-28 18:30:00',
			'_eventorganiser_schedule_last_finish'    => '2025-08-28 20:30:00',
		),
		'tax_input'    => array(
			'event-category' => array( 'lecture' ),
			'event-tag'      => array( 'evening', 'beginner-friendly' ),
			'event-venue'    => $get_term_id( $venue1_term ),
		),
	) 
);

wp_insert_post(
	array(
		'post_title'   => 'Friday Night Live Jazz Session',
		'post_content' => 'Live jazz performances every Friday night. This week featuring the Django Reinhardt Tribute Quartet.',
		'post_type'    => 'event',
		'post_status'  => 'publish',
		'meta_input'   => array(
			'_eventorganiser_schedule_start_datetime' => '2025-09-05 20:00:00',
			'_eventorganiser_schedule_end_datetime'   => '2025-09-05 23:00:00',
			'_eventorganiser_schedule_start_finish'   => '2025-09-05 23:00:00',
			'_eventorganiser_schedule_last_start'     => '2025-09-05 20:00:00',
			'_eventorganiser_schedule_last_finish'    => '2025-09-05 23:00:00',
		),
		'tax_input'    => array(
			'event-category' => array( 'performance' ),
			'event-tag'      => array( 'evening' ),
			'event-venue'    => $get_term_id( $venue2_term ),
		),
	) 
);

wp_insert_post(
	array(
		'post_title'   => 'WordPress Translation Sprint',
		'post_content' => 'A collaborative day-long sprint to translate WordPress core and popular plugins into underrepresented languages.',
		'post_type'    => 'event',
		'post_status'  => 'publish',
		'meta_input'   => array(
			'_eventorganiser_schedule_start_datetime' => '2025-09-20 10:00:00',
			'_eventorganiser_schedule_end_datetime'   => '2025-09-20 17:00:00',
			'_eventorganiser_schedule_start_finish'   => '2025-09-20 17:00:00',
			'_eventorganiser_schedule_last_start'     => '2025-09-20 10:00:00',
			'_eventorganiser_schedule_last_finish'    => '2025-09-20 17:00:00',
		),
		'tax_input'    => array(
			'event-category' => array( 'sprint' ),
			'event-tag'      => array( 'daytime', 'beginner-friendly' ),
			'event-venue'    => $get_term_id( $venue3_term ),
		),
	) 
);

flush_rewrite_rules();
