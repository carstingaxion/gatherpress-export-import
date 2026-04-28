<?php
/**
 * Modern Events Calendar demo data import script for WordPress Playground.
 *
 * Creates sample events, locations, categories, labels, and organizers
 * for MEC migration testing. Uses MEC's own internal API when available
 * (`MEC::getInstance()` → `$main->save_event()`), falling back to
 * wp_insert_post() with explicit meta keys and wp_set_object_terms()
 * for taxonomy assignments.
 *
 * ## MEC's Internal Save Pattern
 *
 * MEC provides an internal API for event creation that populates custom
 * tables, internal caches, and all required meta in one call:
 *
 * ```php
 * $mec  = MEC::getInstance();
 * $main = $mec->getMain();
 * $data = array(
 *     'post' => array(
 *         'post_title'  => 'My Event',
 *         'post_status' => 'publish',
 *     ),
 *     'meta' => array(
 *         'mec_start_date'         => '2026-05-01',
 *         'mec_end_date'           => '2026-05-01',
 *         'mec_start_time_hour'    => '10',
 *         'mec_start_time_minutes' => '00',
 *         'mec_end_time_hour'      => '12',
 *         'mec_end_time_minutes'   => '00',
 *     ),
 *     'terms' => array(
 *         'mec_location' => array( $term_id ),
 *     ),
 * );
 * $event_id = $main->save_event( $data );
 * ```
 *
 * This ensures the MEC admin UI, shortcodes, and REST endpoints
 * function correctly. When MEC's API is not available (e.g., the
 * plugin failed to activate), we fall back to wp_insert_post() with
 * all required meta keys — sufficient for WXR export testing.
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
 * 1. Detect MEC API availability.
 * -------------------------------------------------------------------------
 *
 * MEC provides `MEC::getInstance()` which returns the main plugin instance.
 * From there, `$mec->getMain()` returns an `MEC_main` object that has the
 * `save_event()` method for creating events with full internal state.
 *
 * We check for this API first. If unavailable, we fall back to manual
 * post creation — same pattern used by the TEC and EM import scripts.
 */
$use_mec_api = false;
$mec_main    = null;

if ( class_exists( 'MEC' ) ) {
	try {
		/*
		 * MEC::getInstance() is a factory/singleton accessor that expects
		 * a dot-separated file path and the class name to instantiate.
		 * For the main library class (MEC_main), the canonical call is:
		 *
		 *   MEC::getInstance( 'app/libraries/main.php', 'MEC_main' )
		 *
		 * or equivalently with dot notation:
		 *
		 *   MEC::getInstance( 'app.libraries.main' )
		 *
		 * The returned object has the save_event() method we need.
		 */
		$mec_main = MEC::getInstance( 'app.libraries.main' );

		if ( $mec_main && method_exists( $mec_main, 'save_event' ) ) {
			$use_mec_api = true;
		}

		// Fallback: check for a global MEC_main instance.
		if ( ! $use_mec_api && isset( $GLOBALS['MEC_main'] ) && method_exists( $GLOBALS['MEC_main'], 'save_event' ) ) {
			$mec_main    = $GLOBALS['MEC_main'];
			$use_mec_api = true;
		}
	} catch ( \Throwable $e ) {
		error_log( 'GPEI-MEC: Error while initialising MEC API: ' . $e->getMessage() );
	}
}

$mec_active = post_type_exists( 'mec-events' );

error_log( 'GPEI-MEC: MEC API available: ' . ( $use_mec_api ? 'YES' : 'NO' ) );
error_log( 'GPEI-MEC: mec-events post type exists: ' . ( $mec_active ? 'YES' : 'NO' ) );

if ( ! $mec_active ) {
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
 * When MEC's API is available, we use `$main->save_event()` which handles:
 * - Creating the `mec-events` CPT post.
 * - Populating MEC's custom `mec_events` table.
 * - Setting all required internal meta keys.
 * - Assigning taxonomy terms.
 * - Firing MEC's internal hooks for caching and scheduling.
 *
 * When MEC's API is NOT available, we fall back to wp_insert_post() with
 * explicit meta keys — sufficient for WXR export testing.
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
	// Resolve taxonomy term IDs for the current event.
	$loc_term_id = 0;
	if ( ! empty( $event['location_slug'] ) && ! empty( $location_term_ids[ $event['location_slug'] ] ) ) {
		$loc_term_id = $location_term_ids[ $event['location_slug'] ];
	}

	$org_term_id = 0;
	if ( ! empty( $event['organizer_slug'] ) && ! empty( $organizer_term_ids[ $event['organizer_slug'] ] ) ) {
		$org_term_id = $organizer_term_ids[ $event['organizer_slug'] ];
	}

	// Resolve category term IDs by slug.
	$cat_term_ids = array();
	foreach ( $event['categories'] as $cat_slug ) {
		$cat_term = term_exists( $cat_slug, 'mec_category' );
		if ( $cat_term ) {
			$cat_term_ids[] = is_array( $cat_term ) ? intval( $cat_term['term_id'] ) : intval( $cat_term );
		}
	}

	// Resolve label term IDs by slug.
	$label_term_ids = array();
	foreach ( $event['labels'] as $label_slug ) {
		$label_term = term_exists( $label_slug, 'mec_label' );
		if ( $label_term ) {
			$label_term_ids[] = is_array( $label_term ) ? intval( $label_term['term_id'] ) : intval( $label_term );
		}
	}

	if ( $use_mec_api && $mec_main ) {
		/*
		 * Use MEC's internal API to create events.
		 *
		 * `$main->save_event( $data )` accepts an associative array with:
		 * - 'post'  => array of wp_insert_post() compatible fields
		 * - 'meta'  => array of MEC post meta keys
		 * - 'terms' => array of taxonomy => term_id[] assignments
		 *
		 * This populates:
		 * - The `mec-events` CPT post in wp_posts.
		 * - The `mec_events` custom table (if it exists).
		 * - All MEC-internal meta keys.
		 * - Taxonomy term assignments.
		 *
		 * @see MEC_main::save_event() in modern-events-calendar-lite/app/libraries/main.php
		 */
		$mec_data = array(
			'post'  => array(
				'post_title'   => $event['title'],
				'post_content' => $event['content'],
				'post_status'  => 'publish',
				'post_type'    => 'mec-events',
			),
			'meta'  => array(
				'mec_start_date'         => $event['start_date'],
				'mec_end_date'           => $event['end_date'],
				'mec_start_time_hour'    => $event['start_hour'],
				'mec_start_time_minutes' => $event['start_minutes'],
				'mec_start_time_ampm'    => $event['start_ampm'],
				'mec_end_time_hour'      => $event['end_hour'],
				'mec_end_time_minutes'   => $event['end_minutes'],
				'mec_end_time_ampm'      => $event['end_ampm'],
			),
			'terms' => array(),
		);

		if ( $loc_term_id > 0 ) {
			$mec_data['terms']['mec_location'] = array( $loc_term_id );
		}
		if ( $org_term_id > 0 ) {
			$mec_data['terms']['mec_organizer'] = array( $org_term_id );
		}
		if ( ! empty( $cat_term_ids ) ) {
			$mec_data['terms']['mec_category'] = $cat_term_ids;
		}
		if ( ! empty( $label_term_ids ) ) {
			$mec_data['terms']['mec_label'] = $label_term_ids;
		}

		$event_id = $mec_main->save_event( $mec_data );

		if ( $event_id && ! is_wp_error( $event_id ) ) {
			error_log( 'GPEI-MEC: Created event via MEC API: "' . $event['title'] . '" (ID: ' . $event_id . ')' );
		} else {
			error_log( 'GPEI-MEC: MEC API save_event failed for "' . $event['title'] . '", falling back to wp_insert_post.' );
			// Fall through to the wp_insert_post fallback below.
			$use_mec_api_for_this = false;
		}

		// Skip the fallback if API succeeded.
		if ( $event_id && ! is_wp_error( $event_id ) ) {
			continue;
		}
	}

	/*
	 * Fallback: Create event via wp_insert_post() with explicit meta keys.
	 *
	 * This approach is sufficient for creating posts that export correctly
	 * via WordPress's Tools > Export. The resulting WXR file will contain
	 * all the meta keys needed by the GatherPress migration adapter.
	 *
	 * However, MEC's admin UI may not fully recognise these events because
	 * MEC's custom table (`mec_events`) is not populated. This is acceptable
	 * for export/migration testing purposes.
	 */
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

	error_log( 'GPEI-MEC: Created event via wp_insert_post: "' . $event['title'] . '" (ID: ' . $event_id . ')' );

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
	 */

	// Assign categories.
	if ( ! empty( $cat_term_ids ) ) {
		$result = wp_set_object_terms( $event_id, $cat_term_ids, 'mec_category' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to assign categories to event ' . $event_id . ': ' . $result->get_error_message() );
		}
	}

	// Assign labels.
	if ( ! empty( $label_term_ids ) ) {
		$result = wp_set_object_terms( $event_id, $label_term_ids, 'mec_label' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to assign labels to event ' . $event_id . ': ' . $result->get_error_message() );
		}
	}

	// Assign location.
	if ( $loc_term_id > 0 ) {
		$result = wp_set_object_terms( $event_id, array( $loc_term_id ), 'mec_location' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-MEC: Failed to assign location to event ' . $event_id . ': ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-MEC: Assigned location "' . $event['location_slug'] . '" (term ID: ' . $loc_term_id . ') to event ' . $event_id );
		}
	}

	// Assign organizer.
	if ( $org_term_id > 0 ) {
		$result = wp_set_object_terms( $event_id, array( $org_term_id ), 'mec_organizer' );
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
