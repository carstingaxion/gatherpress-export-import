<?php
/**
 * All-in-One Event Calendar demo data import script for WordPress Playground.
 *
 * Creates sample events, categories, and tags
 * for AIOEC migration testing.
 *
 * @package GatherPressExportImport
 */

require_once 'wordpress/wp-load.php';

// Register the ai1ec_event post type manually for demo purposes.
if ( ! post_type_exists( 'ai1ec_event' ) ) {
	register_post_type( 'ai1ec_event', array(
		'label'   => 'AI1EC Events',
		'public'  => true,
		'show_ui' => true,
	) );
}

// Register AIOEC taxonomies.
if ( ! taxonomy_exists( 'events_categories' ) ) {
	register_taxonomy( 'events_categories', 'ai1ec_event', array(
		'label'  => 'Events Categories',
		'public' => true,
	) );
}
if ( ! taxonomy_exists( 'events_tags' ) ) {
	register_taxonomy( 'events_tags', 'ai1ec_event', array(
		'label'  => 'Events Tags',
		'public' => true,
	) );
}

// Create categories.
wp_insert_term( 'Arts', 'events_categories', array( 'slug' => 'arts' ) );
wp_insert_term( 'Technology', 'events_categories', array( 'slug' => 'technology' ) );
wp_insert_term( 'Health', 'events_categories', array( 'slug' => 'health' ) );

// Create tags.
wp_insert_term( 'beginner', 'events_tags', array( 'slug' => 'beginner' ) );
wp_insert_term( 'advanced', 'events_tags', array( 'slug' => 'advanced' ) );
wp_insert_term( 'virtual', 'events_tags', array( 'slug' => 'virtual' ) );

// Create events.
$ev1 = wp_insert_post( array(
	'post_title'   => 'Digital Art Exhibition Opening',
	'post_content' => 'Explore the intersection of technology and creativity at our digital art exhibition featuring works by 20 emerging artists.',
	'post_type'    => 'ai1ec_event',
	'post_status'  => 'publish',
) );
if ( $ev1 ) {
	update_post_meta( $ev1, '_ai1ec_start', '2025-08-15 18:00:00' );
	update_post_meta( $ev1, '_ai1ec_end', '2025-08-15 21:00:00' );
	update_post_meta( $ev1, '_ai1ec_venue', 'Modern Art Gallery, 55 Gallery Row, Seattle' );
	update_post_meta( $ev1, '_ai1ec_timezone', 'America/Los_Angeles' );
	wp_set_object_terms( $ev1, array( 'arts' ), 'events_categories' );
	wp_set_object_terms( $ev1, array( 'beginner' ), 'events_tags' );
}

$ev2 = wp_insert_post( array(
	'post_title'   => 'AI and WordPress: The Future',
	'post_content' => 'A virtual panel discussion on how artificial intelligence is shaping the future of WordPress development and content creation.',
	'post_type'    => 'ai1ec_event',
	'post_status'  => 'publish',
) );
if ( $ev2 ) {
	update_post_meta( $ev2, '_ai1ec_start', '2025-09-22 15:00:00' );
	update_post_meta( $ev2, '_ai1ec_end', '2025-09-22 17:00:00' );
	update_post_meta( $ev2, '_ai1ec_venue', 'Online (Zoom Webinar)' );
	update_post_meta( $ev2, '_ai1ec_timezone', 'UTC' );
	wp_set_object_terms( $ev2, array( 'technology' ), 'events_categories' );
	wp_set_object_terms( $ev2, array( 'advanced', 'virtual' ), 'events_tags' );
}

$ev3 = wp_insert_post( array(
	'post_title'   => 'Mindfulness and Wellness Retreat',
	'post_content' => 'A weekend retreat focused on mindfulness practices, yoga, nutrition workshops, and digital detox strategies.',
	'post_type'    => 'ai1ec_event',
	'post_status'  => 'publish',
) );
if ( $ev3 ) {
	update_post_meta( $ev3, '_ai1ec_start', '2025-10-17 09:00:00' );
	update_post_meta( $ev3, '_ai1ec_end', '2025-10-19 15:00:00' );
	update_post_meta( $ev3, '_ai1ec_venue', 'Mountain View Resort, 100 Summit Dr, Boulder' );
	update_post_meta( $ev3, '_ai1ec_timezone', 'America/Denver' );
	wp_set_object_terms( $ev3, array( 'health' ), 'events_categories' );
	wp_set_object_terms( $ev3, array( 'beginner' ), 'events_tags' );
}

flush_rewrite_rules();
