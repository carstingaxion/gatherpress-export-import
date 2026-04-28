<?php
/**
 * EventON demo data import script for WordPress Playground.
 *
 * Creates sample events, locations, event types, and organizers
 * for EventON migration testing.
 *
 * @package GatherPressExportImport
 */

require_once 'wordpress/wp-load.php';

// Register the ajde_events post type manually for demo purposes.
if ( ! post_type_exists( 'ajde_events' ) ) {
	register_post_type(
		'ajde_events',
		array(
			'label'   => 'EventON Events',
			'public'  => true,
			'show_ui' => true,
		) 
	);
}

// Register EventON taxonomies.
if ( ! taxonomy_exists( 'event_type' ) ) {
	register_taxonomy(
		'event_type',
		'ajde_events',
		array(
			'label'  => 'Event Type',
			'public' => true,
		) 
	);
}
if ( ! taxonomy_exists( 'event_location' ) ) {
	register_taxonomy(
		'event_location',
		'ajde_events',
		array(
			'label'  => 'Event Location',
			'public' => true,
		) 
	);
}
if ( ! taxonomy_exists( 'event_organizer' ) ) {
	register_taxonomy(
		'event_organizer',
		'ajde_events',
		array(
			'label'  => 'Event Organizer',
			'public' => true,
		) 
	);
}

// Create event type terms.
wp_insert_term( 'Charity', 'event_type', array( 'slug' => 'charity' ) );
wp_insert_term( 'Corporate', 'event_type', array( 'slug' => 'corporate' ) );
wp_insert_term( 'Festival', 'event_type', array( 'slug' => 'festival' ) );

// Create location terms.
$loc1_term = wp_insert_term( 'City Exhibition Grounds', 'event_location', array( 'slug' => 'city-exhibition-grounds' ) );
$loc2_term = wp_insert_term( 'Harbor Conference Rooms', 'event_location', array( 'slug' => 'harbor-conference-rooms' ) );

// Create organizer terms.
$org1_term = wp_insert_term( 'EventON Foundation', 'event_organizer', array( 'slug' => 'eventon-foundation' ) );

// Create events with Unix timestamps.
$ev1 = wp_insert_post(
	array(
		'post_title'   => 'Annual Charity Gala Night',
		'post_content' => 'An elegant evening of dining, entertainment, and fundraising for local community programs.',
		'post_type'    => 'ajde_events',
		'post_status'  => 'publish',
	) 
);
if ( $ev1 ) {
	update_post_meta( $ev1, 'evcal_srow', strtotime( '2025-08-25 19:00:00' ) );
	update_post_meta( $ev1, 'evcal_erow', strtotime( '2025-08-25 23:00:00' ) );
	update_post_meta( $ev1, 'evcal_allday', 'no' );
	wp_set_object_terms( $ev1, array( 'charity' ), 'event_type' );
	if ( ! is_wp_error( $loc1_term ) ) {
		wp_set_object_terms( $ev1, array( intval( is_array( $loc1_term ) ? $loc1_term['term_id'] : $loc1_term ) ), 'event_location' );
	}
	if ( ! is_wp_error( $org1_term ) ) {
		wp_set_object_terms( $ev1, array( intval( is_array( $org1_term ) ? $org1_term['term_id'] : $org1_term ) ), 'event_organizer' );
	}
}

$ev2 = wp_insert_post(
	array(
		'post_title'   => 'Q3 Strategy Review Meeting',
		'post_content' => 'Quarterly strategy review and planning session for all department leads and managers.',
		'post_type'    => 'ajde_events',
		'post_status'  => 'publish',
	) 
);
if ( $ev2 ) {
	update_post_meta( $ev2, 'evcal_srow', strtotime( '2025-09-01 09:00:00' ) );
	update_post_meta( $ev2, 'evcal_erow', strtotime( '2025-09-01 12:00:00' ) );
	update_post_meta( $ev2, 'evcal_allday', 'no' );
	wp_set_object_terms( $ev2, array( 'corporate' ), 'event_type' );
	if ( ! is_wp_error( $loc2_term ) ) {
		wp_set_object_terms( $ev2, array( intval( is_array( $loc2_term ) ? $loc2_term['term_id'] : $loc2_term ) ), 'event_location' );
	}
}

$ev3 = wp_insert_post(
	array(
		'post_title'   => 'Harvest Moon Food Festival',
		'post_content' => 'A full-day outdoor food festival with local vendors, live cooking demos, craft beer, and family activities.',
		'post_type'    => 'ajde_events',
		'post_status'  => 'publish',
	) 
);
if ( $ev3 ) {
	update_post_meta( $ev3, 'evcal_srow', strtotime( '2025-10-04 10:00:00' ) );
	update_post_meta( $ev3, 'evcal_erow', strtotime( '2025-10-04 20:00:00' ) );
	update_post_meta( $ev3, 'evcal_allday', 'no' );
	wp_set_object_terms( $ev3, array( 'festival' ), 'event_type' );
	if ( ! is_wp_error( $loc1_term ) ) {
		wp_set_object_terms( $ev3, array( intval( is_array( $loc1_term ) ? $loc1_term['term_id'] : $loc1_term ) ), 'event_location' );
	}
	if ( ! is_wp_error( $org1_term ) ) {
		wp_set_object_terms( $ev3, array( intval( is_array( $org1_term ) ? $org1_term['term_id'] : $org1_term ) ), 'event_organizer' );
	}
}

flush_rewrite_rules();
