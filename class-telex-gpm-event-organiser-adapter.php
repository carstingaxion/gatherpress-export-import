<?php
/**
 * Event Organiser (Stephen Harris) adapter.
 *
 * Handles import conversion for the `event` post type as used by Event Organiser.
 * Event Organiser stores schedule data in `_eventorganiser_schedule_start_datetime`
 * and `_eventorganiser_schedule_end_datetime` meta keys in 'Y-m-d H:i:s' format.
 *
 * Venues are stored as terms of the `event-venue` taxonomy rather than a
 * separate post type, so venue post type mapping is not applicable.
 *
 * Note: Event Organiser shares the `event` post type slug with Events Manager.
 * The adapter distinguishes itself via meta key detection in `can_handle()`.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_GPM_Event_Organiser_Adapter' ) ) {
	/**
	 * Class Telex_GPM_Event_Organiser_Adapter.
	 *
	 * Source adapter for Event Organiser by Stephen Harris. Converts the
	 * `event` post type to `gatherpress_event` during WordPress XML import.
	 * Differentiates from Events Manager (which uses the same CPT slug) by
	 * inspecting for Event Organiser-specific meta keys.
	 *
	 * @since 0.1.0
	 *
	 * @see Telex_GPM_Source_Adapter
	 * @see Telex_GPM_Datetime_Helper
	 */
	class Telex_GPM_Event_Organiser_Adapter implements Telex_GPM_Source_Adapter {

		use Telex_GPM_Datetime_Helper;

		/**
		 * Gets the human-readable name of the source plugin.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source plugin name.
		 */
		public function get_name(): string {
			return 'Event Organiser (Stephen Harris)';
		}

		/**
		 * Gets the event post type mapping.
		 *
		 * Event Organiser uses the `event` post type. Because Events Manager
		 * also uses `event`, the main migration class deduplicates the map.
		 * Adapter differentiation happens in `can_handle()`.
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
		 * Event Organiser uses a taxonomy (`event-venue`) for venues,
		 * not a custom post type. No venue post type mapping is needed.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Empty array; no venue CPT.
		 */
		public function get_venue_post_type_map(): array {
			return array();
		}

		/**
		 * Gets the meta keys that should be stashed during import.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Event Organiser schedule meta keys.
		 */
		public function get_stash_meta_keys(): array {
			return array(
				'_eventorganiser_schedule_start_datetime',
				'_eventorganiser_schedule_end_datetime',
				'_eventorganiser_schedule_start_finish',
				'_eventorganiser_schedule_last_start',
				'_eventorganiser_schedule_last_finish',
			);
		}

		/**
		 * Gets pseudopostmeta definitions for Event Organiser meta keys.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, array{post_type: string, import_callback: callable}> Pseudopostmeta definitions.
		 */
		public function get_pseudopostmetas(): array {
			$callback = array( $this, 'noop_callback' );

			return array(
				'_eventorganiser_schedule_start_datetime' => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_eventorganiser_schedule_end_datetime'   => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_eventorganiser_schedule_start_finish'   => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_eventorganiser_schedule_last_start'     => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'_eventorganiser_schedule_last_finish'    => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
			);
		}

		/**
		 * Determines if the given stash data belongs to this adapter.
		 *
		 * Checks for the presence of the `_eventorganiser_schedule_start_datetime`
		 * meta key to distinguish from Events Manager, which also uses the
		 * `event` post type.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $stash The collected meta key/value pairs.
		 * @return bool True if Event Organiser meta keys are present.
		 */
		public function can_handle( array $stash ): bool {
			return isset( $stash['_eventorganiser_schedule_start_datetime'] );
		}

		/**
		 * Converts the stashed Event Organiser meta data into GatherPress datetimes.
		 *
		 * Reads `_eventorganiser_schedule_start_datetime` and
		 * `_eventorganiser_schedule_end_datetime` from the stash. Falls back to
		 * the `_eventorganiser_schedule_start_finish` key if the end datetime
		 * is not available. Uses the site's default timezone since Event Organiser
		 * stores datetimes in local time.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $post_id The post ID of the imported event.
		 * @param array<string, mixed> $stash   The collected meta key/value pairs.
		 * @return void
		 */
		public function convert_datetimes( int $post_id, array $stash ): void {
			$start = isset( $stash['_eventorganiser_schedule_start_datetime'] )
				? $stash['_eventorganiser_schedule_start_datetime']
				: '';

			$end = isset( $stash['_eventorganiser_schedule_end_datetime'] )
				? $stash['_eventorganiser_schedule_end_datetime']
				: '';

			// Fall back to the "finish" variants if the primary keys are absent.
			if ( empty( $end ) && isset( $stash['_eventorganiser_schedule_start_finish'] ) ) {
				$end = $stash['_eventorganiser_schedule_start_finish'];
			}

			if ( empty( $start ) ) {
				return;
			}

			if ( empty( $end ) ) {
				$end = $start;
			}

			// Event Organiser stores datetimes in the site's local timezone.
			$timezone = $this->get_default_timezone();

			$this->save_gatherpress_datetimes( $post_id, $start, $end, $timezone );
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * Event Organiser does not use a venue meta key on the event post.
		 * Venues are taxonomy terms (`event-venue`), which are handled
		 * by the WordPress Importer's taxonomy processing.
		 *
		 * @since 0.1.0
		 *
		 * @return null Always null for Event Organiser.
		 */
		public function get_venue_meta_key(): ?string {
			return null;
		}

		/**
		 * Links a venue to an event after import.
		 *
		 * No-op for Event Organiser because venues are taxonomy terms,
		 * not posts. The WXR importer handles taxonomy term assignment.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id      The event post ID.
		 * @param int $new_venue_id The new (mapped) venue post ID.
		 * @return void
		 */
		public function link_venue( int $post_id, int $new_venue_id ): void {
			// No-op: Event Organiser venues are taxonomy terms,
			// not posts. The WXR importer handles taxonomy assignment.
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
