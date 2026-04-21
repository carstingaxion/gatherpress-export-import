<?php
/**
 * Main migration orchestrator (singleton).
 *
 * Registers all source adapters, hooks into the WordPress import process,
 * and dispatches to the appropriate adapter for post type rewriting,
 * meta stashing, and datetime conversion.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_GatherPress_Migration' ) ) {
	/**
	 * Class Telex_GatherPress_Migration.
	 *
	 * Singleton orchestrator that manages source adapter registration,
	 * WordPress import hook integration, and meta data processing.
	 * Intercepts the standard WordPress XML import to transform
	 * third-party event plugin data into GatherPress format.
	 *
	 * @since 0.1.0
	 */
	class Telex_GatherPress_Migration {

		/**
		 * Singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @var Telex_GatherPress_Migration|null
		 */
		private static ?Telex_GatherPress_Migration $instance = null;

		/**
		 * Registered source adapters.
		 *
		 * @since 0.1.0
		 *
		 * @var Telex_GPM_Source_Adapter[]
		 */
		private array $adapters = array();

		/**
		 * Cached merged event post type map from all adapters.
		 *
		 * @since 0.1.0
		 *
		 * @var array<string, string>|null
		 */
		private ?array $event_type_map = null;

		/**
		 * Cached merged venue post type map from all adapters.
		 *
		 * @since 0.1.0
		 *
		 * @var array<string, string>|null
		 */
		private ?array $venue_type_map = null;

		/**
		 * Cached merged stash meta keys from all adapters.
		 *
		 * @since 0.1.0
		 *
		 * @var string[]|null
		 */
		private ?array $stash_meta_keys = null;

		/**
		 * Cached merged taxonomy map from all adapters.
		 *
		 * @since 0.1.0
		 *
		 * @var array<string, string>|null
		 */
		private ?array $taxonomy_map = null;

		/**
		 * Gets the singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @return Telex_GatherPress_Migration The singleton instance.
		 */
		public static function get_instance(): Telex_GatherPress_Migration {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private constructor — registers adapters and hooks.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			$this->register_default_adapters();
			$this->setup_hooks();
		}

		/**
		 * Prevents cloning of the singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Registers the built-in source adapters.
		 *
		 * Instantiates and registers each adapter for the supported
		 * third-party event plugins.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function register_default_adapters(): void {
			$this->register_adapter( new Telex_GPM_TEC_Adapter() );
			$this->register_adapter( new Telex_GPM_Events_Manager_Adapter() );
			$this->register_adapter( new Telex_GPM_MEC_Adapter() );
			$this->register_adapter( new Telex_GPM_EventON_Adapter() );
			$this->register_adapter( new Telex_GPM_AIOEC_Adapter() );
			$this->register_adapter( new Telex_GPM_Event_Organiser_Adapter() );
		}

		/**
		 * Registers a source adapter.
		 *
		 * Adds the adapter to the internal registry and invalidates
		 * the cached type maps so they will be rebuilt on next access.
		 *
		 * @since 0.1.0
		 *
		 * @param Telex_GPM_Source_Adapter $adapter The adapter instance to register.
		 * @return void
		 */
		public function register_adapter( Telex_GPM_Source_Adapter $adapter ): void {
			$this->adapters[] = $adapter;

			// Invalidate caches when a new adapter is registered.
			$this->event_type_map  = null;
			$this->venue_type_map  = null;
			$this->stash_meta_keys = null;
			$this->taxonomy_map    = null;
		}

		/**
		 * Gets all registered adapters.
		 *
		 * @since 0.1.0
		 *
		 * @return Telex_GPM_Source_Adapter[] Array of registered adapter instances.
		 */
		public function get_adapters(): array {
			return $this->adapters;
		}

		/**
		 * Sets up WordPress hooks for import interception.
		 *
		 * Hooks into the WordPress import process at strategic points:
		 * - `wp_import_post_data_raw` at priority 5 for post type rewriting
		 * - `wp_import_post_data_raw` at priority 4 for taxonomy rewriting in post terms
		 * - `add_post_metadata` at priority 5 for meta stashing
		 * - `gatherpress_pseudopostmetas` for pseudopostmeta registration
		 * - `wp_import_post_meta` at priority 20 for post-import processing
		 * - `save_post_gatherpress_event` at priority 99 as a fallback trigger
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function setup_hooks(): void {
			add_filter( 'wp_import_post_data_raw', array( $this, 'rewrite_post_type_on_import' ), 5 );
			add_filter( 'wp_import_post_terms', array( $this, 'rewrite_post_terms_taxonomy' ), 5 );
			add_filter( 'wp_import_terms', array( $this, 'rewrite_import_terms' ), 5 );
			add_filter( 'add_post_metadata', array( $this, 'stash_meta_on_import' ), 5, 5 );
			add_filter( 'gatherpress_pseudopostmetas', array( $this, 'register_pseudopostmetas' ) );
			add_action( 'wp_import_post_meta', array( $this, 'process_stashed_meta' ), 20 );
			add_action( 'save_post_gatherpress_event', array( $this, 'process_stashed_meta' ), 99 );
		}

		/**
		 * Gets the merged event post type map from all adapters.
		 *
		 * Builds a combined map of all source event post types to their
		 * GatherPress equivalents. The result is cached and filterable
		 * via the `telex_gpm_event_post_type_map` filter.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Combined event post type map.
		 */
		public function get_event_post_type_map(): array {
			if ( null === $this->event_type_map ) {
				$this->event_type_map = array();
				foreach ( $this->adapters as $adapter ) {
					$this->event_type_map = array_merge( $this->event_type_map, $adapter->get_event_post_type_map() );
				}
			}

			/**
			 * Filters the event post type mapping.
			 *
			 * @since 0.1.0
			 *
			 * @param array<string, string> $event_type_map Source-to-GatherPress event post type map.
			 */
			return apply_filters( 'telex_gpm_event_post_type_map', $this->event_type_map );
		}

		/**
		 * Gets the merged venue post type map from all adapters.
		 *
		 * Builds a combined map of all source venue post types to their
		 * GatherPress equivalents. The result is cached and filterable
		 * via the `telex_gpm_venue_post_type_map` filter.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Combined venue post type map.
		 */
		public function get_venue_post_type_map(): array {
			if ( null === $this->venue_type_map ) {
				$this->venue_type_map = array();
				foreach ( $this->adapters as $adapter ) {
					$this->venue_type_map = array_merge( $this->venue_type_map, $adapter->get_venue_post_type_map() );
				}
			}

			/**
			 * Filters the venue post type mapping.
			 *
			 * @since 0.1.0
			 *
			 * @param array<string, string> $venue_type_map Source-to-GatherPress venue post type map.
			 */
			return apply_filters( 'telex_gpm_venue_post_type_map', $this->venue_type_map );
		}

		/**
		 * Gets the merged list of meta keys to stash from all adapters.
		 *
		 * Combines all stash meta keys from registered adapters, including
		 * venue meta keys. The result is cached and deduplicated.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Unique array of meta key strings to intercept.
		 */
		public function get_stash_meta_keys(): array {
			if ( null === $this->stash_meta_keys ) {
				$this->stash_meta_keys = array();
				foreach ( $this->adapters as $adapter ) {
					$this->stash_meta_keys = array_merge( $this->stash_meta_keys, $adapter->get_stash_meta_keys() );

					// Also include venue meta keys for stashing.
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
		 * Builds a combined map of all source taxonomy slugs to their
		 * GatherPress (or WordPress) equivalents. The result is cached
		 * and filterable via the `telex_gpm_taxonomy_map` filter.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Combined taxonomy map.
		 */
		public function get_taxonomy_map(): array {
			if ( null === $this->taxonomy_map ) {
				$this->taxonomy_map = array();
				foreach ( $this->adapters as $adapter ) {
					$this->taxonomy_map = array_merge( $this->taxonomy_map, $adapter->get_taxonomy_map() );
				}
			}

			/**
			 * Filters the taxonomy mapping used during import.
			 *
			 * Allows adding or modifying taxonomy slug rewrites from source
			 * plugins to GatherPress or WordPress taxonomies.
			 *
			 * @since 0.1.0
			 *
			 * @param array<string, string> $taxonomy_map Source-to-target taxonomy map.
			 */
			return apply_filters( 'telex_gpm_taxonomy_map', $this->taxonomy_map );
		}

		/**
		 * Rewrites taxonomy slugs in term data during the WordPress import.
		 *
		 * Hooked to `wp_import_terms` at priority 5. This filter receives the
		 * full array of terms being imported and rewrites the taxonomy slug
		 * for each term that matches the taxonomy map.
		 *
		 * @since 0.1.0
		 *
		 * @param array $terms Array of term data arrays from the WXR file.
		 * @return array Modified term data with rewritten taxonomy slugs.
		 */
		public function rewrite_import_terms( array $terms ): array {
			$tax_map = $this->get_taxonomy_map();

			if ( empty( $tax_map ) ) {
				return $terms;
			}

			foreach ( $terms as &$term ) {
				if ( isset( $term['term_taxonomy'] ) && isset( $tax_map[ $term['term_taxonomy'] ] ) ) {
					$term['term_taxonomy'] = $tax_map[ $term['term_taxonomy'] ];
				}
			}

			return $terms;
		}

		/**
		 * Rewrites taxonomy slugs in the per-post term assignments during import.
		 *
		 * Hooked to `wp_import_post_terms` at priority 5. The WordPress Importer
		 * calls this filter with the array of terms assigned to each post being
		 * imported. This method rewrites the `domain` (taxonomy) field to match
		 * the GatherPress equivalent.
		 *
		 * @since 0.1.0
		 *
		 * @param array $terms Array of term assignment arrays, each with 'domain', 'slug', 'name' keys.
		 * @return array Modified term assignments with rewritten taxonomy domains.
		 */
		public function rewrite_post_terms_taxonomy( array $terms ): array {
			$tax_map = $this->get_taxonomy_map();

			if ( empty( $tax_map ) ) {
				return $terms;
			}

			foreach ( $terms as &$term ) {
				if ( isset( $term['domain'] ) && isset( $tax_map[ $term['domain'] ] ) ) {
					$term['domain'] = $tax_map[ $term['domain'] ];
				}
			}

			return $terms;
		}

		/**
		 * Rewrites third-party post types to GatherPress post types during import.
		 *
		 * Hooked to `wp_import_post_data_raw` at priority 5 (before GatherPress's
		 * own priority 10). Checks the incoming post type against the event and
		 * venue maps, and rewrites it to the corresponding GatherPress type.
		 * Also stashes the original source type for adapter differentiation.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $post_data Raw post data from the importer.
		 * @return array<string, mixed> Modified post data with rewritten post type.
		 */
		public function rewrite_post_type_on_import( array $post_data ): array {
			if ( empty( $post_data['post_type'] ) ) {
				return $post_data;
			}

			$source_type = $post_data['post_type'];

			$event_map = $this->get_event_post_type_map();
			if ( isset( $event_map[ $source_type ] ) ) {
				$post_data['post_type']              = $event_map[ $source_type ];
				$post_data['_telex_gpm_source_type'] = $source_type;
				return $post_data;
			}

			$venue_map = $this->get_venue_post_type_map();
			if ( isset( $venue_map[ $source_type ] ) ) {
				$post_data['post_type']              = $venue_map[ $source_type ];
				$post_data['_telex_gpm_source_type'] = $source_type;
				return $post_data;
			}

			return $post_data;
		}

		/**
		 * Intercepts add_post_metadata to stash third-party meta values during import.
		 *
		 * Hooked to `add_post_metadata` at priority 5. When a recognized meta key
		 * is being added to a `gatherpress_event` post, the value is stored in a
		 * transient instead of being written to `wp_postmeta`. This prevents
		 * the original meta from polluting the database while collecting all
		 * values needed for datetime conversion.
		 *
		 * @since 0.1.0
		 *
		 * @param mixed  $check      Current check value (null to proceed with normal save).
		 * @param int    $object_id  Post ID receiving the meta.
		 * @param string $meta_key   The meta key being added.
		 * @param mixed  $meta_value The meta value being added.
		 * @param bool   $unique     Whether the meta should be unique.
		 * @return mixed True to short-circuit meta saving, or original $check value.
		 */
		public function stash_meta_on_import( $check, int $object_id, string $meta_key, $meta_value, bool $unique ) {
			$stash_keys = $this->get_stash_meta_keys();

			if ( ! in_array( $meta_key, $stash_keys, true ) ) {
				return $check;
			}

			$post_type = get_post_type( $object_id );
			if ( 'gatherpress_event' !== $post_type ) {
				return $check;
			}

			$transient_key = 'telex_gpm_meta_stash_' . $object_id;
			$stash         = get_transient( $transient_key );
			if ( ! is_array( $stash ) ) {
				$stash = array();
			}
			$stash[ $meta_key ] = $meta_value;
			set_transient( $transient_key, $stash, HOUR_IN_SECONDS );

			// Return true to prevent saving to wp_postmeta.
			return true;
		}

		/**
		 * Registers pseudopostmeta entries from all adapters.
		 *
		 * Hooked to `gatherpress_pseudopostmetas`. Merges pseudopostmeta
		 * definitions from all registered adapters into the GatherPress
		 * pseudopostmeta registry.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array{post_type: string, import_callback: callable}> $pseudopostmetas Existing pseudopostmetas.
		 * @return array<string, array{post_type: string, import_callback: callable}> Merged pseudopostmetas.
		 */
		public function register_pseudopostmetas( array $pseudopostmetas ): array {
			foreach ( $this->adapters as $adapter ) {
				$pseudopostmetas = array_merge( $pseudopostmetas, $adapter->get_pseudopostmetas() );
			}

			return $pseudopostmetas;
		}

		/**
		 * Processes stashed meta after a gatherpress_event is fully imported.
		 *
		 * Finds the appropriate adapter via `can_handle()` and delegates
		 * datetime conversion. Also resolves venue ID mapping and delegates
		 * venue linking to the appropriate adapter.
		 *
		 * Hooked to both `wp_import_post_meta` (priority 20) and
		 * `save_post_gatherpress_event` (priority 99) to ensure processing
		 * occurs regardless of import method.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The post ID of the imported event.
		 * @return void
		 */
		public function process_stashed_meta( int $post_id ): void {
			$post_type = get_post_type( $post_id );
			if ( 'gatherpress_event' !== $post_type ) {
				return;
			}

			$transient_key = 'telex_gpm_meta_stash_' . $post_id;
			$stash         = get_transient( $transient_key );
			if ( ! is_array( $stash ) || empty( $stash ) ) {
				return;
			}

			// Find the adapter that can handle this data and convert datetimes.
			foreach ( $this->adapters as $adapter ) {
				if ( $adapter->can_handle( $stash ) ) {
					$adapter->convert_datetimes( $post_id, $stash );
					break;
				}
			}

			// Handle venue linking for any adapter that defines a venue meta key.
			foreach ( $this->adapters as $adapter ) {
				$venue_key = $adapter->get_venue_meta_key();
				if ( $venue_key && isset( $stash[ $venue_key ] ) ) {
					$old_venue_id = intval( $stash[ $venue_key ] );
					$new_venue_id = $this->resolve_venue_id( $old_venue_id );
					if ( $new_venue_id ) {
						$adapter->link_venue( $post_id, $new_venue_id );
					}
					break;
				}
			}

			delete_transient( $transient_key );
		}

		/**
		 * Resolves an old venue ID to a new venue ID using WordPress Importer mapping.
		 *
		 * Checks the global `$wp_import` object's `processed_posts` array
		 * for the old-to-new ID mapping created during the import process.
		 *
		 * @since 0.1.0
		 *
		 * @param int $old_venue_id The original venue post ID from the source site.
		 * @return int The new venue post ID, or 0 if not found.
		 */
		private function resolve_venue_id( int $old_venue_id ): int {
			if ( isset( $GLOBALS['wp_import'] ) && isset( $GLOBALS['wp_import']->processed_posts[ $old_venue_id ] ) ) {
				return intval( $GLOBALS['wp_import']->processed_posts[ $old_venue_id ] );
			}

			return 0;
		}
	}
}
