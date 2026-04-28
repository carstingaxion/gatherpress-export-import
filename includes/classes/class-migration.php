<?php
/**
 * Main migration orchestrator (singleton).
 *
 * Registers all source adapters, hooks into the WordPress import process,
 * and dispatches to the appropriate adapter for post type rewriting,
 * meta stashing, and datetime conversion.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Migration' ) ) {
	/**
	 * Class Migration.
	 *
	 * Singleton orchestrator that manages source adapter registration,
	 * WordPress import hook integration, and meta data processing.
	 * Intercepts the standard WordPress XML import to transform
	 * third-party event plugin data into GatherPress format.
	 *
	 * @since 0.1.0
	 */
	class Migration {

		/**
		 * Singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @var Migration|null
		 */
		private static ?Migration $instance = null;

		/**
		 * Registered source adapters.
		 *
		 * @since 0.1.0
		 *
		 * @var Source_Adapter[]
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
		 * Tracks post IDs that have already been processed for datetime conversion.
		 *
		 * Prevents duplicate processing when multiple hooks fire for the
		 * same post during a single import run.
		 *
		 * @since 0.1.0
		 *
		 * @var array<int, bool>
		 */
		private array $processed_posts = array();

		/**
		 * Stashes a meta key/value pair into a per-post transient.
		 *
		 * Shared helper used by both the main migration class (for event meta)
		 * and the `Venue_Detail_Handler` trait (for venue detail meta). Stores
		 * the value in a transient keyed by `$prefix . $object_id` and tracks
		 * the post ID in a pending list transient keyed by `$pending_key`.
		 *
		 * Returns `true` to short-circuit `add_post_metadata`, preventing the
		 * meta from being saved to `wp_postmeta`.
		 *
		 * @since 0.2.0
		 *
		 * @param int    $object_id   The post ID receiving the meta.
		 * @param string $meta_key    The meta key to stash.
		 * @param mixed  $meta_value  The meta value to stash.
		 * @param string $prefix      Transient key prefix (e.g., 'gpei_meta_stash_').
		 * @param string $pending_key Transient key for the pending ID list
		 *                            (e.g., 'gpei_pending_event_ids').
		 * @return true Always returns true to block normal meta saving.
		 */
		public static function stash_meta( int $object_id, string $meta_key, $meta_value, string $prefix, string $pending_key ): bool {
			$transient_key = $prefix . $object_id;
			$stash         = get_transient( $transient_key );
			if ( ! is_array( $stash ) ) {
				$stash = array();
			}
			$stash[ $meta_key ] = $meta_value;
			set_transient( $transient_key, $stash, HOUR_IN_SECONDS );

			// Track this post ID for deferred processing at import_end.
			$pending = get_transient( $pending_key );
			if ( ! is_array( $pending ) ) {
				$pending = array();
			}
			if ( ! in_array( $object_id, $pending, true ) ) {
				$pending[] = $object_id;
				set_transient( $pending_key, $pending, HOUR_IN_SECONDS );
			}

			return true;
		}

		/**
		 * Gets the singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @return Migration The singleton instance.
		 */
		public static function get_instance(): Migration {
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
		 * third-party event plugins. Adapters that need additional
		 * import hooks set them up internally via their own methods.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function register_default_adapters(): void {
			$this->register_adapter( new TEC_Adapter() );
			$this->register_adapter( new Events_Manager_Adapter() );
			$this->register_adapter( new MEC_Adapter() );
			$this->register_adapter( new EventON_Adapter() );
			$this->register_adapter( new AIOEC_Adapter() );
			$this->register_adapter( new Event_Organiser_Adapter() );
		}

		/**
		 * Registers a source adapter.
		 *
		 * Adds the adapter to the internal registry, invalidates
		 * the cached type maps so they will be rebuilt on next access,
		 * and calls `setup_import_hooks()` on the adapter if it
		 * implements the hookable adapter interface.
		 *
		 * @since 0.1.0
		 *
		 * @param Source_Adapter $adapter The adapter instance to register.
		 * @return void
		 */
		public function register_adapter( Source_Adapter $adapter ): void {
			$this->adapters[] = $adapter;

			// Invalidate caches when a new adapter is registered.
			$this->event_type_map  = null;
			$this->venue_type_map  = null;
			$this->stash_meta_keys = null;
			$this->taxonomy_map    = null;

			// Allow adapters to register their own import hooks.
			if ( $adapter instanceof Hookable_Adapter ) {
				$adapter->setup_import_hooks();
			}
		}

		/**
		 * Gets all registered adapters.
		 *
		 * @since 0.1.0
		 *
		 * @return Source_Adapter[] Array of registered adapter instances.
		 */
		public function get_adapters(): array {
			return $this->adapters;
		}

		/**
		 * Sets up WordPress hooks for import interception.
		 *
		 * Hooks into the WordPress import process at strategic points:
		 * - `wp_import_post_data_raw` at priority 5 for post type rewriting
		 * - `wp_import_post_terms` at priority 5 for taxonomy rewriting in post terms
		 * - `pre_insert_term` at priority 5 for intercepting taxonomy term creation
		 * - `add_post_metadata` at priority 5 for meta stashing
		 * - `gatherpress_pseudopostmetas` for pseudopostmeta registration
		 * - `wp_import_post_meta` at priority 20 for meta counting and deferred processing
		 * - `import_end` for processing all remaining stashed meta after the entire import
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function setup_hooks(): void {
			add_filter( 'wp_import_post_data_raw', array( $this, 'rewrite_post_type_on_import' ), 5 );
			add_filter( 'wp_import_post_terms', array( $this, 'rewrite_post_terms_taxonomy' ), 5 );
			add_filter( 'pre_insert_term', array( $this, 'intercept_term_creation' ), 5, 2 );
			add_filter( 'add_post_metadata', array( $this, 'stash_meta_on_import' ), 5, 5 );
			add_filter( 'gatherpress_pseudopostmetas', array( $this, 'register_pseudopostmetas' ) );
			add_filter( 'wp_import_post_meta', array( $this, 'filter_post_meta_on_import' ), 20, 3 );
			add_action( 'import_end', array( $this, 'process_all_remaining_stashes' ) );
		}

		/**
		 * Gets the merged event post type map from all adapters.
		 *
		 * Builds a combined map of all source event post types to their
		 * GatherPress equivalents. The result is cached and filterable
		 * via the `gpei_event_post_type_map` filter.
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
		 * Builds a combined map of all source venue post types to their
		 * GatherPress equivalents. The result is cached and filterable
		 * via the `gpei_venue_post_type_map` filter.
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
		 * and filterable via the `gpei_taxonomy_map` filter.
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
			 * Filters the merged taxonomy mapping used during import.
			 *
			 * This filter controls how source plugin taxonomy slugs are rewritten
			 * to GatherPress or WordPress taxonomy slugs during the import process.
			 * It is applied in two places:
			 *
			 * 1. In `rewrite_post_terms_taxonomy()` — rewrites the `domain` field
			 *    in per-post term assignments so terms are assigned to the correct
			 *    target taxonomy on the imported post.
			 * 2. In `intercept_term_creation()` — intercepts top-level `<wp:term>`
			 *    entries in the WXR file, creates the term in the target taxonomy,
			 *    and blocks creation in the source taxonomy.
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
		 * Intercepts term creation during the WordPress import.
		 *
		 * Hooked to `pre_insert_term` at priority 5. The WordPress Importer
		 * calls `wp_insert_term()` for each term in the WXR file. This filter
		 * fires before the term is inserted into the database, allowing us to:
		 *
		 * 1. Rewrite taxonomy slugs — if the taxonomy is in our taxonomy map,
		 *    the term is silently blocked here (by returning a WP_Error) and
		 *    re-inserted under the correct GatherPress taxonomy.
		 * 2. Delegate to adapters — for special cases like Event Organiser's
		 *    `event-venue` taxonomy, the adapter's own `pre_insert_term` hook
		 *    handles the term at an earlier priority.
		 *
		 * @since 0.1.0
		 *
		 * @param string $term     The term name being inserted.
		 * @param string $taxonomy The taxonomy slug for the term.
		 * @return string|\WP_Error The term name to proceed, or WP_Error to block insertion.
		 */
		public function intercept_term_creation( $term, string $taxonomy ) {
			$tax_map = $this->get_taxonomy_map();

			if ( empty( $tax_map ) || ! isset( $tax_map[ $taxonomy ] ) ) {
				return $term;
			}

			$target_taxonomy = $tax_map[ $taxonomy ];

			// Check if the target taxonomy exists.
			if ( ! taxonomy_exists( $target_taxonomy ) ) {
				return $term;
			}

			// Check if the term already exists in the target taxonomy.
			$existing = term_exists( $term, $target_taxonomy );
			if ( ! $existing ) {
				wp_insert_term( $term, $target_taxonomy );
			}

			// Return a WP_Error to prevent insertion into the original
			// (non-existent) taxonomy. The importer logs this as a skip.
			return new \WP_Error(
				'gpei_taxonomy_rewritten',
				sprintf(
					/* translators: 1: term name, 2: source taxonomy, 3: target taxonomy */
					__( 'Term "%1$s" rewritten from "%2$s" to "%3$s".', 'gatherpress-export-import' ),
					$term,
					$taxonomy,
					$target_taxonomy
				)
			);
		}

		/**
		 * Rewrites taxonomy slugs in the per-post term assignments during import.
		 *
		 * Hooked to `wp_import_post_terms` at priority 5. The WordPress Importer
		 * calls this filter with the array of terms assigned to each post being
		 * imported. This method rewrites the `domain` (taxonomy) field to match
		 * the GatherPress equivalent based on the merged taxonomy map.
		 *
		 * Adapter-specific taxonomy handling (such as Event Organiser's
		 * `event-venue` two-pass strategy) is handled by the adapters
		 * themselves via their own hooks registered at earlier priorities.
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
				$post_data['post_type']         = $event_map[ $source_type ];
				$post_data['_gpei_source_type'] = $source_type;
				return $post_data;
			}

			$venue_map = $this->get_venue_post_type_map();
			if ( isset( $venue_map[ $source_type ] ) ) {
				$post_data['post_type']         = $venue_map[ $source_type ];
				$post_data['_gpei_source_type'] = $source_type;
				return $post_data;
			}

			return $post_data;
		}

		/**
		 * Intercepts add_post_metadata to stash third-party meta values during import.
		 *
		 * Hooked to `add_post_metadata` at priority 5. When a recognized meta key
		 * is being added to a `gatherpress_event` or `gatherpress_venue` post,
		 * the value is stored in a transient instead of being written to
		 * `wp_postmeta`. This prevents the original meta from polluting the
		 * database while collecting all values needed for datetime conversion
		 * and venue information assembly.
		 *
		 * Delegates to the shared `stash_meta()` helper to avoid duplicating
		 * the transient storage and pending ID tracking logic that is also
		 * used by the `Venue_Detail_Handler` trait.
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

			if ( 'gatherpress_event' !== $post_type && 'gatherpress_venue' !== $post_type ) {
				return $check;
			}

			// Venue meta stashing is handled by adapter-specific hooks
			// (e.g., Venue_Detail_Handler::vdh_stash_venue_meta_on_import at priority 4).
			// If we reach here for a venue post, let the adapter handle it.
			if ( 'gatherpress_venue' === $post_type ) {
				return $check;
			}

			return self::stash_meta( $object_id, $meta_key, $meta_value, 'gpei_meta_stash_', 'gpei_pending_event_ids' );
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
		 * Filters post meta during WordPress import and records the post for deferred processing.
		 *
		 * Hooked to `wp_import_post_meta` at priority 20. The WordPress Importer
		 * calls this filter with the full array of post meta BEFORE calling
		 * `add_post_meta()` for each individual key. We use this to ensure the
		 * post ID is tracked in the pending list so `import_end` can process it.
		 *
		 * Note: We do NOT process the stash here because the individual
		 * `add_post_meta()` calls (which trigger `stash_meta_on_import()`)
		 * happen AFTER this filter returns. Processing is deferred to
		 * `import_end` via `process_all_remaining_stashes()`.
		 *
		 * The actual pending ID tracking is now handled by `stash_meta()` inside
		 * `stash_meta_on_import()`, so this method serves as a safety net for
		 * events that might have stash-eligible meta but where the individual
		 * `add_post_meta` calls haven't yet fired.
		 *
		 * @since 0.1.0
		 *
		 * @param array $postmeta Array of post meta arrays to import.
		 * @param int   $post_id  The post ID being imported.
		 * @param array $post     The full post data array from the importer.
		 * @return array The unmodified post meta array.
		 */
		public function filter_post_meta_on_import( array $postmeta, int $post_id, array $post ): array {

			if ( 'gatherpress_event' !== $post['post_type'] ) {
				return $postmeta;
			}

			// Ensure this post ID is in the pending list. The stash_meta() helper
			// also adds it, but this covers the case where filter_post_meta_on_import
			// fires before any individual add_post_meta calls.
			$pending = get_transient( 'gpei_pending_event_ids' );
			if ( ! is_array( $pending ) ) {
				$pending = array();
			}
			if ( ! in_array( $post_id, $pending, true ) ) {
				$pending[] = $post_id;
				set_transient( 'gpei_pending_event_ids', $pending, HOUR_IN_SECONDS );
			}

			return $postmeta;
		}

		/**
		 * Processes all remaining stashed meta after the entire import completes.
		 *
		 * Hooked to `import_end`. At this point, ALL posts have been created
		 * and ALL meta keys have been processed via `add_post_meta()` (which
		 * triggers our `stash_meta_on_import()` to populate the transient stashes).
		 *
		 * This method iterates through all pending event post IDs and runs
		 * datetime conversion and venue linking for each.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function process_all_remaining_stashes(): void {
			$pending = get_transient( 'gpei_pending_event_ids' );

			if ( ! is_array( $pending ) || empty( $pending ) ) {
				return;
			}

			foreach ( $pending as $post_id ) {
				$this->process_stashed_meta( (int) $post_id );
			}

			// Clean up the pending list.
			delete_transient( 'gpei_pending_event_ids' );
		}

		/**
		 * Processes stashed meta for a single gatherpress_event post.
		 *
		 * Finds the appropriate adapter via `can_handle()` and delegates
		 * datetime conversion. Also resolves venue ID mapping and delegates
		 * venue linking to the appropriate adapter.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The post ID of the imported event.
		 * @return void
		 */
		public function process_stashed_meta( int $post_id ): void {
			// Prevent duplicate processing.
			if ( isset( $this->processed_posts[ $post_id ] ) ) {
				return;
			}

			$post_type = get_post_type( $post_id );

			if ( 'gatherpress_event' !== $post_type ) {
				return;
			}

			$transient_key = 'gpei_meta_stash_' . $post_id;
			$stash         = get_transient( $transient_key );

			if ( ! is_array( $stash ) || empty( $stash ) ) {
				return;
			}

			// Mark as processed to prevent duplicate calls.
			$this->processed_posts[ $post_id ] = true;

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
