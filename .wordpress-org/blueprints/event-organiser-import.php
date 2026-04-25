<?php
/**
 * Event Organiser demo data import script for WordPress Playground.
 *
 * Creates sample events, venues, categories, and tags
 * for Event Organiser migration testing.
 *
 * @package GatherPressExportImport
 */

require_once 'wordpress/wp-load.php';

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
$venue1_term = wp_insert_term( 'University Lecture Theatre', 'event-venue', array(
	'slug'        => 'university-lecture-theatre',
	'description' => 'University of London, Malet Street, London WC1E 7HU',
) );
$venue2_term = wp_insert_term( 'The Jazz Cellar', 'event-venue', array(
	'slug'        => 'the-jazz-cellar',
	'description' => '42 Dean Street, Soho, London W1D 4PZ',
) );
$venue3_term = wp_insert_term( 'Community Hackerspace', 'event-venue', array(
	'slug'        => 'community-hackerspace',
	'description' => '18 Brick Lane, London E1 6RF',
) );

// Create events.
$ev1 = wp_insert_post( array(
	'post_title'   => 'The History of Open Source Software',
	'post_content' => 'A fascinating lecture tracing the origins and evolution of open source software from the 1970s to today.',
	'post_type'    => 'event',
	'post_status'  => 'publish',
) );
if ( $ev1 ) {
	update_post_meta( $ev1, '_eventorganiser_schedule_start_datetime', '2025-08-28 18:30:00' );
	update_post_meta( $ev1, '_eventorganiser_schedule_end_datetime', '2025-08-28 20:30:00' );
	update_post_meta( $ev1, '_eventorganiser_schedule_start_finish', '2025-08-28 20:30:00' );
	update_post_meta( $ev1, '_eventorganiser_schedule_last_start', '2025-08-28 18:30:00' );
	update_post_meta( $ev1, '_eventorganiser_schedule_last_finish', '2025-08-28 20:30:00' );
	wp_set_object_terms( $ev1, array( 'lecture' ), 'event-category' );
	wp_set_object_terms( $ev1, array( 'evening', 'beginner-friendly' ), 'event-tag' );
	if ( ! is_wp_error( $venue1_term ) ) {
		wp_set_object_terms( $ev1, array( intval( is_array( $venue1_term ) ? $venue1_term['term_id'] : $venue1_term ) ), 'event-venue' );
	}
}

$ev2 = wp_insert_post( array(
	'post_title'   => 'Friday Night Live Jazz Session',
	'post_content' => 'Live jazz performances every Friday night. This week featuring the Django Reinhardt Tribute Quartet.',
	'post_type'    => 'event',
	'post_status'  => 'publish',
) );
if ( $ev2 ) {
	update_post_meta( $ev2, '_eventorganiser_schedule_start_datetime', '2025-09-05 20:00:00' );
	update_post_meta( $ev2, '_eventorganiser_schedule_end_datetime', '2025-09-05 23:00:00' );
	update_post_meta( $ev2, '_eventorganiser_schedule_start_finish', '2025-09-05 23:00:00' );
	update_post_meta( $ev2, '_eventorganiser_schedule_last_start', '2025-09-05 20:00:00' );
	update_post_meta( $ev2, '_eventorganiser_schedule_last_finish', '2025-09-05 23:00:00' );
	wp_set_object_terms( $ev2, array( 'performance' ), 'event-category' );
	wp_set_object_terms( $ev2, array( 'evening' ), 'event-tag' );
	if ( ! is_wp_error( $venue2_term ) ) {
		wp_set_object_terms( $ev2, array( intval( is_array( $venue2_term ) ? $venue2_term['term_id'] : $venue2_term ) ), 'event-venue' );
	}
}

$ev3 = wp_insert_post( array(
	'post_title'   => 'WordPress Translation Sprint',
	'post_content' => 'A collaborative day-long sprint to translate WordPress core and popular plugins into underrepresented languages.',
	'post_type'    => 'event',
	'post_status'  => 'publish',
) );
if ( $ev3 ) {
	update_post_meta( $ev3, '_eventorganiser_schedule_start_datetime', '2025-09-20 10:00:00' );
	update_post_meta( $ev3, '_eventorganiser_schedule_end_datetime', '2025-09-20 17:00:00' );
	update_post_meta( $ev3, '_eventorganiser_schedule_start_finish', '2025-09-20 17:00:00' );
	update_post_meta( $ev3, '_eventorganiser_schedule_last_start', '2025-09-20 10:00:00' );
	update_post_meta( $ev3, '_eventorganiser_schedule_last_finish', '2025-09-20 17:00:00' );
	wp_set_object_terms( $ev3, array( 'sprint' ), 'event-category' );
	wp_set_object_terms( $ev3, array( 'daytime', 'beginner-friendly' ), 'event-tag' );
	if ( ! is_wp_error( $venue3_term ) ) {
		wp_set_object_terms( $ev3, array( intval( is_array( $venue3_term ) ? $venue3_term['term_id'] : $venue3_term ) ), 'event-venue' );
	}
}

flush_rewrite_rules();
