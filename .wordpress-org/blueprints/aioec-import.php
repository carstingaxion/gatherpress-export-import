<?php
/**
 * All-in-One Event Calendar demo data import script for WordPress Playground.
 *
 * Creates sample events, categories, and tags
 * for AIOEC migration testing.
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

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
wp_insert_post( array(
	'post_title'   => 'Digital Art Exhibition Opening',
	'post_content' => 'Explore the intersection of technology and creativity at our digital art exhibition featuring works by 20 emerging artists.',
	'post_type'    => 'ai1ec_event',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_ai1ec_start'    => '2025-08-15 18:00:00',
		'_ai1ec_end'      => '2025-08-15 21:00:00',
		'_ai1ec_venue'    => 'Modern Art Gallery, 55 Gallery Row, Seattle',
		'_ai1ec_timezone' => 'America/Los_Angeles',
	),
	'tax_input'    => array(
		'events_categories' => array( 'arts' ),
		'events_tags'       => array( 'beginner' ),
	),
) );

wp_insert_post( array(
	'post_title'   => 'AI and WordPress: The Future',
	'post_content' => 'A virtual panel discussion on how artificial intelligence is shaping the future of WordPress development and content creation.',
	'post_type'    => 'ai1ec_event',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_ai1ec_start'    => '2025-09-22 15:00:00',
		'_ai1ec_end'      => '2025-09-22 17:00:00',
		'_ai1ec_venue'    => 'Online (Zoom Webinar)',
		'_ai1ec_timezone' => 'UTC',
	),
	'tax_input'    => array(
		'events_categories' => array( 'technology' ),
		'events_tags'       => array( 'advanced', 'virtual' ),
	),
) );

wp_insert_post( array(
	'post_title'   => 'Mindfulness and Wellness Retreat',
	'post_content' => 'A weekend retreat focused on mindfulness practices, yoga, nutrition workshops, and digital detox strategies.',
	'post_type'    => 'ai1ec_event',
	'post_status'  => 'publish',
	'meta_input'   => array(
		'_ai1ec_start'    => '2025-10-17 09:00:00',
		'_ai1ec_end'      => '2025-10-19 15:00:00',
		'_ai1ec_venue'    => 'Mountain View Resort, 100 Summit Dr, Boulder',
		'_ai1ec_timezone' => 'America/Denver',
	),
	'tax_input'    => array(
		'events_categories' => array( 'health' ),
		'events_tags'       => array( 'beginner' ),
	),
) );

flush_rewrite_rules();
