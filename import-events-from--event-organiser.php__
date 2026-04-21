<?php
/*
Plugin Name: GatherPress | Import for 'Event Organiser' Events
Plugin URI:
Description: Use WordPress' native import tool to import data from the 'Event Organiser' plugin into GatherPress.
Version: 0.1.0
Author: Carsten Bach
Author URI: https://gatherpress.org
Text Domain: gatherpress-import-eo-events
Domain Path: /languages
*/
namespace GatherPress\ExportImport\ImportEoEvents;


/**
 * 5. Migrating data from existing event plugins
 *    This should be separate files per plugin to migrate from.
 * 
 * EXAMPLE: Migrate 'Event Organiser' events into GatherPress.
 */

// WordPress Importer (v2)
// https://github.com/humanmade/Wordpress-Importer
if ( class_exists( 'WXR_Importer' ) ) {
	\add_filter( 'wxr_importer.pre_process.post', __NAMESPACE__ . '\\import_eo_events', 9 );

// Default WordPres Importer
// https://github.com/WordPress/wordpress-importer/issues/42
} else {
	\add_filter( 'wp_import_post_data_raw', __NAMESPACE__ . '\\import_eo_events', 9 );
}


function import_eo_events( array $post_data_raw ): array {
	if ( 'eo_event' !== $post_data_raw['post_type'] ) {
		return $post_data_raw;
	}

	\add_filter(
		'gatherpress_pseudopostmetas',
		function ( array $pseudopostmetas ): array {
			$pseudopostmetas['eo_start_date'] = [
				'import_callback' => __NAMESPACE__ . '\\import_eo_start_date_callback',
			];
			return $pseudopostmetas;
		}
	);

	$post_data_raw['post_type'] = 'gatherpress_event';
	return $post_data_raw;
}


/**
 * 4. Misc.
 * 
 * Functions, that could live anywhere inside or outside of GatherPress.
 *
 * @param  int    $post_id
 * @param  mixed $data
 *
 * @return void
 */
function import_eo_start_date_callback( int $post_id, mixed $data ) : void {
	// Save $data into some place, which is not post_meta.
	error_log( 
		var_export(
			[
				__FUNCTION__,
				__FILE__, // Helpful to find origin of debug statements.
				$post_id,
				$data,
			],
			true
		)
	);
	// Save $data into some place, which is not post_meta.
	// ...
	$event = new \GatherPress\Core\Event( $post_id );
	$event->save_datetimes( \maybe_unserialize( $data ) );
}

