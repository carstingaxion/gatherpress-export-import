<?php
/**
 * Event Organiser (Stephen Harris) adapter.
 *
 * Handles import conversion for the `event` post type as used by Event Organiser.
 * Event Organiser stores schedule data in `_eventorganiser_schedule_start_datetime`
 * and `_eventorganiser_schedule_end_datetime` meta keys in 'Y-m-d H:i:s' format.
 *
 * Venues are stored as terms of the `event-venue` taxonomy rather than a
 * separate post type. This adapter uses the shared `Taxonomy_Venue_Handler`
 * trait to implement the two-pass import strategy:
 *
 * - **Pass 1 (Venue creation):** When `event-venue` taxonomy terms are
 *   encountered, they are automatically converted into `gatherpress_venue`
 *   posts. Events in this pass are skipped (not imported).
 * - **Pass 2 (Event import):** On the second import of the same file,
 *   venues already exist as `gatherpress_venue` posts. Events are imported
 *   and linked to venues via the `_gatherpress_venue` shadow taxonomy.
 *
 * Note: Event Organiser shares the `event` post type slug with Events Manager.
 * The adapter distinguishes itself via meta key detection in `can_handle()`.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Event_Organiser_Adapter' ) ) {
	/**
	 * Class Event_Organiser_Adapter.
	 *
	 * Source adapter for Event Organiser by Stephen Harris. Converts the
	 * `event` post type to `gatherpress_event` during WordPress XML import.
	 * Differentiates from Events Manager (which uses the same CPT slug) by
	 * inspecting for Event Organiser-specific meta keys.
	 *
	 * Implements the two-pass import strategy for taxonomy-based venues via
	 * the shared `Taxonomy_Venue_Handler` trait and the
	 * `Taxonomy_Venue_Adapter` interface.
	 *
	 * @since 0.1.0
	 *
	 * @see Source_Adapter
	 * @see Hookable_Adapter
	 * @see Taxonomy_Venue_Adapter
	 * @see Datetime_Helper
	 * @see Taxonomy_Venue_Handler
	 */
	class Event_Organiser_Adapter implements Hookable_Adapter, Source_Adapter, Taxonomy_Venue_Adapter {

		use Datetime_Helper;
		use Taxonomy_Venue_Handler;

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
		 * Gets the source taxonomy slug used for venues.
		 *
		 * Event Organiser uses the `event-venue` taxonomy for venues.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source venue taxonomy slug.
		 */
		public function get_venue_taxonomy_slug(): string {
			return 'event-venue';
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

			if ( empty( $end ) && isset( $stash['_eventorganiser_schedule_start_finish'] ) ) {
				$end = $stash['_eventorganiser_schedule_start_finish'];
			}

			if ( empty( $start ) ) {
				return;
			}

			if ( empty( $end ) ) {
				$end = $start;
			}

			$timezone = $this->get_default_timezone();

			if ( ! is_string( $start ) || ! is_string( $end ) ) {
				return;
			}

			$this->save_gatherpress_datetimes( $post_id, $start, $end, $timezone );
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * Event Organiser does not use a venue meta key on the event post.
		 * Venues are taxonomy terms (`event-venue`), handled by the
		 * two-pass import strategy via the Taxonomy_Venue_Handler trait.
		 *
		 * @since 0.1.0
		 *
		 * @return null Always null for Event Organiser.
		 */
		public function get_venue_meta_key(): ?string {
			return null;
		}

		/**
		 * Gets the taxonomy mapping for Event Organiser.
		 *
		 * Maps Event Organiser's custom taxonomies to GatherPress equivalents:
		 * - `event-category` → `gatherpress_topic`
		 * - `event-tag` → `post_tag`
		 *
		 * Note: `event-venue` is NOT mapped here because it requires special
		 * two-pass handling via the Taxonomy_Venue_Handler trait.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Taxonomy map.
		 */
		public function get_taxonomy_map(): array {
			return array(
				'event-category' => 'gatherpress_topic',
				'event-tag'      => 'post_tag',
			);
		}

		/**
		 * Sets up the import hooks for the two-pass venue/event strategy.
		 *
		 * Delegates to the shared `setup_taxonomy_venue_hooks()` method
		 * provided by the `Taxonomy_Venue_Handler` trait.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function setup_import_hooks(): void {
			$this->setup_taxonomy_venue_hooks();
		}

	}
}
