<?php
/**
 * Adapter registry — manages source adapter registration and merged maps.
 *
 * Provides cached, merged views of all registered adapters' post type maps,
 * taxonomy maps, stash meta keys, and pseudopostmeta definitions. Each
 * merged result is built lazily and invalidated when a new adapter is
 * registered.
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Adapter_Registry' ) ) {
	/**
	 * Class Adapter_Registry.
	 *
	 * Stores registered source adapters and exposes merged configuration
	 * maps consumed by the rewriter, stasher, and processor classes.
	 *
	 * @since 0.3.0
	 */
	class Adapter_Registry {

		/**
		 * Registered source adapters.
		 *
		 * @since 0.3.0
		 *
		 * @var Source_Adapter[]
		 */
		private array $adapters = array();

		/**
		 * Cached merged event post type map.
		 *
		 * @since 0.3.0
		 *
		 * @var array<string, string>|null
		 */
		private ?array $event_type_map = null;

		/**
		 * Cached merged venue post type map.
		 *
		 * @since 0.3.0
		 *
		 * @var array<string, string>|null
		 */
		private ?array $venue_type_map = null;

		/**
		 * Cached merged stash meta keys.
		 *
		 * @since 0.3.0
		 *
		 * @var string[]|null
		 */
		private ?array $stash_meta_keys = null;

		/**
		 * Cached merged taxonomy map.
		 *
		 * @since 0.3.0
		 *
		 * @var array<string, string>|null
		 */
		private ?array $taxonomy_map = null;

		/**
		 * Registers a source adapter.
		 *
		 * Adds the adapter to the internal list, invalidates all cached
		 * maps, and calls `setup_import_hooks()` on hookable adapters.
		 *
		 * @since 0.3.0
		 *
		 * @param Source_Adapter $adapter The adapter to register.
		 * @return void
		 */
		public function register( Source_Adapter $adapter ): void {
			$this->adapters[] = $adapter;
			$this->invalidate_caches();

			if ( $adapter instanceof Hookable_Adapter ) {
				$adapter->setup_import_hooks();
			}
		}

		/**
		 * Gets all registered adapters.
		 *
		 * @since 0.3.0
		 *
		 * @return Source_Adapter[]
		 */
		public function get_adapters(): array {
			return $this->adapters;
		}

		/**
		 * Gets the merged event post type map from all adapters.
		 *
		 * @since 0.3.0
		 *
		 * @return array<string, string>
		 */
		public function get_event_post_type_map(): array {
			if ( null === $this->event_type_map ) {
				$this->event_type_map = array();
				foreach ( $this->adapters as $adapter ) {
					$this->event_type_map = array_merge( $this->event_type_map, $adapter->get_event_post_type_map() );
				}
			}

			/**
			 * Filters the merged event post type mapping used during import.
			 *
			 * This filter allows third-party code to add, remove, or modify the
			 * mapping of source event post type slugs to GatherPress post type
			 * slugs. The map is built by merging all registered adapter definitions
			 * and is consulted every time the WordPress Importer processes a post
			 * during `wp_import_post_data_raw`. Any source post type present as a
			 * key in this map will be rewritten to the corresponding value.
			 *
			 * @since 0.1.0
			 *
			 * @example Add support for a custom event post type:
			 *```php
			 *     add_filter( 'gpei_event_post_type_map', function ( array $map ): array {
			 *         $map['my_custom_event'] = 'gatherpress_event';
			 *         return $map;
			 *     } );
			 *```
			 *
			 * @example Remove a built-in mapping to prevent automatic conversion:
			 *```php
			 *     add_filter( 'gpei_event_post_type_map', function ( array $map ): array {
			 *         unset( $map['ai1ec_event'] );
			 *         return $map;
			 *     } );
			 *```
			 *
			 * @param array<string, string> $event_type_map Associative array where keys are
			 *                                              source event post type slugs and
			 *                                              values are GatherPress post type slugs.
			 */
			return apply_filters( 'gpei_event_post_type_map', $this->event_type_map );
		}

		/**
		 * Gets the merged venue post type map from all adapters.
		 *
		 * @since 0.3.0
		 *
		 * @return array<string, string>
		 */
		public function get_venue_post_type_map(): array {
			if ( null === $this->venue_type_map ) {
				$this->venue_type_map = array();
				foreach ( $this->adapters as $adapter ) {
					$this->venue_type_map = array_merge( $this->venue_type_map, $adapter->get_venue_post_type_map() );
				}
			}

			/**
			 * Filters the merged venue post type mapping used during import.
			 *
			 * This filter allows third-party code to add, remove, or modify the
			 * mapping of source venue post type slugs to GatherPress venue post
			 * type slugs. Works identically to `gpei_event_post_type_map` but
			 * for venue/location post types. Source venue posts whose type appears
			 * as a key in this map will be rewritten to `gatherpress_venue`,
			 * and GatherPress will automatically create the corresponding shadow
			 * taxonomy term in `_gatherpress_venue`.
			 *
			 * @since 0.1.0
			 *
			 * @example Add support for a custom venue post type:
			 *```php
			 *     add_filter( 'gpei_venue_post_type_map', function ( array $map ): array {
			 *         $map['my_venue_cpt'] = 'gatherpress_venue';
			 *         return $map;
			 *     } );
			 *```
			 *
			 * @example Remove the Events Manager location mapping if you handle it differently:
			 *```php
			 *     add_filter( 'gpei_venue_post_type_map', function ( array $map ): array {
			 *         unset( $map['location'] );
			 *         return $map;
			 *     } );
			 *```
			 *
			 * @param array<string, string> $venue_type_map Associative array where keys are
			 *                                              source venue post type slugs and
			 *                                              values are GatherPress post type slugs.
			 */
			return apply_filters( 'gpei_venue_post_type_map', $this->venue_type_map );
		}

		/**
		 * Gets the merged stash meta keys from all adapters.
		 *
		 * @since 0.3.0
		 *
		 * @return string[]
		 */
		public function get_stash_meta_keys(): array {
			if ( null === $this->stash_meta_keys ) {
				$this->stash_meta_keys = array();
				foreach ( $this->adapters as $adapter ) {
					$this->stash_meta_keys = array_merge( $this->stash_meta_keys, $adapter->get_stash_meta_keys() );

					$venue_key = $adapter->get_venue_meta_key();
					if ( $venue_key ) {
						$this->stash_meta_keys[] = $venue_key;
					}
				}
				$this->stash_meta_keys = array_unique( $this->stash_meta_keys );
			}

			return $this->stash_meta_keys;
		}

		/**
		 * Gets the merged taxonomy map from all adapters.
		 *
		 * @since 0.3.0
		 *
		 * @return array<string, string>
		 */
		public function get_taxonomy_map(): array {
			if ( null === $this->taxonomy_map ) {
				$this->taxonomy_map = array();
				foreach ( $this->adapters as $adapter ) {
					$this->taxonomy_map = array_merge( $this->taxonomy_map, $adapter->get_taxonomy_map() );
				}
			}

			/**
			 * Filters the merged taxonomy mapping used during import.
			 *
			 * This filter controls how source plugin taxonomy slugs are rewritten
			 * to GatherPress or WordPress taxonomy slugs during the import process.
			 * It is applied in two places:
			 *
			 * 1. In `Taxonomy_Rewriter::rewrite_post_terms_taxonomy()` — rewrites
			 *    the `domain` field in per-post term assignments.
			 * 2. In `Taxonomy_Rewriter::intercept_term_creation()` — intercepts
			 *    top-level `<wp:term>` entries in the WXR file.
			 *
			 * Note: Taxonomy-based venue slugs (e.g., `event-venue` for Event
			 * Organiser) should NOT be added here if they require special two-pass
			 * handling — those are managed by the `Taxonomy_Venue_Handler` trait.
			 *
			 * @since 0.1.0
			 *
			 * @example Map a custom source taxonomy to `gatherpress_topic`:
			 *```php
			 *     add_filter( 'gpei_taxonomy_map', function ( array $map ): array {
			 *         $map['my_event_category'] = 'gatherpress_topic';
			 *         return $map;
			 *     } );
			 *```
			 *
			 * @example Redirect a source tag taxonomy to WordPress core `post_tag`:
			 *```php
			 *     add_filter( 'gpei_taxonomy_map', function ( array $map ): array {
			 *         $map['custom_event_tags'] = 'post_tag';
			 *         return $map;
			 *     } );
			 *```
			 *
			 * @param array<string, string> $taxonomy_map Associative array where keys are
			 *                                            source taxonomy slugs and values
			 *                                            are target taxonomy slugs.
			 */
			return apply_filters( 'gpei_taxonomy_map', $this->taxonomy_map );
		}

		/**
		 * Merges pseudopostmeta definitions from all adapters.
		 *
		 * @since 0.3.0
		 *
		 * @param array<string, array{post_type: string, import_callback: callable}> $pseudopostmetas Existing entries.
		 * @return array<string, array{post_type: string, import_callback: callable}> Merged entries.
		 */
		public function merge_pseudopostmetas( array $pseudopostmetas ): array {
			foreach ( $this->adapters as $adapter ) {
				$pseudopostmetas = array_merge( $pseudopostmetas, $adapter->get_pseudopostmetas() );
			}

			return $pseudopostmetas;
		}

		/**
		 * Invalidates all cached merged maps.
		 *
		 * @since 0.3.0
		 *
		 * @return void
		 */
		private function invalidate_caches(): void {
			$this->event_type_map  = null;
			$this->venue_type_map  = null;
			$this->stash_meta_keys = null;
			$this->taxonomy_map    = null;
		}
	}
}