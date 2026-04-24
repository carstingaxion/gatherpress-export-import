<?php
/**
 * Events Manager adapter.
 *
 * Handles import conversion for `event` and `location` post types as used
 * by the Events Manager plugin. Stores dates in `_event_start` / `_event_end`
 * meta keys in 'Y-m-d H:i:s' format with `_event_timezone` for the timezone.
 *
 * Venue details are stored as individual post meta keys on `location` posts:
 * `_location_address`, `_location_town`, `_location_state`, `_location_postcode`,
 * `_location_country`. These are intercepted during import via the shared
 * `Venue_Detail_Handler` trait, assembled into a full address string, and saved
 * as `gatherpress_venue_information` on the converted `gatherpress_venue` post.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Events_Manager_Adapter' ) ) {
	/**
	 * Class Events_Manager_Adapter.
	 *
	 * Source adapter for Events Manager. Converts `event` to
	 * `gatherpress_event` and `location` to `gatherpress_venue`
	 * during WordPress XML import. Also converts Events Manager
	 * venue detail meta into GatherPress venue information JSON
	 * via the shared `Venue_Detail_Handler` trait.
	 *
	 * @since 0.1.0
	 *
	 * @see Source_Adapter
	 * @see Hookable_Adapter
	 * @see Datetime_Helper
	 * @see Venue_Detail_Handler
	 */
	class Events_Manager_Adapter implements Hookable_Adapter, Source_Adapter {

		use Datetime_Helper;
		use Venue_Detail_Handler;

		/**
		 * Gets the human-readable name of the source plugin.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source plugin name.
		 */
		public function get_name(): string {
			return 'Events Manager';
		}

		/**
		 * Gets the event post type mapping.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Event post type map.
		 */
		public function get_event_post_type_map(): array {
			return array(
				'event' => 'gatherpress_event',
			);
		}

		/**
		 * Gets the venue post type mapping.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Venue post type map.
		 */
		public function get_venue_post_type_map(): array {
			return array(
				'location' => 'gatherpress_venue',
			);
		}

		/**
		 * Gets the mapping of Events Manager venue meta keys to address components.
		 *
		 * Maps Events Manager's individual location meta keys to the component
		 * types understood by the `Venue_Detail_Handler` trait.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Map of source_meta_key => component_type.
		 */
		protected function get_venue_detail_meta_map(): array {
			return array(
				'_location_address'  => 'address',
				'_location_town'     => 'city',
				'_location_state'    => 'state',
				'_location_postcode' => 'zip',
				'_location_country'  => 'country',
			);
		}

		/**
		 * Gets the meta keys that should be stashed during import.
		 *
		 * Includes both event datetime meta keys and venue detail meta keys.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Meta keys for date/time, timezone, and venue details.
		 */
		public function get_stash_meta_keys(): array {
			return array_merge(
				array(
					'_event_start',
					'_event_end',
					'_event_timezone',
				),
				$this->get_venue_detail_meta_keys()
			);
		}

		/**
		 * Gets pseudopostmeta definitions for Events Manager meta keys.
		 *
		 * Registers both event datetime keys and venue detail keys as
		 * pseudopostmeta entries so GatherPress recognises them during
		 * its own import/export flow.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, array{post_type: string, import_callback: callable}> Pseudopostmeta definitions.
		 */
		public function get_pseudopostmetas(): array {
			$callback = array( $this, 'noop_callback' );

			$pseudometas = array(
				'_event_start'    => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_event_end'      => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_event_timezone' => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
			);

			// Register venue detail keys as pseudopostmeta for gatherpress_venue.
			foreach ( $this->get_venue_detail_meta_keys() as $key ) {
				$pseudometas[ $key ] = array(
					'post_type'       => 'gatherpress_venue',
					'import_callback' => $callback,
				);
			}

			return $pseudometas;
		}

		/**
		 * Determines if the given stash data belongs to this adapter.
		 *
		 * Checks for the presence of the `_event_start` meta key,
		 * which is specific to Events Manager (as opposed to Event Organiser).
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $stash The collected meta key/value pairs.
		 * @return bool True if Events Manager meta keys are present.
		 */
		public function can_handle( array $stash ): bool {
			return isset( $stash['_event_start'] );
		}

		/**
		 * Converts the stashed Events Manager meta data into GatherPress datetimes.
		 *
		 * Reads `_event_start`, `_event_end`, and `_event_timezone` from the
		 * stash and saves them via the GatherPress Event class.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $post_id The post ID of the imported event.
		 * @param array<string, mixed> $stash   The collected meta key/value pairs.
		 * @return void
		 */
		public function convert_datetimes( int $post_id, array $stash ): void {
			$start    = isset( $stash['_event_start'] ) ? $stash['_event_start'] : '';
			$end      = isset( $stash['_event_end'] ) ? $stash['_event_end'] : '';
			$timezone = isset( $stash['_event_timezone'] ) ? $stash['_event_timezone'] : $this->get_default_timezone();

			if ( empty( $start ) ) {
				return;
			}

			if ( empty( $end ) ) {
				$end = $start;
			}

			if ( ! is_string( $start ) || ! is_string( $end ) || ! is_string( $timezone ) ) {
				return;
			}

			$this->save_gatherpress_datetimes( $post_id, $start, $end, $timezone );
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * Events Manager does not use a meta key for venue references;
		 * venues are linked via the `location` post type relationship.
		 *
		 * @since 0.1.0
		 *
		 * @return null Always null for Events Manager.
		 */
		public function get_venue_meta_key(): ?string {
			return null;
		}

		/**
		 * Gets the taxonomy mapping for Events Manager.
		 *
		 * Maps Events Manager's event-categories and event-tags taxonomies
		 * to the GatherPress topic taxonomy and standard post tags respectively.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Taxonomy map.
		 */
		public function get_taxonomy_map(): array {
			return array(
				'event-categories' => 'gatherpress_topic',
				'event-tags'       => 'post_tag',
			);
		}

		/**
		 * Sets up adapter-specific import hooks.
		 *
		 * Delegates venue detail meta stashing and processing to the shared
		 * `Venue_Detail_Handler` trait via `setup_venue_detail_hooks()`.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function setup_import_hooks(): void {
			$this->setup_venue_detail_hooks();
		}

		/**
		 * No-op callback for pseudopostmeta registration.
		 *
		 * This callback is registered with pseudopostmeta definitions but
		 * intentionally does nothing. The actual meta processing is handled
		 * by the stash mechanism in the main migration class.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function noop_callback(): void {
			// Intentionally empty; meta is handled via stash mechanism.
		}
	}
}
