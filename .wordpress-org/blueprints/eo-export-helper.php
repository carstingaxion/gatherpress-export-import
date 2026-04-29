<?php
/**
 * Plugin Name: EO Export Helper
 * Description: Registers Event Organiser term meta fields so they are included in WordPress WXR exports.
 * Version:     1.0.0
 * Author:      GatherPress Export Import
 *
 * Event Organiser stores venue details (address, city, state, postcode,
 * country, latitude, longitude) as term meta on the `event-venue` taxonomy
 * via its own internal functions (eo_update_venue_meta, etc.), but it
 * never calls register_term_meta() to formally register these keys.
 *
 * WordPress's built-in WXR exporter (Tools > Export) includes term meta
 * only for keys that are registered via register_meta() or register_term_meta().
 * By registering these fields here, we ensure they appear in the exported
 * XML file, making venue address data available for migration.
 *
 * Known EO venue term meta keys (stored in wp_termmeta):
 * - _venue_address   — Street address
 * - _venue_city      — City name
 * - _venue_state     — State or province
 * - _venue_postcode  — Postal/ZIP code
 * - _venue_country   — Country code (2-letter ISO 3166-1 alpha-2)
 * - _venue_lat       — Latitude coordinate
 * - _venue_lng       — Longitude coordinate
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function (): void {
		// Only register if the event-venue taxonomy exists (EO is active).
		if ( ! taxonomy_exists( 'event-venue' ) ) {
			return;
		}

		$venue_meta_keys = array(
			'_venue_address'  => array(
				'type'        => 'string',
				'description' => 'Venue street address.',
			),
			'_venue_city'     => array(
				'type'        => 'string',
				'description' => 'Venue city.',
			),
			'_venue_state'    => array(
				'type'        => 'string',
				'description' => 'Venue state or province.',
			),
			'_venue_postcode' => array(
				'type'        => 'string',
				'description' => 'Venue postal/ZIP code.',
			),
			'_venue_country'  => array(
				'type'        => 'string',
				'description' => 'Venue country code (ISO 3166-1 alpha-2).',
			),
			'_venue_lat'      => array(
				'type'        => 'number',
				'description' => 'Venue latitude coordinate.',
			),
			'_venue_lng'      => array(
				'type'        => 'number',
				'description' => 'Venue longitude coordinate.',
			),
		);

		foreach ( $venue_meta_keys as $meta_key => $args ) {
			register_term_meta(
				'event-venue',
				$meta_key,
				array(
					'type'              => $args['type'],
					'description'       => $args['description'],
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'string' === $args['type'] ? 'sanitize_text_field' : null,
				)
			);
		}
	},
	20
);
