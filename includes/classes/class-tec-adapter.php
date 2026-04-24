<?php
/**
 * The Events Calendar (StellarWP) adapter.
 *
 * Handles import conversion for `tribe_events` and `tribe_venue` post types.
 * TEC stores event dates in `_EventStartDate` / `_EventEndDate` meta keys
 * as 'Y-m-d H:i:s' local time, with `_EventTimezone` for the timezone string.
 *
 * Venue details are stored as individual post meta keys on `tribe_venue` posts:
 * `_VenueAddress`, `_VenueCity`, `_VenueState`, `_VenueZip`, `_VenueCountry`,
 * `_VenuePhone`, `_VenueURL`. These are intercepted during import via the
 * shared `Venue_Detail_Handler` trait, assembled into a full address string,
 * and saved as `gatherpress_venue_information` on the converted
 * `gatherpress_venue` post.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\TEC_Adapter' ) ) {
	/**
	 * Class TEC_Adapter.
	 *
	 * Source adapter for The Events Calendar (StellarWP).
	 * Converts tribe_events to gatherpress_event and tribe_venue
	 * to gatherpress_venue during WordPress XML import. Also converts
	 * TEC venue detail meta into GatherPress venue information JSON
	 * via the shared `Venue_Detail_Handler` trait.
	 *
	 * @since 0.1.0
	 *
	 * @see Source_Adapter
	 * @see Hookable_Adapter
	 * @see Datetime_Helper
	 * @see Venue_Detail_Handler
	 */
	class TEC_Adapter implements Hookable_Adapter, Source_Adapter {

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
			return 'The Events Calendar (StellarWP)';
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
				'tribe_events' => 'gatherpress_event',
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
				'tribe_venue' => 'gatherpress_venue',
			);
		}

		/**
		 * Gets the mapping of TEC venue meta keys to address components.
		 *
		 * Maps TEC's individual venue meta keys to the component types
		 * understood by the `Venue_Detail_Handler` trait.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Map of source_meta_key => component_type.
		 */
		protected function get_venue_detail_meta_map(): array {
			return array(
				'_VenueAddress' => 'address',
				'_VenueCity'    => 'city',
				'_VenueState'   => 'state',
				'_VenueZip'     => 'zip',
				'_VenueCountry' => 'country',
				'_VenuePhone'   => 'phone',
				'_VenueURL'     => 'website',
			);
		}

		/**
		 * Gets the meta keys that should be stashed during import.
		 *
		 * Includes both event datetime meta keys and venue detail meta keys.
		 * The venue meta keys are stashed on `gatherpress_venue` posts and
		 * processed at `import_end` to build GatherPress venue information.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Meta keys for date/time, timezone, and venue details.
		 */
		public function get_stash_meta_keys(): array {
			return array_merge(
				array(
					'_EventStartDate',
					'_EventEndDate',
					'_EventTimezone',
				),
				$this->get_venue_detail_meta_keys()
			);
		}

		/**
		 * Gets pseudopostmeta definitions for TEC meta keys.
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
				'_EventStartDate' => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_EventEndDate'   => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_EventTimezone'  => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_EventVenueID'   => array(
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
		 * Checks for the presence of the `_EventStartDate` meta key,
		 * which is unique to The Events Calendar.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $stash The collected meta key/value pairs.
		 * @return bool True if TEC meta keys are present.
		 */
		public function can_handle( array $stash ): bool {
			return isset( $stash['_EventStartDate'] );
		}

		/**
		 * Converts the stashed TEC meta data into GatherPress datetimes.
		 *
		 * Reads `_EventStartDate`, `_EventEndDate`, and `_EventTimezone` from
		 * the stash and saves them via the GatherPress Event class.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $post_id The post ID of the imported event.
		 * @param array<string, mixed> $stash   The collected meta key/value pairs.
		 * @return void
		 */
		public function convert_datetimes( int $post_id, array $stash ): void {
			$start    = isset( $stash['_EventStartDate'] ) ? $stash['_EventStartDate'] : '';
			$end      = isset( $stash['_EventEndDate'] ) ? $stash['_EventEndDate'] : '';
			$timezone = isset( $stash['_EventTimezone'] ) ? $stash['_EventTimezone'] : $this->get_default_timezone();

			if ( empty( $start ) ) {
				return;
			}

			if ( empty( $end ) ) {
				$end = $start;
			}

			$this->save_gatherpress_datetimes( $post_id, $start, $end, $timezone );
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * @since 0.1.0
		 *
		 * @return string The venue meta key '_EventVenueID'.
		 */
		public function get_venue_meta_key(): ?string {
			return '_EventVenueID';
		}

		/**
		 * Gets the taxonomy mapping for The Events Calendar.
		 *
		 * Maps TEC's custom event category taxonomy to the GatherPress topic
		 * taxonomy. Standard post tags are already compatible.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Taxonomy map.
		 */
		public function get_taxonomy_map(): array {
			return array(
				'tribe_events_cat' => 'gatherpress_topic',
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
