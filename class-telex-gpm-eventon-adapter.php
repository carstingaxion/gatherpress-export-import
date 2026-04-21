<?php
/**
 * EventON adapter.
 *
 * Handles import conversion for the `ajde_events` post type. EventON
 * stores event dates as Unix timestamps in the `evcal_srow` (start)
 * and `evcal_erow` (end) meta keys.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_GPM_EventON_Adapter' ) ) {
	/**
	 * Class Telex_GPM_EventON_Adapter.
	 *
	 * Source adapter for EventON. Converts `ajde_events` to
	 * `gatherpress_event` during WordPress XML import. EventON
	 * does not use a dedicated venue CPT; venue data is stored
	 * via taxonomy terms and meta fields.
	 *
	 * @since 0.1.0
	 *
	 * @see Telex_GPM_Source_Adapter
	 * @see Telex_GPM_Datetime_Helper
	 */
	class Telex_GPM_EventON_Adapter implements Telex_GPM_Source_Adapter {

		use Telex_GPM_Datetime_Helper;

		/**
		 * Gets the human-readable name of the source plugin.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source plugin name.
		 */
		public function get_name(): string {
			return 'EventON';
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
				'ajde_events' => 'gatherpress_event',
			);
		}

		/**
		 * Gets the venue post type mapping.
		 *
		 * EventON does not use a custom post type for venues.
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
		 * @return string[] Meta keys for Unix timestamp-based start and end times.
		 */
		public function get_stash_meta_keys(): array {
			return array(
				'evcal_srow',
				'evcal_erow',
			);
		}

		/**
		 * Gets pseudopostmeta definitions for EventON meta keys.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, array{post_type: string, import_callback: callable}> Pseudopostmeta definitions.
		 */
		public function get_pseudopostmetas(): array {
			$callback = array( $this, 'noop_callback' );

			return array(
				'evcal_srow' => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
				'evcal_erow' => array(
					'post_type'       => 'gatherpress_event',
					'import_callback' => $callback,
				),
			);
		}

		/**
		 * Determines if the given stash data belongs to this adapter.
		 *
		 * Checks for the presence of the `evcal_srow` meta key,
		 * which is unique to EventON.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $stash The collected meta key/value pairs.
		 * @return bool True if EventON meta keys are present.
		 */
		public function can_handle( array $stash ): bool {
			return isset( $stash['evcal_srow'] );
		}

		/**
		 * Converts the stashed EventON meta data into GatherPress datetimes.
		 *
		 * Converts Unix timestamps from `evcal_srow` and `evcal_erow` into
		 * 'Y-m-d H:i:s' format using the site's configured timezone.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $post_id The post ID of the imported event.
		 * @param array<string, mixed> $stash   The collected meta key/value pairs.
		 * @return void
		 */
		public function convert_datetimes( int $post_id, array $stash ): void {
			$start_ts = isset( $stash['evcal_srow'] ) ? intval( $stash['evcal_srow'] ) : 0;
			$end_ts   = isset( $stash['evcal_erow'] ) ? intval( $stash['evcal_erow'] ) : 0;

			if ( 0 === $start_ts ) {
				return;
			}

			if ( 0 === $end_ts ) {
				$end_ts = $start_ts;
			}

			$timezone       = $this->get_default_timezone();
			$tz             = new \DateTimeZone( $timezone );
			$datetime_start = ( new \DateTime( '@' . $start_ts ) )->setTimezone( $tz )->format( 'Y-m-d H:i:s' );
			$datetime_end   = ( new \DateTime( '@' . $end_ts ) )->setTimezone( $tz )->format( 'Y-m-d H:i:s' );

			$this->save_gatherpress_datetimes( $post_id, $datetime_start, $datetime_end, $timezone );
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * EventON does not use a meta key for venue references.
		 *
		 * @since 0.1.0
		 *
		 * @return null Always null for EventON.
		 */
		public function get_venue_meta_key(): ?string {
			return null;
		}

		/**
		 * Gets the taxonomy mapping for EventON.
		 *
		 * Maps EventON's event_type taxonomy to GatherPress topic.
		 * Location and organizer taxonomies are specific to EventON
		 * and have no direct GatherPress equivalent.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Taxonomy map.
		 */
		public function get_taxonomy_map(): array {
			return array(
				'event_type' => 'gatherpress_topic',
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
