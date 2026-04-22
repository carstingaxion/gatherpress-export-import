<?php
/**
 * Interface for adapters whose source plugin stores venues as taxonomy terms.
 *
 * Adapters implementing this interface (alongside the Taxonomy_Venue_Handler
 * trait) gain automatic two-pass import support: Pass 1 creates `gatherpress_venue`
 * posts from taxonomy terms and skips events; Pass 2 imports events and links them
 * to the previously created venues via the `_gatherpress_venue` shadow taxonomy.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( __NAMESPACE__ . '\Taxonomy_Venue_Adapter' ) ) {
	/**
	 * Interface Taxonomy_Venue_Adapter.
	 *
	 * Defines the contract for adapters that use taxonomy-based venues
	 * and need two-pass import support via the Taxonomy_Venue_Handler trait.
	 *
	 * @since 0.1.0
	 */
	interface Taxonomy_Venue_Adapter {

		/**
		 * Gets the source taxonomy slug used for venues.
		 *
		 * For example, Event Organiser uses `event-venue`.
		 *
		 * @since 0.1.0
		 *
		 * @return string The source venue taxonomy slug.
		 */
		public function get_venue_taxonomy_slug(): string;

		/**
		 * Gets the source event post type slug(s) that should be skipped during Pass 1.
		 *
		 * Returns the original (pre-rewrite) post type slugs. During Pass 1,
		 * posts of these types are redirected to the temporary skip post type.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Array of source event post type slugs.
		 */
		public function get_skippable_event_post_types(): array;
	}
}
