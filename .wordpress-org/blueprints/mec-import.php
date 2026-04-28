<?php
/**
 * Modern Events Calendar demo data import script for WordPress Playground.
 *
 * Creates sample events, locations, categories, labels, and organizers
 * for MEC migration testing. Uses wp_insert_post() with explicit meta
 * keys and wp_set_object_terms() for taxonomy assignments.
 *
 * MEC stores:
 * - Events as `mec-events` CPT posts with date/time meta keys.
 * - Venues as terms of the `mec_location` taxonomy.
 * - Categories as terms of the `mec_category` taxonomy.
 * - Labels as terms of the `mec_label` taxonomy.
 * - Organizers as terms of the `mec_organizer` taxonomy.
 *
 * Date meta keys:
 * - `mec_start_date` / `mec_end_date` — 'Y-m-d' format.
 * - `mec_start_time_hour`, `mec_start_time_minutes`, `mec_start_time_ampm`
 * - `mec_end_time_hour`, `mec_end_time_minutes`, `mec_end_time_ampm`
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

error_log( 'GPEI-MEC: Starting Modern Events Calendar demo data import.' );

/*
 * -------------------------------------------------------------------------
 * 1. Verify MEC is available and register post type / taxonomies if needed.
 * -------------------------------------------------------------------------
 *
 * MEC registers its post type (`mec-events`) and taxonomies during `init`.
 * In WordPress Playground, the plugin may not fully activate. We check and
 * register manually as a fallback — same pattern used by the EO and EventON
 * import scripts.
 */
$mec_active = post_type_exists( 'mec-events' );

if ( $mec_active ) {
	error_log( 'GPEI-MEC: Modern Events Calendar detected as active.' );
} else {
	error_log( 'GPEI-MEC: MEC not fully active. Registering post type and taxonomies manually.' );

	if ( ! post_type_exists( 'mec-events' ) ) {
		register_post_type( 'mec-events', array(
			'label'    => 'MEC Events',
			'public'   => true,
			'show_ui'  => true,
			'supports' => array( 'title', 'editor', 'custom-fields' ),
		) );
	}
}

// Ensure all required taxonomies exist.
if ( ! taxonomy_exists( 'mec_category' ) ) {
	register_taxonomy( 'mec_category', 'mec-events', array(
		'label'        => 'MEC Category',
		'public'       => true,
		'hierarchical' => true,
	) );
}

if ( ! taxonomy_exists( 'mec_label' ) ) {
	register_taxonomy( 'mec_label', 'mec-events', array(
		'label'  => 'MEC Label',
		'public' => true,
	) );
}

if ( ! taxonomy_exists( 'mec_location' ) ) {
	register_taxonomy( 'mec_location', 'mec-events', array(
		'label'  => 'MEC Location',
		'public' => true,
	) );
}

if ( ! taxonomy_exists( 'mec_organizer' ) ) {
	register_taxonomy( 'mec_organizer', 'mec-events', array(
		'label'  => 'MEC Organizer',
		'public' => true,
	) );
}

error_log( 'GPEI-MEC: Post type and taxonomies verified.' );

/*
 * -------------------------------------------------------------------------
 * 2. Create MEC categories (mec_category taxonomy).
 * -------------------------------------------------------------------------
 */
$categories = array(
	'Webinar'  => 'webinar',
	'Seminar'  => 'seminar',
	'Social'   => 'social',
);

foreach ( $categories as $name => $slug ) {
	if ( ! term_exists( $name, 'mec_category' ) ) {
		$result = wp_insert_term( $name, 'mec_category', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to create category "' . $name . '": ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-MEC: Created category "' . $name . '" (term ID: ' . $result['term_id'] . ')' );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 3. Create MEC labels (mec_label taxonomy).
 * -------------------------------------------------------------------------
 */
$labels = array(
	'Featured' => 'featured',
	'New'      => 'new',
);

foreach ( $labels as $name => $slug ) {
	if ( ! term_exists( $slug, 'mec_label' ) ) {
		$result = wp_insert_term( $name, 'mec_label', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to create label "' . $name . '": ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-MEC: Created label "' . $name . '" (term ID: ' . $result['term_id'] . ')' );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 4. Create MEC locations (mec_location taxonomy terms).
 * -------------------------------------------------------------------------
 *
 * MEC stores venues as taxonomy terms of `mec_location`. Venue address
 * details are stored as taxonomy term meta, which is NOT included in
 * standard WordPress WXR exports. Only the term name, slug, and
 * description survive an export/import cycle.
 */
$locations = array(
	array(
		'name' => 'TechHub Coworking Space',
		'slug' => 'techhub-coworking',
	),
	array(
		'name' => 'Grand Hotel Ballroom',
		'slug' => 'grand-hotel-ballroom',
	),
	array(
		'name' => 'Online (Zoom)',
		'slug' => 'online-zoom',
	),
);

$location_term_ids = array();

foreach ( $locations as $loc ) {
	$existing = term_exists( $loc['slug'], 'mec_location' );
	if ( $existing ) {
		$location_term_ids[ $loc['slug'] ] = is_array( $existing ) ? intval( $existing['term_id'] ) : intval( $existing );
		error_log( 'GPEI-MEC: Location "' . $loc['name'] . '" already exists (term ID: ' . $location_term_ids[ $loc['slug'] ] . ')' );
	} else {
		$result = wp_insert_term( $loc['name'], 'mec_location', array( 'slug' => $loc['slug'] ) );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to create location "' . $loc['name'] . '": ' . $result->get_error_message() );
			$location_term_ids[ $loc['slug'] ] = 0;
		} else {
			$location_term_ids[ $loc['slug'] ] = intval( $result['term_id'] );
			error_log( 'GPEI-MEC: Created location "' . $loc['name'] . '" (term ID: ' . $result['term_id'] . ')' );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 5. Create MEC organizers (mec_organizer taxonomy terms).
 * -------------------------------------------------------------------------
 */
$organizers = array(
	array(
		'name' => 'WordPress Community Team',
		'slug' => 'wp-community-team',
	),
	array(
		'name' => 'Developer Academy',
		'slug' => 'developer-academy',
	),
);

$organizer_term_ids = array();

foreach ( $organizers as $org ) {
	$existing = term_exists( $org['slug'], 'mec_organizer' );
	if ( $existing ) {
		$organizer_term_ids[ $org['slug'] ] = is_array( $existing ) ? intval( $existing['term_id'] ) : intval( $existing );
		error_log( 'GPEI-MEC: Organizer "' . $org['name'] . '" already exists (term ID: ' . $organizer_term_ids[ $org['slug'] ] . ')' );
	} else {
		$result = wp_insert_term( $org['name'], 'mec_organizer', array( 'slug' => $org['slug'] ) );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to create organizer "' . $org['name'] . '": ' . $result->get_error_message() );
			$organizer_term_ids[ $org['slug'] ] = 0;
		} else {
			$organizer_term_ids[ $org['slug'] ] = intval( $result['term_id'] );
			error_log( 'GPEI-MEC: Created organizer "' . $org['name'] . '" (term ID: ' . $result['term_id'] . ')' );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 6. Create events.
 * -------------------------------------------------------------------------
 *
 * MEC stores event dates as 'Y-m-d' strings and times as separate
 * hour, minute, and AM/PM fields in post meta:
 * - mec_start_date / mec_end_date          — 'Y-m-d' format.
 * - mec_start_time_hour                     — Hour (1-12).
 * - mec_start_time_minutes                  — Minutes (00-59).
 * - mec_start_time_ampm                     — 'AM' or 'PM'.
 * - mec_end_time_hour / _minutes / _ampm    — Same for end time.
 *
 * Taxonomy terms are assigned AFTER wp_insert_post() using
 * wp_set_object_terms() to ensure reliable assignment regardless of
 * user capabilities — same pattern used by the EO import script.
 */
$events = array(
	array(
		'title'          => 'Introduction to Full Site Editing',
		'content'        => 'Learn the fundamentals of WordPress Full Site Editing, including template editing, global styles, and block patterns.',
		'start_date'     => '2025-08-20',
		'end_date'       => '2025-08-20',
		'start_hour'     => '2',
		'start_minutes'  => '00',
		'start_ampm'     => 'PM',
		'end_hour'       => '4',
		'end_minutes'    => '00',
		'end_ampm'       => 'PM',
		'categories'     => array( 'webinar' ),
		'labels'         => array( 'featured' ),
		'location_slug'  => 'online-zoom',
		'organizer_slug' => 'wp-community-team',
	),
	array(
		'title'          => 'Advanced Custom Blocks Seminar',
		'content'        => 'Deep dive into creating custom blocks with React, PHP rendering, and the WordPress Interactivity API.',
		'start_date'     => '2025-09-12',
		'end_date'       => '2025-09-12',
		'start_hour'     => '10',
		'start_minutes'  => '00',
		'start_ampm'     => 'AM',
		'end_hour'       => '1',
		'end_minutes'    => '00',
		'end_ampm'       => 'PM',
		'categories'     => array( 'seminar' ),
		'labels'         => array( 'new' ),
		'location_slug'  => 'techhub-coworking',
		'organizer_slug' => 'developer-academy',
	),
	array(
		'title'          => 'WP Community Social Dinner',
		'content'        => 'An informal dinner for WordPress enthusiasts to connect, share stories, and enjoy great food together.',
		'start_date'     => '2025-10-08',
		'end_date'       => '2025-10-08',
		'start_hour'     => '7',
		'start_minutes'  => '30',
		'start_ampm'     => 'PM',
		'end_hour'       => '10',
		'end_minutes'    => '00',
		'end_ampm'       => 'PM',
		'categories'     => array( 'social' ),
		'labels'         => array(),
		'location_slug'  => 'grand-hotel-ballroom',
		'organizer_slug' => 'wp-community-team',
	),
);

foreach ( $events as $event ) {
	$event_id = wp_insert_post( array(
		'post_title'   => $event['title'],
		'post_content' => $event['content'],
		'post_type'    => 'mec-events',
		'post_status'  => 'publish',
		'meta_input'   => array(
			'mec_start_date'         => $event['start_date'],
			'mec_end_date'           => $event['end_date'],
			'mec_start_time_hour'    => $event['start_hour'],
			'mec_start_time_minutes' => $event['start_minutes'],
			'mec_start_time_ampm'    => $event['start_ampm'],
			'mec_end_time_hour'      => $event['end_hour'],
			'mec_end_time_minutes'   => $event['end_minutes'],
			'mec_end_time_ampm'      => $event['end_ampm'],
		),
	) );

	if ( is_wp_error( $event_id ) || 0 === $event_id ) {
		error_log( 'GPEI-MEC: Failed to create event: "' . $event['title'] . '"' );
		continue;
	}

	error_log( 'GPEI-MEC: Created event "' . $event['title'] . '" (ID: ' . $event_id . ')' );

	/*
	 * Assign taxonomy terms AFTER post creation using wp_set_object_terms().
	 *
	 * Using wp_set_object_terms() instead of `tax_input` in wp_insert_post()
	 * because `tax_input` requires:
	 * 1. The taxonomy to be registered for the post type at insertion time.
	 * 2. The current user to have the `assign_terms` capability.
	 *
	 * In WordPress Playground, both conditions may not always be met.
	 * wp_set_object_terms() bypasses capability checks and works reliably.
	 *
	 * @see Event Organiser import script for the same pattern and rationale.
	 */

	// Assign categories.
	if ( ! empty( $event['categories'] ) ) {
		$result = wp_set_object_terms( $event_id, $event['categories'], 'mec_category' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to assign categories to event ' . $event_id . ': ' . $result->get_error_message() );
		}
	}

	// Assign labels.
	if ( ! empty( $event['labels'] ) ) {
		$result = wp_set_object_terms( $event_id, $event['labels'], 'mec_label' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to assign labels to event ' . $event_id . ': ' . $result->get_error_message() );
		}
	}

	// Assign location.
	if ( ! empty( $event['location_slug'] ) && ! empty( $location_term_ids[ $event['location_slug'] ] ) ) {
		$loc_term_id = $location_term_ids[ $event['location_slug'] ];
		$result      = wp_set_object_terms( $event_id, array( $loc_term_id ), 'mec_location' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to assign location to event ' . $event_id . ': ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-MEC: Assigned location "' . $event['location_slug'] . '" (term ID: ' . $loc_term_id . ') to event ' . $event_id );
		}
	}

	// Assign organizer.
	if ( ! empty( $event['organizer_slug'] ) && ! empty( $organizer_term_ids[ $event['organizer_slug'] ] ) ) {
		$org_term_id = $organizer_term_ids[ $event['organizer_slug'] ];
		$result      = wp_set_object_terms( $event_id, array( $org_term_id ), 'mec_organizer' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to assign organizer to event ' . $event_id . ': ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-MEC: Assigned organizer "' . $event['organizer_slug'] . '" (term ID: ' . $org_term_id . ') to event ' . $event_id );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 7. Flush rewrite rules.
 * -------------------------------------------------------------------------
 */
flush_rewrite_rules();

error_log( 'GPEI-MEC: Demo data import complete.' );
