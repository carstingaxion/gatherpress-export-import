<?php
/**
 * The Events Calendar (StellarWP) adapter.
 *
 * Handles import conversion for `tribe_events` and `tribe_venue` post types.
 * TEC stores event dates in `_EventStartDate` / `_EventEndDate` meta keys
 * as 'Y-m-d H:i:s' local time, with `_EventTimezone` for the timezone string.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_GPM_TEC_Adapter' ) ) {
	/**
	 * Class Telex_GPM_TEC_Adapter.
	 *
	 * Source adapter for The Events Calendar (StellarWP).
	 * Converts tribe_events to gatherpress_event and tribe_venue
	 * to gatherpress_venue during WordPress XML import.
	 *
	 * @since 0.1.0
	 *
	 * @see Telex_GPM_Source_Adapter
	 * @see Telex_GPM_Datetime_Helper
	 */
	class Telex_GPM_TEC_Adapter implements Telex_GPM_Source_Adapter {

		use Telex_GPM_Datetime_Helper;

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
		 * Gets the meta keys that should be stashed during import.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Meta keys for date/time and timezone.
		 */
		public function get_stash_meta_keys(): array {
			return array(
				'_EventStartDate',
				'_EventEndDate',
				'_EventTimezone',
			);
		}

		/**
		 * Gets pseudopostmeta definitions for TEC meta keys.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, array{post_type: string, import_callback: callable}> Pseudopostmeta definitions.
		 */
		public function get_pseudopostmetas(): array {
			$callback = array( $this, 'noop_callback' );

			return array(
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
		 * No-op callback for pseudopostmeta registration.
		 *
		 * This callback is registered with pseudopostmeta definitions but
		 * intentionally does nothing. The actual meta processing is handled
		 * by the stash mechanism in the main migration class.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $post_id    The post ID.
		 * @param mixed $meta_value The meta value.
		 * @return void
		 */
		public function noop_callback( int $post_id, $meta_value ): void {
			// Intentionally empty; meta is handled via stash mechanism.
		}
	}
}
