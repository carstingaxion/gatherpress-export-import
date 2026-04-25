<?php
/**
 * Shared two-pass taxonomy-based venue import handler.
 *
 * Provides reusable logic for adapters whose source plugin stores venues
 * as taxonomy terms rather than as a dedicated custom post type. Implements
 * the full two-pass import strategy:
 *
 * - **Pass 1:** Creates `gatherpress_venue` posts from taxonomy terms and
 *   silently skips event posts.
 * - **Pass 2:** Imports events and links them to the previously created
 *   venues via the `_gatherpress_venue` shadow taxonomy.
 *
 * Classes using this trait MUST implement the `Taxonomy_Venue_Adapter`
 * interface and the `Hookable_Adapter` interface, and MUST use the
 * `Datetime_Helper` trait (for `get_venue_term_slug()`).
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( __NAMESPACE__ . '\Taxonomy_Venue_Handler' ) ) {
	/**
	 * Trait Taxonomy_Venue_Handler.
	 *
	 * Encapsulates the two-pass import logic for taxonomy-based venues.
	 * Any adapter that uses taxonomy terms for venues can include this
	 * trait to gain automatic two-pass support.
	 *
	 * @since 0.1.0
	 */
	trait Taxonomy_Venue_Handler {

		/**
		 * The temporary post type used to silently skip events during pass 1.
		 *
		 * Registered as a non-public post type so `post_type_exists()` returns
		 * true, preventing the WordPress Importer from logging "Invalid post type"
		 * errors. Posts of this type are cleaned up at `import_end`.
		 *
		 * @since 0.1.0
		 *
		 * @var string
		 */
		private static string $skip_post_type = '_gpei_skip';

		/**
		 * Whether this adapter is currently in venue-creation mode (pass 1).
		 *
		 * When true, events are skipped and only venue taxonomy terms are
		 * converted into gatherpress_venue posts. Determined during the
		 * first call to `tvh_filter_venue_terms()` by checking whether
		 * matching `gatherpress_venue` posts already exist.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $tvh_is_venue_pass = true;

		/**
		 * Whether the venue pass detection has been performed.
		 *
		 * Set to true after the first venue taxonomy term is encountered,
		 * so the pass detection logic only runs once per import.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $tvh_pass_detected = false;

		/**
		 * Whether the import hooks for the two-pass handler have been registered.
		 *
		 * Ensures idempotency — calling `setup_taxonomy_venue_hooks()` multiple
		 * times will not register duplicate hooks.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $tvh_hooks_registered = false;

		/**
		 * The post title of the event currently being imported.
		 *
		 * Set by `tvh_capture_current_post_data()` from the raw post data
		 * BEFORE the importer creates the post.
		 *
		 * @since 0.1.0
		 *
		 * @var string
		 */
		private string $tvh_current_post_title = '';

		/**
		 * The post ID of the last gatherpress_event that was saved.
		 *
		 * Set by `tvh_track_saved_post_id()` when `save_post_gatherpress_event`
		 * fires. Because the WordPress Importer processes terms AFTER
		 * `wp_insert_post()`, this ID is available when
		 * `tvh_filter_venue_terms()` runs.
		 *
		 * @since 0.1.0
		 *
		 * @var int
		 */
		private int $tvh_last_saved_event_id = 0;

		/**
		 * Deferred venue linking map: post_id => array of venue slugs.
		 *
		 * Because the WordPress Importer processes terms AFTER saving the
		 * post (and thus after `save_post` hooks have already fired), we
		 * cannot link venues in `save_post`. Instead, we collect the
		 * mappings here and process them all at `import_end`.
		 *
		 * @since 0.1.0
		 *
		 * @var array<int, string[]>
		 */
		private array $tvh_deferred_venue_links = array();

		/**
		 * Whether the current event post should be skipped during pass 1.
		 *
		 * Set by `tvh_maybe_flag_events_on_venue_pass()` and consumed by
		 * `tvh_skip_flagged_events()`.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $tvh_skip_current_event = false;

		/**
		 * Sets up the import hooks for the two-pass venue/event strategy.
		 *
		 * Called from the adapter's `setup_import_hooks()` method. Registers
		 * hooks for skip post type registration, post data capture, event
		 * skipping, venue term interception, post ID tracking, and deferred
		 * venue linking at `import_end`.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		final protected function setup_taxonomy_venue_hooks(): void {
			if ( $this->tvh_hooks_registered ) {
				return;
			}

			$this->tvh_hooks_registered = true;

			// Register the temporary skip post type early.
			$this->tvh_register_skip_post_type();

			// Capture the post title from raw data BEFORE any other processing.
			// Priority 2 ensures this runs before our flag check at priority 3.
			add_filter( 'wp_import_post_data_raw', array( $this, 'tvh_capture_current_post_data' ), 2 );

			// Flag events for skipping during pass 1 (before post type rewrite).
			add_filter( 'wp_import_post_data_raw', array( $this, 'tvh_maybe_flag_events_on_venue_pass' ), 3 );

			// Hook into term creation to intercept venue terms from
			// WXR <wp:term> entries. Priority 3 runs BEFORE the main migration
			// class's intercept_term_creation() at priority 5.
			add_filter( 'pre_insert_term', array( $this, 'tvh_intercept_venue_term_creation' ), 3, 2 );

			// Hook into per-post term assignments to intercept venue terms.
			// Priority 4 ensures this runs BEFORE the main migration class's
			// rewrite_post_terms_taxonomy() at priority 5.
			add_filter( 'wp_import_post_terms', array( $this, 'tvh_filter_venue_terms' ), 4 );

			// Track the post ID when a gatherpress_event is saved.
			add_action( 'save_post_gatherpress_event', array( $this, 'tvh_track_saved_post_id' ), 1 );

			// Process all deferred venue links and clean up skip posts
			// after the entire import completes.
			add_action( 'import_end', array( $this, 'tvh_process_deferred_venue_links' ) );
		}

		/**
		 * Registers the temporary skip post type used during pass 1.
		 *
		 * This non-public post type exists solely so that `post_type_exists()`
		 * returns true when the WordPress Importer checks it.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function tvh_register_skip_post_type(): void {
			if ( post_type_exists( self::$skip_post_type ) ) {
				return;
			}

			register_post_type(
				self::$skip_post_type,
				array(
					'label'               => 'GPM Skip (temporary)',
					'public'              => false,
					'publicly_queryable'  => false,
					'show_ui'             => false,
					'show_in_menu'        => false,
					'show_in_nav_menus'   => false,
					'show_in_admin_bar'   => false,
					'show_in_rest'        => false,
					'exclude_from_search' => true,
					'can_export'          => false,
					'rewrite'             => false,
					'query_var'           => false,
					'supports'            => array( 'title' ),
				)
			);
		}

		/**
		 * Cleans up any posts created with the temporary skip post type.
		 *
		 * Also removes the corresponding entries from the WordPress Importer's
		 * `processed_posts` map so a subsequent pass can re-import them.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function tvh_cleanup_skip_posts(): void {
			$skip_posts = get_posts(
				array(
					'post_type'      => self::$skip_post_type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			if ( empty( $skip_posts ) ) {
				return;
			}

			foreach ( $skip_posts as $skip_post_id ) {
				if (
					isset( $GLOBALS['wp_import'] )
					&& $GLOBALS['wp_import'] instanceof \stdClass
					&& ! empty( $GLOBALS['wp_import']->processed_posts )
					&& is_array( $GLOBALS['wp_import']->processed_posts )
				) {
					$source_ids = array_keys( $GLOBALS['wp_import']->processed_posts, $skip_post_id, true );
					foreach ( $source_ids as $source_id ) {
						unset( $GLOBALS['wp_import']->processed_posts[ $source_id ] );
					}
				}

				wp_delete_post( $skip_post_id, true );
			}
		}

		/**
		 * Captures the current event post data from the raw import data.
		 *
		 * Hooked to `wp_import_post_data_raw` at priority 2.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $post_data Raw post data from the importer.
		 * @return array<string, mixed> Unmodified post data.
		 */
		final public function tvh_capture_current_post_data( array $post_data ): array {
			$post_type       = $post_data['post_type'] ?? '';
			$skippable_types = $this->get_skippable_event_post_types();

			if ( in_array( $post_type, $skippable_types, true ) ) {
				$post_title                   = is_string( $post_data['post_title'] ) && ! empty( $post_data['post_title'] ) ? $post_data['post_title'] : '(untitled)';
				$this->tvh_current_post_title = $post_title;
			}

			return $post_data;
		}

		/**
		 * Tracks the post ID when a gatherpress_event is saved during import.
		 *
		 * Hooked to `save_post_gatherpress_event` at priority 1.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The event post ID.
		 * @return void
		 */
		final public function tvh_track_saved_post_id( int $post_id ): void {
			$post_type = get_post_type( $post_id );

			if ( 'gatherpress_event' !== $post_type ) {
				return;
			}

			$this->tvh_last_saved_event_id = $post_id;
		}

		/**
		 * Determines whether this is pass 1 or pass 2 based on existing venue posts.
		 *
		 * Checks whether a `gatherpress_venue` post matching the given slug
		 * already exists. Only runs once per import.
		 *
		 * @since 0.1.0
		 *
		 * @param string $venue_slug The sanitized venue term slug to check.
		 * @return void
		 */
		private function tvh_detect_pass( string $venue_slug ): void {
			if ( $this->tvh_pass_detected ) {
				return;
			}

			$this->tvh_pass_detected = true;

			$existing = get_posts(
				array(
					'post_type'      => 'gatherpress_venue',
					'name'           => $venue_slug,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				$this->tvh_is_venue_pass = false;
			} else {
				$this->tvh_is_venue_pass = true;
			}
		}

		/**
		 * Attempts early pass detection before any venue terms are encountered.
		 *
		 * Checks whether any `gatherpress_venue` posts exist that were created
		 * from the source venue taxonomy terms (identified by the presence of
		 * the `_gpei_source_venue_term_slug` post meta).
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function tvh_detect_pass_early(): void {
			$venue_from_taxonomy = get_posts(
				array(
					'post_type'      => 'gatherpress_venue',
					'meta_key'       => '_gpei_source_venue_term_slug',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $venue_from_taxonomy ) ) {
				$this->tvh_pass_detected = true;
				$this->tvh_is_venue_pass = false;
				return;
			}

			$venue_taxonomy_slug = $this->get_venue_taxonomy_slug();

			if ( isset( $GLOBALS['wp_import'] ) && $GLOBALS['wp_import'] instanceof \stdClass && is_array( $GLOBALS['wp_import']->terms ) ) {
				foreach ( $GLOBALS['wp_import']->terms as $term_data ) {
					if ( ! is_array( $term_data ) ) {
						continue;
					}
					if ( isset( $term_data['term_taxonomy'] ) && $venue_taxonomy_slug === $term_data['term_taxonomy'] ) {
						$term_data_slug = is_string( $term_data['slug'] ) ? $term_data['slug'] : '';
						$venue_slug     = sanitize_title( $term_data_slug );
						if ( empty( $venue_slug ) ) {
							continue;
						}
						$existing = get_posts(
							array(
								'post_type'      => 'gatherpress_venue',
								'name'           => $venue_slug,
								'post_status'    => 'publish',
								'posts_per_page' => 1,
								'fields'         => 'ids',
							)
						);
						if ( ! empty( $existing ) ) {
							$this->tvh_pass_detected = true;
							$this->tvh_is_venue_pass = false;
							return;
						}
					}
				}
			}
		}

		/**
		 * Creates a gatherpress_venue post from a venue term name and slug.
		 *
		 * If a matching venue post already exists, returns its ID without
		 * creating a duplicate.
		 *
		 * @since 0.1.0
		 *
		 * @param string $venue_name The venue name (term name).
		 * @param string $venue_slug The sanitized venue slug.
		 * @return int The venue post ID, or 0 on failure.
		 */
		private function tvh_create_venue_post( string $venue_name, string $venue_slug ): int {
			$existing = get_posts(
				array(
					'post_type'      => 'gatherpress_venue',
					'name'           => $venue_slug,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				return $existing[0];
			}

			$venue_post_id = wp_insert_post(
				array(
					'post_title'   => $venue_name,
					'post_name'    => $venue_slug,
					'post_content' => '',
					'post_type'    => 'gatherpress_venue',
					'post_status'  => 'publish',
				),
				true
			);

			if ( is_wp_error( $venue_post_id ) ) {
				return 0;
			}

			update_post_meta( $venue_post_id, '_gpei_source_venue_term_slug', $venue_slug );

			return $venue_post_id;
		}

		/**
		 * Filters venue taxonomy terms from per-post term assignments.
		 *
		 * Hooked to `wp_import_post_terms` at priority 4.
		 *
		 * **Pass 1:** Creates `gatherpress_venue` posts from venue terms.
		 * **Pass 2:** Records venue slugs for deferred linking.
		 *
		 * In both passes, the venue terms are removed from the assignment list.
		 *
		 * When a venue term is encountered in the per-post terms, this also
		 * updates the WXR taxonomy presence cache so that the event skipping
		 * logic in `tvh_maybe_flag_events_on_venue_pass()` can correctly
		 * identify that this adapter's taxonomy is present.
		 *
		 * @since 0.1.0
		 *
		 * @param array<int, array<string, string>> $terms Array of term assignment arrays.
		 * @return array<int, array<string, string>> Filtered term assignments.
		 */
		final public function tvh_filter_venue_terms( array $terms ): array {
			$venue_taxonomy_slug = $this->get_venue_taxonomy_slug();
			$event_post_id       = $this->tvh_last_saved_event_id;
			$venue_slugs         = array();
			$filtered_terms      = array();

			foreach ( $terms as $term ) {
				$domain = $term['domain'] ?? '';
				$slug   = $term['slug'] ?? '';
				$name   = $term['name'] ?? '';

				if ( $venue_taxonomy_slug === $domain ) {
					$venue_slug = sanitize_title( $slug );

					// Mark that we've seen our venue taxonomy in this WXR file.
					$this->tvh_wxr_has_our_taxonomy = true;

					$this->tvh_detect_pass( $venue_slug );

					if ( $this->tvh_is_venue_pass ) {
						$this->tvh_create_venue_post( $name, $venue_slug );
					} else {
						$venue_slugs[] = $venue_slug;
					}

					continue;
				}

				$filtered_terms[] = $term;
			}

			if ( ! $this->tvh_is_venue_pass && ! empty( $venue_slugs ) && $event_post_id > 0 ) {
				$this->tvh_deferred_venue_links[ $event_post_id ] = $venue_slugs;
			}

			return $filtered_terms;
		}

		/**
		 * Intercepts venue taxonomy term creation from top-level WXR entries.
		 *
		 * Hooked to `pre_insert_term` at priority 3.
		 *
		 * @since 0.1.0
		 *
		 * @param string $term     The term name being inserted.
		 * @param string $taxonomy The taxonomy slug for the term.
		 * @return string|\WP_Error The term name to proceed, or WP_Error to block insertion.
		 */
		final public function tvh_intercept_venue_term_creation( $term, string $taxonomy ) {
			$venue_taxonomy_slug = $this->get_venue_taxonomy_slug();

			if ( $venue_taxonomy_slug !== $taxonomy ) {
				return $term;
			}

			// Mark that we've seen our venue taxonomy in this WXR file.
			$this->tvh_wxr_has_our_taxonomy = true;

			$term_slug = sanitize_title( $term );

			$this->tvh_detect_pass( $term_slug );

			if ( $this->tvh_is_venue_pass ) {
				$this->tvh_create_venue_post( $term, $term_slug );
			}

			return new \WP_Error(
				'gpei_venue_handled',
				sprintf(
					/* translators: %s: venue term name */
					__( 'Venue "%s" handled by GatherPress migration.', 'gatherpress-export-import' ),
					$term
				)
			);
		}

		/**
		 * Redirects source events to a temporary skip post type during pass 1.
		 *
		 * Hooked to `wp_import_post_data_raw` at priority 3, BEFORE the main
		 * migration class rewrites the post type at priority 5.
		 *
		 * Only activates when the current WXR file actually contains venue
		 * taxonomy terms belonging to this adapter (detected via the importer's
		 * parsed terms or via existing venue posts from a prior pass). This
		 * prevents adapters from interfering with each other when multiple
		 * adapters share the same source event post type slug (e.g., Events
		 * Manager and Event Organiser both use `event`).
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $post_data Raw post data from the importer.
		 * @return array<string, mixed> Modified post data with skip post_type, or unmodified.
		 */
		final public function tvh_maybe_flag_events_on_venue_pass( array $post_data ): array {
			$this->tvh_skip_current_event = false;

			$post_type       = $post_data['post_type'] ?? '';
			$skippable_types = $this->get_skippable_event_post_types();

			if ( ! in_array( $post_type, $skippable_types, true ) ) {
				return $post_data;
			}

			// Only activate if this adapter's venue taxonomy is actually present
			// in the current WXR file. This prevents adapters from interfering
			// with each other when they share the same event post type slug.
			if ( ! $this->tvh_wxr_contains_our_venue_taxonomy() ) {
				return $post_data;
			}

			if ( ! $this->tvh_pass_detected ) {
				$this->tvh_detect_pass_early();
			}

			if ( ! $this->tvh_is_venue_pass && $this->tvh_pass_detected ) {
				return $post_data;
			}

			$post_data['post_type']       = self::$skip_post_type;
			$this->tvh_skip_current_event = true;

			return $post_data;
		}

		/**
		 * Processes all deferred venue links after the entire import completes.
		 *
		 * Hooked to `import_end`. Also cleans up skip posts.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		final public function tvh_process_deferred_venue_links(): void {
			$this->tvh_cleanup_skip_posts();

			if ( empty( $this->tvh_deferred_venue_links ) ) {
				return;
			}

			foreach ( $this->tvh_deferred_venue_links as $post_id => $venue_slugs ) {
				$post_type = get_post_type( $post_id );
				if ( 'gatherpress_event' !== $post_type ) {
					continue;
				}

				if ( taxonomy_exists( '_gatherpress_venue' ) ) {
					$existing_terms = wp_get_object_terms( $post_id, '_gatherpress_venue' );
					if ( ! empty( $existing_terms ) && ! is_wp_error( $existing_terms ) ) {
						continue;
					}
				}

				foreach ( $venue_slugs as $venue_slug ) {
					$venue_posts = get_posts(
						array(
							'post_type'      => 'gatherpress_venue',
							'name'           => $venue_slug,
							'post_status'    => 'publish',
							'posts_per_page' => 1,
							'fields'         => 'ids',
						)
					);

					if ( empty( $venue_posts ) ) {
						$venue_posts = get_posts(
							array(
								'post_type'      => 'gatherpress_venue',
								'meta_key'       => '_gpei_source_venue_term_slug',
								'meta_value'     => $venue_slug,
								'post_status'    => 'publish',
								'posts_per_page' => 1,
								'fields'         => 'ids',
							)
						);
					}

					if ( ! empty( $venue_posts ) ) {
						$venue_post_id  = $venue_posts[0];
						$linked         = false;
						$venue_post_obj = get_post( $venue_post_id );

						if ( $venue_post_obj && taxonomy_exists( '_gatherpress_venue' ) ) {
							$term_slug   = $this->get_venue_term_slug( $venue_post_obj->post_name );
							$shadow_term = get_term_by( 'slug', $term_slug, '_gatherpress_venue' );

							if ( $shadow_term instanceof \WP_Term ) {
								$result = wp_set_object_terms( $post_id, array( $shadow_term->term_id ), '_gatherpress_venue', false );
								$linked = ! is_wp_error( $result );
							}
						}

						if ( ! $linked ) {
							$this->link_venue( $post_id, $venue_post_id );
							$linked = true;
						}

						if ( $linked ) {
							delete_post_meta( $venue_post_id, '_gpei_source_venue_term_slug' );
						}

						break; // GatherPress events have one venue.
					}
				}
			}

			$this->tvh_deferred_venue_links = array();
		}

		/**
		 * Whether the WXR file presence check has been performed.
		 *
		 * @since 0.2.0
		 *
		 * @var bool|null Null = not checked yet, true/false = result cached.
		 */
		private ?bool $tvh_wxr_has_our_taxonomy = null;

		/**
		 * Checks whether the current WXR file contains venue taxonomy terms
		 * belonging to this adapter.
		 *
		 * Inspects three sources:
		 * 1. The importer's parsed `terms` array for top-level `<wp:term>` entries.
		 * 2. The importer's parsed `posts` array for per-post `<category>` entries
		 *    that reference the adapter's venue taxonomy.
		 * 3. Existing `gatherpress_venue` posts created from this adapter's venue
		 *    taxonomy (via the `_gpei_source_venue_term_slug` meta), indicating
		 *    a prior Pass 1 has already run.
		 *
		 * The result is cached for the lifetime of the import run.
		 *
		 * @since 0.2.0
		 *
		 * @return bool True if the WXR file or database contains evidence of this
		 *              adapter's venue taxonomy.
		 */
		private function tvh_wxr_contains_our_venue_taxonomy(): bool {
			if ( null !== $this->tvh_wxr_has_our_taxonomy ) {
				return $this->tvh_wxr_has_our_taxonomy;
			}

			$venue_taxonomy_slug = $this->get_venue_taxonomy_slug();

			// Check 1: Look in the importer's parsed terms array.
			if (
				isset( $GLOBALS['wp_import'] )
				&& $GLOBALS['wp_import'] instanceof \WP_Import
				&& ! empty( $GLOBALS['wp_import']->terms )
				&& is_array( $GLOBALS['wp_import']->terms )
			) {
				foreach ( $GLOBALS['wp_import']->terms as $term_data ) {
					if ( ! is_array( $term_data ) ) {
						continue;
					}
					if ( isset( $term_data['term_taxonomy'] ) && $venue_taxonomy_slug === $term_data['term_taxonomy'] ) {
						$this->tvh_wxr_has_our_taxonomy = true;
						return true;
					}
				}
			}

			// Check 2: Look in the importer's parsed posts for per-post terms.
			if (
				isset( $GLOBALS['wp_import'] )
				&& $GLOBALS['wp_import'] instanceof \WP_Import
				&& ! empty( $GLOBALS['wp_import']->posts )
				&& is_array( $GLOBALS['wp_import']->posts )
			) {
				foreach ( $GLOBALS['wp_import']->posts as $post_data ) {
					if ( ! is_array( $post_data ) || empty( $post_data['terms'] ) || ! is_array( $post_data['terms'] ) ) {
						continue;
					}
					foreach ( $post_data['terms'] as $term_entry ) {
						if ( isset( $term_entry['domain'] ) && $venue_taxonomy_slug === $term_entry['domain'] ) {
							$this->tvh_wxr_has_our_taxonomy = true;
							return true;
						}
					}
				}
			}

			// Check 3: Look for existing venue posts from a prior Pass 1.
			$existing_venues = get_posts(
				array(
					'post_type'      => 'gatherpress_venue',
					'meta_key'       => '_gpei_source_venue_term_slug',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $existing_venues ) ) {
				$this->tvh_wxr_has_our_taxonomy = true;
				return true;
			}

			$this->tvh_wxr_has_our_taxonomy = false;
			return false;
		}

		/**
		 * Checks whether the adapter is currently in venue creation mode.
		 *
		 * @since 0.1.0
		 *
		 * @return bool True if in venue pass (pass 1), false if in event pass (pass 2).
		 */
		final public function is_venue_pass(): bool {
			return $this->tvh_is_venue_pass;
		}
	}
}
