<?php
/**
 * Event Organiser demo data import script for WordPress Playground.
 *
 * Creates sample events, venues (as taxonomy terms), categories, and tags
 * for Event Organiser migration testing. Uses WordPress core functions
 * since Event Organiser stores all data as standard post meta and taxonomy
 * terms — no custom tables or API classes are required.
 *
 * Event Organiser stores:
 * - Events as `event` CPT posts with schedule meta keys.
 * - Venues as terms of the `event-venue` taxonomy.
 * - Categories as terms of the `event-category` taxonomy.
 * - Tags as terms of the `event-tag` taxonomy.
 *
 * @see https://developer.developer.developer.developer/ — Event Organiser developer docs
 * @see https://developer.developer.developer.developer/function/eo_insert_event/ — EO event creation function
 * @see https://developer.developer.developer.developer/function/eo_insert_venue/ — EO venue creation function
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

error_log( 'GPEI-EO: Starting Event Organiser demo data import.' );

/*
 * -------------------------------------------------------------------------
 * 1. Verify Event Organiser is available and taxonomies are registered.
 * -------------------------------------------------------------------------
 *
 * Event Organiser registers its post type (`event`) and taxonomies
 * (`event-category`, `event-tag`, `event-venue`) during `init`. In
 * WordPress Playground, the plugin should be fully loaded by the time
 * this script runs via `require_once wp-load.php`.
 *
 * If the taxonomies are not registered, we register them manually so
 * the demo data can still be created. This mirrors the approach used
 * by other import scripts (EventON, AIOEC) for plugins that may not
 * be fully active in Playground.
 */
$eo_active = function_exists( 'eventorganiser_register_taxonomy' )
	|| taxonomy_exists( 'event-venue' );

if ( $eo_active ) {
	error_log( 'GPEI-EO: Event Organiser detected as active.' );
} else {
	error_log( 'GPEI-EO: Event Organiser not fully active. Registering post type and taxonomies manually.' );

	if ( ! post_type_exists( 'event' ) ) {
		register_post_type( 'event', array(
			'label'    => 'Events',
			'public'   => true,
			'show_ui'  => true,
			'supports' => array( 'title', 'editor', 'custom-fields' ),
		) );
	}

	if ( ! taxonomy_exists( 'event-category' ) ) {
		register_taxonomy( 'event-category', 'event', array(
			'label'        => 'Event Categories',
			'public'       => true,
			'hierarchical' => true,
		) );
	}

	if ( ! taxonomy_exists( 'event-tag' ) ) {
		register_taxonomy( 'event-tag', 'event', array(
			'label'  => 'Event Tags',
			'public' => true,
		) );
	}

	if ( ! taxonomy_exists( 'event-venue' ) ) {
		register_taxonomy( 'event-venue', 'event', array(
			'label'  => 'Event Venues',
			'public' => true,
		) );
	}
}

/*
 * -------------------------------------------------------------------------
 * 2. Create event categories (event-category taxonomy).
 * -------------------------------------------------------------------------
 */
$categories = array(
	'Lecture'     => 'lecture',
	'Performance' => 'performance',
	'Sprint'      => 'sprint',
);

foreach ( $categories as $name => $slug ) {
	if ( ! term_exists( $name, 'event-category' ) ) {
		$result = wp_insert_term( $name, 'event-category', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-EO: Failed to create category "' . $name . '": ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-EO: Created category "' . $name . '" (term ID: ' . $result['term_id'] . ')' );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 3. Create event tags (event-tag taxonomy).
 * -------------------------------------------------------------------------
 */
$tags = array(
	'Beginner Friendly' => 'beginner-friendly',
	'Evening'           => 'evening',
	'Daytime'           => 'daytime',
);

foreach ( $tags as $name => $slug ) {
	if ( ! term_exists( $slug, 'event-tag' ) ) {
		$result = wp_insert_term( $name, 'event-tag', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-EO: Failed to create tag "' . $name . '": ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-EO: Created tag "' . $name . '" (term ID: ' . $result['term_id'] . ')' );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 4. Create event venue terms (event-venue taxonomy).
 * -------------------------------------------------------------------------
 *
 * Event Organiser stores venues as terms of the `event-venue` taxonomy.
 * Venue address details are stored as term meta, which is NOT included
 * in standard WordPress WXR exports. Only the term name and slug will
 * survive an export/import cycle.
 *
 * The description field IS included in WXR exports, so we set it to
 * the venue address for reference.
 *
 * @see https://developer.developer.developer.developer/function/eo_insert_venue/
 */
$venues = array(
	array(
		'name'        => 'University Lecture Theatre',
		'slug'        => 'university-lecture-theatre',
		'description' => 'University of London, Malet Street, London WC1E 7HU',
	),
	array(
		'name'        => 'The Jazz Cellar',
		'slug'        => 'the-jazz-cellar',
		'description' => '42 Dean Street, Soho, London W1D 4PZ',
	),
	array(
		'name'        => 'Community Hackerspace',
		'slug'        => 'community-hackerspace',
		'description' => '18 Brick Lane, London E1 6RF',
	),
);

$venue_term_ids = array();

foreach ( $venues as $venue ) {
	$existing = term_exists( $venue['slug'], 'event-venue' );
	if ( $existing ) {
		$venue_term_ids[ $venue['slug'] ] = is_array( $existing ) ? intval( $existing['term_id'] ) : intval( $existing );
		error_log( 'GPEI-EO: Venue "' . $venue['name'] . '" already exists (term ID: ' . $venue_term_ids[ $venue['slug'] ] . ')' );
	} else {
		$result = wp_insert_term( $venue['name'], 'event-venue', array(
			'slug'        => $venue['slug'],
			'description' => $venue['description'],
		) );

		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-EO: Failed to create venue "' . $venue['name'] . '": ' . $result->get_error_message() );
			$venue_term_ids[ $venue['slug'] ] = 0;
		} else {
			$venue_term_ids[ $venue['slug'] ] = intval( $result['term_id'] );
			error_log( 'GPEI-EO: Created venue "' . $venue['name'] . '" (term ID: ' . $result['term_id'] . ')' );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 5. Create events.
 * -------------------------------------------------------------------------
 *
 * Event Organiser stores event schedule data in the following post meta keys:
 *
 * - `_eventorganiser_schedule_start_datetime` — Start datetime (Y-m-d H:i:s)
 * - `_eventorganiser_schedule_end_datetime`   — End datetime (Y-m-d H:i:s)
 * - `_eventorganiser_schedule_start_finish`   — Same as end datetime for single occurrences
 * - `_eventorganiser_schedule_last_start`     — Last occurrence start (same as start for non-recurring)
 * - `_eventorganiser_schedule_last_finish`    — Last occurrence end (same as end for non-recurring)
 *
 * Datetimes are stored in site-local time. Event Organiser does not store
 * a per-event timezone — it uses the site's configured timezone.
 *
 * Taxonomy terms are assigned AFTER wp_insert_post() using
 * wp_set_object_terms() to ensure the post exists first. Using `tax_input`
 * in wp_insert_post() can silently fail if the taxonomy is not registered
 * for the post type at insertion time.
 *
 * @see https://developer.developer.developer.developer/function/eo_insert_event/
 */
$events = array(
	array(
		'title'      => 'The History of Open Source Software',
		'content'    => 'A fascinating lecture tracing the origins and evolution of open source software from the 1970s to today.',
		'start'      => '2025-08-28 18:30:00',
		'end'        => '2025-08-28 20:30:00',
		'venue_slug' => 'university-lecture-theatre',
		'categories' => array( 'lecture' ),
		'tags'       => array( 'evening', 'beginner-friendly' ),
	),
	array(
		'title'      => 'Friday Night Live Jazz Session',
		'content'    => 'Live jazz performances every Friday night. This week featuring the Django Reinhardt Tribute Quartet.',
		'start'      => '2025-09-05 20:00:00',
		'end'        => '2025-09-05 23:00:00',
		'venue_slug' => 'the-jazz-cellar',
		'categories' => array( 'performance' ),
		'tags'       => array( 'evening' ),
	),
	array(
		'title'      => 'WordPress Translation Sprint',
		'content'    => 'A collaborative day-long sprint to translate WordPress core and popular plugins into underrepresented languages.',
		'start'      => '2025-09-20 10:00:00',
		'end'        => '2025-09-20 17:00:00',
		'venue_slug' => 'community-hackerspace',
		'categories' => array( 'sprint' ),
		'tags'       => array( 'daytime', 'beginner-friendly' ),
	),
);

foreach ( $events as $event ) {
	/*
	 * Check if Event Organiser's own API is available.
	 *
	 * `eo_insert_event()` handles all internal EO state including the
	 * custom `eo_events` table (if it exists), occurrence generation,
	 * and proper hook firing. When available, prefer it over plain
	 * wp_insert_post().
	 *
	 * @see https://developer.developer.developer.developer/function/eo_insert_event/
	 */
	if ( function_exists( 'eo_insert_event' ) ) {
		$event_id = eo_insert_event(
			array(
				'post_title'   => $event['title'],
				'post_content' => $event['content'],
				'post_status'  => 'publish',
			),
			array(
				'start'    => new DateTime( $event['start'] ),
				'end'      => new DateTime( $event['end'] ),
				'schedule' => 'once',
			)
		);

		if ( is_wp_error( $event_id ) ) {
			error_log( 'GPEI-EO: Failed to create event via eo_insert_event "' . $event['title'] . '": ' . $event_id->get_error_message() );
			continue;
		}

		error_log( 'GPEI-EO: Created event via eo_insert_event: "' . $event['title'] . '" (ID: ' . $event_id . ')' );

	} else {
		/*
		 * Fallback: Create event via wp_insert_post() with all required
		 * Event Organiser schedule meta keys set explicitly.
		 *
		 * This ensures the events appear correctly in a standard WXR export
		 * even when Event Organiser's internal API is not available.
		 */
		$event_id = wp_insert_post( array(
			'post_title'   => $event['title'],
			'post_content' => $event['content'],
			'post_type'    => 'event',
			'post_status'  => 'publish',
			'meta_input'   => array(
				'_eventorganiser_schedule_start_datetime' => $event['start'],
				'_eventorganiser_schedule_end_datetime'   => $event['end'],
				'_eventorganiser_schedule_start_finish'   => $event['end'],
				'_eventorganiser_schedule_last_start'     => $event['start'],
				'_eventorganiser_schedule_last_finish'    => $event['end'],
			),
		) );

		if ( is_wp_error( $event_id ) || 0 === $event_id ) {
			error_log( 'GPEI-EO: Failed to create event via wp_insert_post: "' . $event['title'] . '"' );
			continue;
		}

		error_log( 'GPEI-EO: Created event via wp_insert_post: "' . $event['title'] . '" (ID: ' . $event_id . ')' );
	}

	/*
	 * Assign taxonomy terms AFTER post creation.
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
	if ( ! empty( $event['categories'] ) ) {
		$result = wp_set_object_terms( $event_id, $event['categories'], 'event-category' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-EO: Failed to assign categories to event ' . $event_id . ': ' . $result->get_error_message() );
		}
	}

	// Assign tags.
	if ( ! empty( $event['tags'] ) ) {
		$result = wp_set_object_terms( $event_id, $event['tags'], 'event-tag' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-EO: Failed to assign tags to event ' . $event_id . ': ' . $result->get_error_message() );
		}
	}

	// Assign venue.
	if ( ! empty( $event['venue_slug'] ) && ! empty( $venue_term_ids[ $event['venue_slug'] ] ) ) {
		$venue_term_id = $venue_term_ids[ $event['venue_slug'] ];
		$result        = wp_set_object_terms( $event_id, array( $venue_term_id ), 'event-venue' );
		if ( is_wp_error( $result ) ) {
			error_log( 'GPEI-EO: Failed to assign venue to event ' . $event_id . ': ' . $result->get_error_message() );
		} else {
			error_log( 'GPEI-EO: Assigned venue "' . $event['venue_slug'] . '" (term ID: ' . $venue_term_id . ') to event ' . $event_id );
		}
	}
}

/*
 * -------------------------------------------------------------------------
 * 6. Flush rewrite rules.
 * -------------------------------------------------------------------------
 */
flush_rewrite_rules();

error_log( 'GPEI-EO: Demo data import complete.' );
