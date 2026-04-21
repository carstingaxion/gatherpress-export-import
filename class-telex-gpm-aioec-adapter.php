<?php
/**
 * All-in-One Event Calendar adapter.
 *
 * Handles import conversion for the `ai1ec_event` post type.
 * Note: AIOEC stores most event data in a custom database table
 * (`ai1ec_events`), not in post meta. This adapter primarily handles
 * the post type rewrite. Datetime conversion from WXR postmeta is
 * limited because the source data is not available in standard meta.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_GPM_AIOEC_Adapter' ) ) {
	/**
	 * Class Telex_GPM_AIOEC_Adapter.
	 *
	 * Source adapter for All-in-One Event Calendar. Converts `ai1ec_event`
	 * to `gatherpress_event` during WordPress XML import. Because AIOEC
	 * stores event data in a custom table, datetime conversion requires
	 * manual mapping beyond standard WXR import capabilities.
	 *
	 * @since 0.1.0
	 *
	 * @see Telex_GPM_Source_Adapter
	 * @see Telex_GPM_Datetime_Helper
	 */
	class Telex_GPM_AIOEC_Adapter implements Telex_GPM_Source_Adapter {

		use Telex_GPM_Datetime_Helper;

		/**
		 * Gets the human-readable name of the source plugin.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source plugin name.
		 */
		public function get_name(): string {
			return 'All-in-One Event Calendar';
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
				'ai1ec_event' => 'gatherpress_event',
			);
		}

		/**
		 * Gets the venue post type mapping.
		 *
		 * AIOEC does not use a custom post type for venues;
		 * venue data is stored in a custom database table.
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
		 * AIOEC stores event data in a custom table, so there are no
		 * standard meta keys to stash.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Empty array; no meta keys to stash.
		 */
		public function get_stash_meta_keys(): array {
			return array();
		}

		/**
		 * Gets pseudopostmeta definitions.
		 *
		 * AIOEC has no standard postmeta to register as pseudopostmeta.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, array{post_type: string, import_callback: callable}> Empty array.
		 */
		public function get_pseudopostmetas(): array {
			return array();
		}

		/**
		 * Determines if the given stash data belongs to this adapter.
		 *
		 * Always returns false because AIOEC stores data in custom tables,
		 * not in postmeta. This adapter primarily handles post type rewriting.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $stash The collected meta key/value pairs.
		 * @return bool Always false.
		 */
		public function can_handle( array $stash ): bool {
			return false;
		}

		/**
		 * Converts the stashed meta data into GatherPress datetimes.
		 *
		 * No-op for AIOEC because datetime conversion requires custom table
		 * access, which is beyond standard WXR import capabilities.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $post_id The post ID of the imported event.
		 * @param array<string, mixed> $stash   The collected meta key/value pairs.
		 * @return void
		 */
		public function convert_datetimes( int $post_id, array $stash ): void {
			// AIOEC datetime conversion requires custom table access,
			// which is beyond standard WXR import capabilities.
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * AIOEC does not use a meta key for venue references.
		 *
		 * @since 0.1.0
		 *
		 * @return null Always null for AIOEC.
		 */
		public function get_venue_meta_key(): ?string {
			return null;
		}

		/**
		 * Links a venue to an event after import.
		 *
		 * No-op for AIOEC because it does not use a standard venue post type.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id      The event post ID.
		 * @param int $new_venue_id The new (mapped) venue post ID.
		 * @return void
		 */
		public function link_venue( int $post_id, int $new_venue_id ): void {
			// AIOEC does not use a standard venue post type.
		}

		/**
		 * Gets the taxonomy mapping for All-in-One Event Calendar.
		 *
		 * Maps AIOEC's custom taxonomies to GatherPress equivalents.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Taxonomy map.
		 */
		public function get_taxonomy_map(): array {
			return array(
				'events_categories' => 'gatherpress_topic',
				'events_tags'       => 'post_tag',
			);
		}
	}
}
