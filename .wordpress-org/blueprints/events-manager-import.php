<?php
/**
 * Events Manager demo data import script for WordPress Playground.
 *
 * Creates sample events, locations, categories, and tags using the
 * Events Manager plugin's own API classes (`EM_Location`, `EM_Event`)
 * to ensure all internal database tables are properly populated.
 *
 * Plain `wp_insert_post()` is insufficient for Events Manager because
 * the plugin maintains its own custom tables (`wp_em_locations` and
 * `wp_em_events`) that must be populated for events and locations to
 * function correctly in the EM admin UI, shortcodes, and widgets.
 *
 * @see https://developer.wordpress.org/reference/classes/EM_Event/
 * @see https://developer.wordpress.org/reference/classes/EM_Location/
 * @see https://wp-events-plugin.com/documentation/developer-docs/
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

// Ensure Events Manager classes are available.
if ( ! class_exists( 'EM_Location' ) || ! class_exists( 'EM_Event' ) ) {
	error_log( 'GPEI-EM: Events Manager plugin is not active or its classes are not available.' );
	return;
}

/*
 * Force-create Events Manager custom tables if they don't exist.
 *
 * In WordPress Playground, the plugin is installed via the `plugins` array
 * which does not always trigger the full activation hook. EM stores event
 * and location data in custom tables (`wp_em_events`, `wp_em_locations`)
 * that are normally created during plugin activation via `em_activate()`.
 *
 * We load the installer file and call the activation function directly
 * to ensure the tables exist before creating any demo data.
 *
 * @see events-manager/em-install.php  — contains em_activate() / em_install()
 * @see events-manager/em-actions.php  — EM's action handlers
 */
global $wpdb;
$em_events_table    = $wpdb->prefix . 'em_events';
$em_locations_table = $wpdb->prefix . 'em_locations';

$events_table_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $em_events_table ) ) === $em_events_table;
$locations_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $em_locations_table ) ) === $em_locations_table;

if ( ! $events_table_exists || ! $locations_table_exists ) {
	error_log( 'GPEI-EM: Custom tables missing, attempting to create them...' );

	// Try loading the EM installer.
	$em_install_file = WP_PLUGIN_DIR . '/events-manager/em-install.php';
	if ( file_exists( $em_install_file ) ) {
		require_once $em_install_file;
	}

	// Try the activation function (varies by EM version).
	if ( function_exists( 'em_activate' ) ) {
		em_activate();
		error_log( 'GPEI-EM: Called em_activate()' );
	} elseif ( function_exists( 'em_install' ) ) {
		em_install();
		error_log( 'GPEI-EM: Called em_install()' );
	} else {
		/*
		 * Fallback: Create the tables manually using dbDelta().
		 *
		 * These schemas match Events Manager v6.x table structure.
		 * We only create the minimum columns needed for demo data.
		 *
		 * @see events-manager/em-install.php for the full schema
		 */
		error_log( 'GPEI-EM: No EM install function found, creating tables manually via dbDelta().' );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Create em_locations table.
		if ( ! $locations_table_exists ) {
			$sql_locations = "CREATE TABLE {$em_locations_table} (
				location_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				blog_id bigint(20) unsigned DEFAULT NULL,
				location_slug varchar(200) DEFAULT NULL,
				location_name text NULL,
				location_owner bigint(20) unsigned NOT NULL DEFAULT 0,
				location_address varchar(200) DEFAULT NULL,
				location_town varchar(200) DEFAULT NULL,
				location_state varchar(200) DEFAULT NULL,
				location_postcode varchar(10) DEFAULT NULL,
				location_region varchar(200) DEFAULT NULL,
				location_country varchar(2) DEFAULT NULL,
				location_latitude float(10,6) DEFAULT NULL,
				location_longitude float(10,6) DEFAULT NULL,
				location_status int(1) DEFAULT NULL,
				location_language varchar(14) DEFAULT NULL,
				location_translation bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (location_id),
				KEY post_id (post_id),
				KEY blog_id (blog_id)
			) {$charset_collate};";
			dbDelta( $sql_locations );
		}

		// Create em_events table.
		if ( ! $events_table_exists ) {
			$sql_events = "CREATE TABLE {$em_events_table} (
				event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				blog_id bigint(20) unsigned DEFAULT NULL,
				event_slug varchar(200) DEFAULT NULL,
				event_owner bigint(20) unsigned NOT NULL DEFAULT 0,
				event_name text NULL,
				event_start_time time DEFAULT NULL,
				event_end_time time DEFAULT NULL,
				event_start_date date DEFAULT NULL,
				event_end_date date DEFAULT NULL,
				event_start datetime DEFAULT NULL,
				event_end datetime DEFAULT NULL,
				event_all_day int(1) DEFAULT NULL,
				event_timezone varchar(100) DEFAULT NULL,
				post_content longtext NULL,
				event_rsvp bool NOT NULL DEFAULT 0,
				event_rsvp_date date DEFAULT NULL,
				event_rsvp_time time DEFAULT NULL,
				event_rsvp_spaces int(5) DEFAULT NULL,
				event_spaces int(5) DEFAULT NULL,
				event_private bool NOT NULL DEFAULT 0,
				location_id bigint(20) unsigned DEFAULT NULL,
				event_status int(1) DEFAULT NULL,
				event_date_created datetime DEFAULT NULL,
				event_date_modified datetime DEFAULT NULL,
				recurrence_id bigint(20) unsigned DEFAULT NULL,
				recurrence bigint(1) unsigned DEFAULT 0,
				recurrence_interval int(4) DEFAULT NULL,
				recurrence_freq tinytext DEFAULT NULL,
				recurrence_byday tinytext DEFAULT NULL,
				recurrence_byweekno int(4) DEFAULT NULL,
				recurrence_days int(3) DEFAULT NULL,
				recurrence_rsvp_days int(3) DEFAULT NULL,
				event_language varchar(14) DEFAULT NULL,
				event_translation bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (event_id),
				KEY post_id (post_id),
				KEY blog_id (blog_id),
				KEY location_id (location_id),
				KEY event_start (event_start),
				KEY event_end (event_end),
				KEY event_status (event_status)
			) {$charset_collate};";
			dbDelta( $sql_events );
		}
	}

	// Verify tables now exist.
	$events_table_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $em_events_table ) ) === $em_events_table;
	$locations_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $em_locations_table ) ) === $em_locations_table;

	error_log( 'GPEI-EM: After install — em_events table exists: ' . ( $events_table_exists ? 'YES' : 'NO' ) );
	error_log( 'GPEI-EM: After install — em_locations table exists: ' . ( $locations_table_exists ? 'YES' : 'NO' ) );

	if ( ! $events_table_exists || ! $locations_table_exists ) {
		error_log( 'GPEI-EM: FATAL — Could not create EM custom tables. Aborting demo data import.' );
		return;
	}
}

error_log( 'GPEI-EM: Custom tables verified, proceeding with demo data creation.' );
























/*
 * Create event categories.
 *
 * Events Manager uses the `event-categories` taxonomy.
 */
if ( ! term_exists( 'Music', 'event-categories' ) ) {
	wp_insert_term( 'Music', 'event-categories', array( 'slug' => 'music' ) );
}
if ( ! term_exists( 'Sports', 'event-categories' ) ) {
	wp_insert_term( 'Sports', 'event-categories', array( 'slug' => 'sports' ) );
}
if ( ! term_exists( 'Education', 'event-categories' ) ) {
	wp_insert_term( 'Education', 'event-categories', array( 'slug' => 'education' ) );
}

/*
 * Create event tags.
 *
 * Events Manager uses the `event-tags` taxonomy.
 */
if ( ! term_exists( 'outdoor', 'event-tags' ) ) {
	wp_insert_term( 'outdoor', 'event-tags', array( 'slug' => 'outdoor' ) );
}
if ( ! term_exists( 'family-friendly', 'event-tags' ) ) {
	wp_insert_term( 'family-friendly', 'event-tags', array( 'slug' => 'family-friendly' ) );
}
if ( ! term_exists( 'free-entry', 'event-tags' ) ) {
	wp_insert_term( 'free-entry', 'event-tags', array( 'slug' => 'free-entry' ) );
}

/*
 * Create locations using EM_Location.
 *
 * EM_Location::save() populates both the `wp_posts` table (as a `location`
 * post type) and the `wp_em_locations` custom table. Setting properties
 * directly on the object before calling save() is the documented approach.
 *
 * @see EM_Location::save() in events-manager/classes/em-location.php
 */

// Location 1: Central Park Amphitheatre.
$location1 = new \EM_Location();
$location1->location_name     = 'Central Park Amphitheatre';
$location1->location_slug     = 'central-park-amphitheatre';
$location1->location_address  = '830 5th Ave';
$location1->location_town     = 'New York';
$location1->location_state    = 'NY';
$location1->location_postcode = '10065';
$location1->location_country  = 'US';
$location1->location_region   = '';
$location1->location_status   = 1;
$location1->post_content      = '';
$location1->save();
$loc1_id = $location1->location_id;

// Location 2: Lakeside Sports Complex.
$location2 = new \EM_Location();
$location2->location_name     = 'Lakeside Sports Complex';
$location2->location_slug     = 'lakeside-sports-complex';
$location2->location_address  = '200 Lake Shore Blvd';
$location2->location_town     = 'Chicago';
$location2->location_state    = 'IL';
$location2->location_postcode = '60601';
$location2->location_country  = 'US';
$location2->location_region   = '';
$location2->location_status   = 1;
$location2->post_content      = '';
$location2->save();
$loc2_id = $location2->location_id;

// Location 3: Public Library Main Hall.
$location3 = new \EM_Location();
$location3->location_name     = 'Public Library Main Hall';
$location3->location_slug     = 'public-library-main-hall';
$location3->location_address  = '42 Library Lane';
$location3->location_town     = 'San Francisco';
$location3->location_state    = 'CA';
$location3->location_postcode = '94102';
$location3->location_country  = 'US';
$location3->location_region   = '';
$location3->location_status   = 1;
$location3->post_content      = '';
$location3->save();
$loc3_id = $location3->location_id;

/*
 * Create events using EM_Event.
 *
 * EM_Event::save() populates both the `wp_posts` table (as an `event`
 * post type) and the `wp_em_events` custom table. The event datetimes,
 * timezone, and location reference are all set as object properties.
 *
 * Properties used:
 * - event_name: Event title (maps to post_title).
 * - post_content: Event description.
 * - event_start_date / event_start_time: Start datetime components.
 * - event_end_date / event_end_time: End datetime components.
 * - event_timezone: PHP timezone string.
 * - location_id: References an EM_Location by its location_id
 *   (from the wp_em_locations table, NOT the post ID).
 * - event_status: 1 = published.
 *
 * @see EM_Event::save() in events-manager/classes/em-event.php
 * @see EM_Event::get_post() for how properties map to post fields
 */

/*
 * Event 1: Summer Jazz Festival.
 *
 * Most EM_Event properties (datetimes, timezone, status) are protected
 * and must be set via the `__set()` magic method, which validates format:
 * - Dates must match `/^\d{4}-\d{2}-\d{2}$/`
 * - Times must match `/^\d{2}:\d{2}:\d{2}$/`
 * - Combined start/end accepts a timestamp or parseable datetime string.
 *
 * @see EM_Event::__set() in events-manager/classes/em-event.php
 */
$event1 = new \EM_Event();
$event1->event_name   = 'Summer Jazz Festival';
$event1->post_content = 'An evening of live jazz performances from local and international artists under the stars.';
$event1->__set( 'event_start_date', '2025-07-18' );
$event1->__set( 'event_start_time', '18:00:00' );
$event1->__set( 'event_end_date', '2025-07-18' );
$event1->__set( 'event_end_time', '23:00:00' );
$event1->__set( 'event_start', '2025-07-18 18:00:00' );
$event1->__set( 'event_end', '2025-07-18 23:00:00' );
$event1->event_timezone = 'America/New_York';
$event1->location_id    = $loc1_id;
$event1->event_status   = 1;
$event1->event_rsvp     = false;
$event1->save();

// Assign taxonomy terms after save (EM_Event::save() creates the post).
if ( $event1->post_id ) {
	wp_set_object_terms( $event1->post_id, array( 'music' ), 'event-categories' );
	wp_set_object_terms( $event1->post_id, array( 'outdoor', 'family-friendly' ), 'event-tags' );
}

// Event 2: Community 5K Fun Run.
$event2 = new \EM_Event();
$event2->event_name   = 'Community 5K Fun Run';
$event2->post_content = 'Join the annual community fun run around the lakefront. All fitness levels welcome!';
$event2->__set( 'event_start_date', '2025-08-10' );
$event2->__set( 'event_start_time', '08:00:00' );
$event2->__set( 'event_end_date', '2025-08-10' );
$event2->__set( 'event_end_time', '11:00:00' );
$event2->__set( 'event_start', '2025-08-10 08:00:00' );
$event2->__set( 'event_end', '2025-08-10 11:00:00' );
$event2->event_timezone = 'America/Chicago';
$event2->location_id    = $loc2_id;
$event2->event_status   = 1;
$event2->event_rsvp     = false;
$event2->save();

if ( $event2->post_id ) {
	wp_set_object_terms( $event2->post_id, array( 'sports' ), 'event-categories' );
	wp_set_object_terms( $event2->post_id, array( 'outdoor', 'free-entry' ), 'event-tags' );
}

// Event 3: Open Source Book Club Kickoff.
$event3 = new \EM_Event();
$event3->event_name   = 'Open Source Book Club Kickoff';
$event3->post_content = 'First meeting of our monthly book club focused on open source culture, history, and innovation.';
$event3->__set( 'event_start_date', '2025-09-05' );
$event3->__set( 'event_start_time', '19:00:00' );
$event3->__set( 'event_end_date', '2025-09-05' );
$event3->__set( 'event_end_time', '21:00:00' );
$event3->__set( 'event_start', '2025-09-05 19:00:00' );
$event3->__set( 'event_end', '2025-09-05 21:00:00' );
$event3->event_timezone = 'America/Los_Angeles';
$event3->location_id    = $loc3_id;
$event3->event_status   = 1;
$event3->event_rsvp     = false;
$event3->save();

if ( $event3->post_id ) {
	wp_set_object_terms( $event3->post_id, array( 'education' ), 'event-categories' );
	wp_set_object_terms( $event3->post_id, array( 'free-entry' ), 'event-tags' );
}

flush_rewrite_rules();
