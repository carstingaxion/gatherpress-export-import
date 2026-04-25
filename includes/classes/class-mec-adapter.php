<?php
/**
 * Modern Events Calendar (Webnus) adapter.
 *
 * Handles import conversion for the `mec-events` post type. MEC stores
 * dates as 'Y-m-d' strings and times as separate hour, minute, and AM/PM
 * fields in post meta.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\MEC_Adapter' ) ) {
	/**
	 * Class MEC_Adapter.
	 *
	 * Source adapter for Modern Events Calendar (Webnus).
	 * Converts `mec-events` to `gatherpress_event` during WordPress XML import.
	 * MEC does not use a venue CPT; venues are stored as taxonomy terms.
	 *
	 * @since 0.1.0
	 *
	 * @see Source_Adapter
	 * @see Datetime_Helper
	 */
	class MEC_Adapter implements Hookable_Adapter, Source_Adapter, Taxonomy_Venue_Adapter {

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
			return 'Modern Events Calendar';
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
				'mec-events' => 'gatherpress_event',
			);
		}

		/**
		 * Gets the venue post type mapping.
		 *
		 * MEC uses taxonomy terms for venues, not a custom post type.
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
		 * @return string[] Meta keys for date and separate time component fields.
		 */
		public function get_stash_meta_keys(): array {
			return array(
				'mec_start_date',
				'mec_end_date',
				'mec_start_time_hour',
				'mec_start_time_minutes',
				'mec_start_time_ampm',
				'mec_end_time_hour',
				'mec_end_time_minutes',
				'mec_end_time_ampm',
			);
		}

		/**
		 * Gets pseudopostmeta definitions for MEC meta keys.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, array{post_type: string, import_callback: callable}> Pseudopostmeta definitions.
		 */
		public function get_pseudopostmetas(): array {
			$callback = array( $this, 'noop_callback' );

			return array(
				'mec_start_date'         => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'mec_end_date'           => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'mec_start_time_hour'    => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'mec_start_time_minutes' => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'mec_start_time_ampm'    => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'mec_end_time_hour'      => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'mec_end_time_minutes'   => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'mec_end_time_ampm'      => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
			);
		}

		/**
		 * Determines if the given stash data belongs to this adapter.
		 *
		 * Checks for the presence of the `mec_start_date` meta key,
		 * which is unique to Modern Events Calendar.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $stash The collected meta key/value pairs.
		 * @return bool True if MEC meta keys are present.
		 */
		public function can_handle( array $stash ): bool {
			return isset( $stash['mec_start_date'] );
		}

		/**
		 * Converts the stashed MEC meta data into GatherPress datetimes.
		 *
		 * Combines the separate date and time component fields (hour, minute,
		 * AM/PM) into 'Y-m-d H:i:s' format and saves them via GatherPress.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $post_id The post ID of the imported event.
		 * @param array<string, mixed> $stash   The collected meta key/value pairs.
		 * @return void
		 */
		public function convert_datetimes( int $post_id, array $stash ): void {
			$start_date = isset( $stash['mec_start_date'] ) ? $stash['mec_start_date'] : '';
			$end_date   = isset( $stash['mec_end_date'] ) ? $stash['mec_end_date'] : '';

			if ( empty( $start_date ) ) {
				return;
			}

			if ( empty( $end_date ) ) {
				$end_date = $start_date;
			}

			$start_time = $this->build_time_string(
				isset( $stash['mec_start_time_hour'] ) ? $stash['mec_start_time_hour'] : '12',
				isset( $stash['mec_start_time_minutes'] ) ? $stash['mec_start_time_minutes'] : '00',
				isset( $stash['mec_start_time_ampm'] ) ? $stash['mec_start_time_ampm'] : 'AM'
			);

			$end_time = $this->build_time_string(
				isset( $stash['mec_end_time_hour'] ) ? $stash['mec_end_time_hour'] : '12',
				isset( $stash['mec_end_time_minutes'] ) ? $stash['mec_end_time_minutes'] : '00',
				isset( $stash['mec_end_time_ampm'] ) ? $stash['mec_end_time_ampm'] : 'AM'
			);

			$start    = $start_date . ' ' . $start_time;
			$end      = $end_date . ' ' . $end_time;
			$timezone = $this->get_default_timezone();

			if ( ! is_string( $start ) || ! is_string( $end ) || ! is_string( $timezone ) ) {
				return;
			}

			$this->save_gatherpress_datetimes( $post_id, $start, $end, $timezone );
		}

		/**
		 * Gets the source taxonomy slug used for venues.
		 *
		 * MEC uses the `mec_location` taxonomy for venues.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source venue taxonomy slug.
		 */
		public function get_venue_taxonomy_slug(): string {
			return 'mec_location';
		}

		/**
		 * Gets the source event post type slug(s) that should be skipped during Pass 1.
		 *
		 * MEC uses the `mec-events` post type.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Array of source event post type slugs.
		 */
		public function get_skippable_event_post_types(): array {
			return array( 'mec-events' );
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * MEC uses taxonomy terms for venues, not a meta key reference.
		 *
		 * @since 0.1.0
		 *
		 * @return null Always null for MEC.
		 */
		public function get_venue_meta_key(): ?string {
			return null;
		}

		/**
		 * Gets the taxonomy mapping for Modern Events Calendar.
		 *
		 * Maps MEC's custom taxonomies to GatherPress equivalents.
		 * `mec_category` maps to `gatherpress_topic`, and `mec_label`
		 * maps to `post_tag` as a reasonable default.
		 *
		 * Note: `mec_location` is NOT mapped here because it requires
		 * special two-pass handling via the Taxonomy_Venue_Handler trait.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Taxonomy map.
		 */
		public function get_taxonomy_map(): array {
			return array(
				'mec_category' => 'gatherpress_topic',
				'mec_label'    => 'post_tag',
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

		/**
		 * Converts MEC 12-hour time components to a 24-hour time string.
		 *
		 * Takes separate hour, minute, and AM/PM values and produces
		 * a time string in 'H:i:s' format suitable for database storage.
		 *
		 * @since 0.1.0
		 *
		 * @param string $hour    Hour value (1-12).
		 * @param string $minutes Minutes value (00-59).
		 * @param string $ampm    AM or PM indicator.
		 * @return string Time in 'H:i:s' format.
		 */
		private function build_time_string( string $hour, string $minutes, string $ampm ): string {
			$hour    = intval( $hour );
			$minutes = str_pad( intval( $minutes ), 2, '0', STR_PAD_LEFT );

			if ( strtoupper( $ampm ) === 'PM' && $hour < 12 ) {
				$hour += 12;
			} elseif ( strtoupper( $ampm ) === 'AM' && 12 === $hour ) {
				$hour = 0;
			}

			return str_pad( $hour, 2, '0', STR_PAD_LEFT ) . ':' . $minutes . ':00';
		}
	}
}
