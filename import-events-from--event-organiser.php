<?php
/*
Plugin Name: GatherPress | Import for 'Event Organiser' Events
Plugin URI:
Description: Use WordPress' native import tool to import data from the 'Event Oragniser' plugin into GatherPress.
Version: 0.1.0
Author: Carsten Bach
Author URI: https://gatherpress.org
Text Domain: gatherpress-import-eo-events
Domain Path: /languages
*/
namespace GatherPress\ExportImport;


/**
 * 5. Migrating data from existing event plugins
 *    This should be separate files per plugin to migrate from.
 * 
 * EXAMPLE: Migrate 'Event Organiser' events into GatherPress.
 */
\add_filter(
	'wp_import_post_data_raw',
	function( array $post_data_raw ): array {
		if ( 'eo_event' !== $post_data_raw['post_type'] ) {
			return $post_data_raw;
		}

		\add_filter(
			'gatherpress_pseudopostmetas',
			function ( array $pseudopostmetas ): array {
				$pseudopostmetas['eo_start_date'] = [
					'import_callback' => __NAMESPACE__ . '\\import_KEY_callback',
				];
				return $pseudopostmetas;
			}
		);

		$post_data_raw['post_type'] = 'gp_event';
		return $post_data_raw;
	}
);
