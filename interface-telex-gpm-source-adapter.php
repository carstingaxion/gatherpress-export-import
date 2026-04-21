<?php
/**
 * Interface for third-party event plugin source adapters.
 *
 * Every adapter must implement this interface so the main migration
 * class can interact with it in a uniform way. Each adapter encapsulates
 * the knowledge of how a specific third-party event plugin stores its
 * data and how to convert that data into GatherPress format.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'Telex_GPM_Source_Adapter' ) ) {
	/**
	 * Interface Telex_GPM_Source_Adapter.
	 *
	 * Defines the contract that all third-party event plugin adapters
	 * must implement for the GatherPress migration system.
	 *
	 * @since 0.1.0
	 */
	interface Telex_GPM_Source_Adapter {

		/**
		 * Gets the human-readable name of the source plugin.
		 *
		 * Used for display in the admin UI and logging.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source plugin name.
		 */
		public function get_name(): string;

		/**
		 * Gets the event post type mapping for the source plugin.
		 *
		 * Returns an associative array where keys are source event post type
		 * slugs and values are the corresponding GatherPress post type slugs.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Associative array of source_post_type => gatherpress_post_type.
		 */
		public function get_event_post_type_map(): array;

		/**
		 * Gets the venue post type mapping for the source plugin.
		 *
		 * Returns an associative array where keys are source venue post type
		 * slugs and values are the corresponding GatherPress post type slugs.
		 * Returns an empty array if the source plugin does not use a venue CPT.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Associative array of source_post_type => gatherpress_post_type.
		 */
		public function get_venue_post_type_map(): array;

		/**
		 * Gets the meta keys that should be stashed during import.
		 *
		 * These keys will be intercepted via the `add_post_metadata` filter
		 * and stored in a transient for later processing by the adapter's
		 * `convert_datetimes()` method.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Flat array of meta key strings.
		 */
		public function get_stash_meta_keys(): array;

		/**
		 * Gets pseudopostmeta definitions for the source plugin.
		 *
		 * Returns an associative array where keys are meta key strings and
		 * values are arrays containing 'post_type' and 'import_callback' entries,
		 * compatible with GatherPress's pseudopostmeta system.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, array{post_type: string, import_callback: callable}> Pseudopostmeta definitions.
		 */
		public function get_pseudopostmetas(): array;

		/**
		 * Determines if the given stash data belongs to this adapter.
		 *
		 * Inspects the collected meta key/value pairs to determine whether
		 * the data originated from this adapter's source plugin. Used to
		 * differentiate between plugins that share the same post type slug.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $stash The collected meta key/value pairs.
		 * @return bool True if this adapter can handle the stash data, false otherwise.
		 */
		public function can_handle( array $stash ): bool;

		/**
		 * Converts the stashed meta data into GatherPress datetimes.
		 *
		 * Reads source-specific date/time values from the stash and saves them
		 * to the GatherPress `gp_event_extended` table via `Event::save_datetimes()`.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $post_id The post ID of the imported event.
		 * @param array<string, mixed> $stash   The collected meta key/value pairs.
		 * @return void
		 */
		public function convert_datetimes( int $post_id, array $stash ): void;

		/**
		 * Gets the meta key used for venue linking, if any.
		 *
		 * Returns the meta key that stores the venue reference on an event post
		 * (e.g., '_EventVenueID' for The Events Calendar). Returns null if the
		 * source plugin does not use a meta key for venue linking.
		 *
		 * @since 0.1.0
		 *
		 * @return string|null The meta key, or null if not applicable.
		 */
		public function get_venue_meta_key(): ?string;

		/**
		 * Links a venue to an event after import.
		 *
		 * Called after venue ID mapping is resolved. The adapter should use
		 * GatherPress native methods to establish the venue-event relationship.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id      The event post ID.
		 * @param int $new_venue_id The new (mapped) venue post ID.
		 * @return void
		 */
		public function link_venue( int $post_id, int $new_venue_id ): void;

		/**
		 * Gets the taxonomy mapping for the source plugin.
		 *
		 * Returns an associative array where keys are source taxonomy slugs
		 * and values are the corresponding GatherPress (or WordPress) taxonomy
		 * slugs. Used during import to rewrite taxonomy names so that terms
		 * from the source plugin are assigned to the correct GatherPress taxonomy.
		 *
		 * Returns an empty array if no taxonomy rewriting is needed.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Associative array of source_taxonomy => target_taxonomy.
		 */
		public function get_taxonomy_map(): array;
	}
}
