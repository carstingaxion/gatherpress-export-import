<?php
/**
 * Event Organiser (Stephen Harris) adapter.
 *
 * Handles import conversion for the `event` post type as used by Event Organiser.
 * Event Organiser stores schedule data in `_eventorganiser_schedule_start_datetime`
 * and `_eventorganiser_schedule_end_datetime` meta keys in 'Y-m-d H:i:s' format.
 *
 * Venues are stored as terms of the `event-venue` taxonomy rather than a
 * separate post type. During import, this adapter implements a two-pass
 * approach:
 *
 * - **Pass 1 (Venue creation):** When `event-venue` taxonomy terms are
 *   encountered, they are automatically converted into `gatherpress_venue`
 *   posts. Events in this pass are skipped (not imported).
 * - **Pass 2 (Event import):** On the second import of the same file,
 *   venues already exist as `gatherpress_venue` posts with their shadow
 *   taxonomy terms. Events are imported and linked to venues via the
 *   `_gatherpress_venue` shadow taxonomy.
 *
 * Note: Event Organiser shares the `event` post type slug with Events Manager.
 * The adapter distinguishes itself via meta key detection in `can_handle()`.
 *
 * This adapter implements `Telex_GPM_Hookable_Adapter` because it needs its
 * own import hooks for the two-pass venue/event strategy and `event-venue`
 * taxonomy term interception. All EO-specific import logic is encapsulated
 * here rather than in the main migration class.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_GPM_Event_Organiser_Adapter' ) ) {
	/**
	 * Class Telex_GPM_Event_Organiser_Adapter.
	 *
	 * Source adapter for Event Organiser by Stephen Harris. Converts the
	 * `event` post type to `gatherpress_event` during WordPress XML import.
	 * Differentiates from Events Manager (which uses the same CPT slug) by
	 * inspecting for Event Organiser-specific meta keys.
	 *
	 * Implements a two-pass import strategy for taxonomy-based venues:
	 * first pass creates venue posts from taxonomy terms, second pass
	 * imports events and links them to venues.
	 *
	 * Also implements `Telex_GPM_Hookable_Adapter` to register its own
	 * import hooks for venue term interception, event skipping, and
	 * venue linking — keeping all EO-specific logic self-contained.
	 *
	 * @since 0.1.0
	 *
	 * @see Telex_GPM_Source_Adapter
	 * @see Telex_GPM_Hookable_Adapter
	 * @see Telex_GPM_Datetime_Helper
	 */
	class Telex_GPM_Event_Organiser_Adapter implements Telex_GPM_Source_Adapter, Telex_GPM_Hookable_Adapter {

		use Telex_GPM_Datetime_Helper;

		/**
		 * Whether this adapter is currently in venue-creation mode (pass 1).
		 *
		 * When true, events are skipped and only venue taxonomy terms are
		 * converted into gatherpress_venue posts. Set via the import pass
		 * detection logic in `intercept_venue_terms()`.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $is_venue_pass = true;

		/**
		 * Whether the import hooks for this adapter have been registered.
		 *
		 * Ensures idempotency — calling `setup_import_hooks()` multiple
		 * times will not register duplicate hooks.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $hooks_registered = false;

		/**
		 * Pending venue term slugs from the current post being imported.
		 *
		 * Temporarily holds event-venue term slugs collected during
		 * `filter_event_venue_terms()` until the post is saved and
		 * we have a real post ID to stash them against.
		 *
		 * @since 0.1.0
		 *
		 * @var string[]
		 */
		private array $pending_venue_slugs = array();

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

			// Fall back to the "finish" variants if the primary keys are absent.
			if ( empty( $end ) && isset( $stash['_eventorganiser_schedule_start_finish'] ) ) {
				$end = $stash['_eventorganiser_schedule_start_finish'];
			}

			if ( empty( $start ) ) {
				return;
			}

			if ( empty( $end ) ) {
				$end = $start;
			}

			// Event Organiser stores datetimes in the site's local timezone.
			$timezone = $this->get_default_timezone();

			$this->save_gatherpress_datetimes( $post_id, $start, $end, $timezone );
		}

		/**
		 * Gets the meta key used for venue linking.
		 *
		 * Event Organiser does not use a venue meta key on the event post.
		 * Venues are taxonomy terms (`event-venue`), which are handled
		 * by the two-pass import strategy.
		 *
		 * @since 0.1.0
		 *
		 * @return null Always null for Event Organiser.
		 */
		public function get_venue_meta_key(): ?string {
			return null;
		}

		// /**
		//  * Links a venue to an event after import.
		//  *
		//  * For Event Organiser, venue linking is handled by the two-pass
		//  * import system. During pass 2, the `event-venue` taxonomy terms
		//  * have already been remapped and the `link_event_to_venue_by_term()`
		//  * method handles the association.
		//  *
		//  * This method serves as a fallback if called directly with a
		//  * venue post ID, delegating to the trait's implementation.
		//  *
		//  * @since 0.1.0
		//  *
		//  * @param int $post_id      The event post ID.
		//  * @param int $new_venue_id The new (mapped) venue post ID.
		//  * @return void
		//  */
		// public function link_venue( int $post_id, int $new_venue_id ): void {
		// 	if ( 'gatherpress_venue' !== get_post_type( $new_venue_id ) ) {
		// 		return;
		// 	}

		// 	// Use the trait's link_venue for direct venue post linking.
		// 	if ( class_exists( '\GatherPress\Core\Event' ) ) {
		// 		$event = new \GatherPress\Core\Event( $post_id );
		// 		if ( method_exists( $event, 'save_venue' ) ) {
		// 			$event->save_venue( $new_venue_id );
		// 			return;
		// 		}
		// 	}

		// 	// Fallback: assign the _gatherpress_venue shadow taxonomy term directly.
		// 	if ( taxonomy_exists( '_gatherpress_venue' ) ) {
		// 		$venue_post = get_post( $new_venue_id );
		// 		if ( $venue_post && ! empty( $venue_post->post_name ) ) {
		// 			$term = get_term_by( 'slug', $venue_post->post_name, '_gatherpress_venue' );
		// 			if ( $term && ! is_wp_error( $term ) ) {
		// 				wp_set_object_terms( $post_id, array( $term->term_id ), '_gatherpress_venue', false );
		// 			}
		// 		}
		// 	}
		// }

		/**
		 * Gets the taxonomy mapping for Event Organiser.
		 *
		 * Maps Event Organiser's custom taxonomies to GatherPress equivalents:
		 * - `event-category` → `gatherpress_topic`
		 * - `event-tag` → `post_tag`
		 *
		 * Note: `event-venue` is NOT mapped here because it requires special
		 * two-pass handling. During pass 1, venue terms are intercepted and
		 * converted to gatherpress_venue posts. During pass 2, the terms are
		 * used to look up the created venue posts and assign the
		 * `_gatherpress_venue` shadow taxonomy.
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
		 * Pending post IDs/titles to skip during pass 1 (venue creation).
		 *
		 * When in venue pass, event post GUIDs or unique identifiers are
		 * collected so that the `wp_import_existing_post` filter can tell
		 * the WordPress Importer to skip them silently.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $skip_current_event = false;

		/**
		 * Sets up the import hooks for the two-pass venue/event strategy.
		 *
		 * This method is called by the main migration class when this adapter
		 * is registered, because it implements `Telex_GPM_Hookable_Adapter`.
		 *
		 * It registers hooks for:
		 * - Intercepting `event-venue` taxonomy term creation via `pre_insert_term`
		 *   to create gatherpress_venue posts instead (pass 1)
		 * - Filtering `event-venue` terms from per-post term assignments and
		 *   stashing them for venue linking
		 * - Skipping event posts during pass 1 (via wp_import_existing_post)
		 * - Linking events to venues via shadow taxonomy during pass 2
		 *
		 * The pass is determined by checking whether any `gatherpress_venue`
		 * posts already exist that match the incoming venue term slugs.
		 *
		 * Note: The standard WordPress Importer does NOT have a `wp_import_terms`
		 * filter. Instead, it calls `wp_insert_term()` for each term, which fires
		 * the `pre_insert_term` filter. This adapter hooks into `pre_insert_term`
		 * at priority 3 (before the main migration class at priority 5) to
		 * intercept `event-venue` terms before they fail with "Invalid taxonomy".
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function setup_import_hooks(): void {
			if ( $this->hooks_registered ) {
				return;
			}

			$this->hooks_registered = true;

			// Hook into term creation to intercept event-venue terms and create
			// gatherpress_venue posts. Priority 3 runs BEFORE the main migration
			// class's intercept_term_creation() at priority 5.
			add_filter( 'pre_insert_term', array( $this, 'intercept_venue_term_creation' ), 3, 2 );

			// Hook into per-post term assignments to intercept event-venue terms.
			// Priority 4 ensures this runs BEFORE the main migration class's
			// rewrite_post_terms_taxonomy() at priority 5.
			add_filter( 'wp_import_post_terms', array( $this, 'filter_event_venue_terms' ), 4 );

			// Flag EO events for skipping during pass 1 (before post type rewrite).
			add_filter( 'wp_import_post_data_raw', array( $this, 'maybe_flag_events_on_venue_pass' ), 3 );

			// Tell the WordPress Importer to skip flagged events by pretending they already exist.
			add_filter( 'wp_import_existing_post', array( $this, 'skip_flagged_events' ), 10, 2 );

			// After an event is saved (pass 2), stash any pending venue slugs.
			add_action( 'save_post_gatherpress_event', array( $this, 'stash_pending_venue_slugs_on_save' ), 5 );

			// After each event is imported (pass 2), link it to venues.
			add_action( 'save_post_gatherpress_event', array( $this, 'link_event_to_venue_by_term' ), 98 );
		}

		/**
		 * Filters event-venue terms from per-post term assignments.
		 *
		 * Hooked to `wp_import_post_terms` at priority 4 (before the main
		 * migration class's taxonomy rewriting at priority 5). Removes
		 * `event-venue` terms from the assignment list and stashes their
		 * slugs for venue linking during pass 2.
		 *
		 * This keeps all Event Organiser-specific `event-venue` handling
		 * encapsulated within this adapter.
		 *
		 * @since 0.1.0
		 *
		 * @param array $terms Array of term assignment arrays, each with 'domain', 'slug', 'name' keys.
		 * @return array Filtered term assignments with event-venue terms removed.
		 */
		public function filter_event_venue_terms( array $terms ): array {
			$venue_slugs    = array();
			$filtered_terms = array();

			foreach ( $terms as $term ) {
				if ( isset( $term['domain'] ) && 'event-venue' === $term['domain'] ) {
					if ( isset( $term['slug'] ) ) {
						$venue_slugs[] = $term['slug'];
					}
					// Remove event-venue terms — they're handled by the two-pass strategy.
					continue;
				}

				$filtered_terms[] = $term;
			}

			if ( ! empty( $venue_slugs ) ) {
				$this->pending_venue_slugs = $venue_slugs;
			}

			return $filtered_terms;
		}

		/**
		 * Stashes pending venue slugs when a gatherpress_event post is saved.
		 *
		 * Hooked to `save_post_gatherpress_event` at priority 5 (early).
		 * Takes the venue slugs collected during `filter_event_venue_terms()`
		 * and stores them in a transient keyed by the post ID.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The event post ID.
		 * @return void
		 */
		public function stash_pending_venue_slugs_on_save( int $post_id ): void {
			if ( empty( $this->pending_venue_slugs ) ) {
				return;
			}

			$transient_key = 'telex_gpm_eo_venue_terms_' . $post_id;
			set_transient( $transient_key, $this->pending_venue_slugs, HOUR_IN_SECONDS );

			// Clear pending slugs after stashing.
			$this->pending_venue_slugs = array();
		}

		/**
		 * Intercepts event-venue taxonomy term creation and creates venue posts.
		 *
		 * Hooked to `pre_insert_term` at priority 3 (before the main migration
		 * class's `intercept_term_creation` at priority 5). This fires for every
		 * `wp_insert_term()` call, including those from the WordPress Importer.
		 *
		 * During pass 1, each `event-venue` term is converted into a
		 * `gatherpress_venue` post. The term's name becomes the post title.
		 * GatherPress then automatically creates the `_gatherpress_venue`
		 * shadow taxonomy term for each venue post.
		 *
		 * If a `gatherpress_venue` post with a matching slug already exists,
		 * the term is skipped (idempotent). This also determines whether
		 * the import is in pass 1 or pass 2 — if a venue already exists,
		 * we may switch to pass 2 (event import mode).
		 *
		 * Returns a WP_Error to prevent the actual `event-venue` taxonomy term
		 * from being created (since that taxonomy doesn't exist in GatherPress).
		 *
		 * @since 0.1.0
		 *
		 * @param string $term     The term name being inserted.
		 * @param string $taxonomy The taxonomy slug for the term.
		 * @return string|\WP_Error The term name to proceed, or WP_Error to block insertion.
		 */
		public function intercept_venue_term_creation( $term, string $taxonomy ) {
			if ( 'event-venue' !== $taxonomy ) {
				return $term;
			}

			$term_slug = sanitize_title( $term );

			// Check if a gatherpress_venue post with this slug already exists.
			$existing = get_posts(
				array(
					'post_type'      => 'gatherpress_venue',
					'name'           => $term_slug,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				// Venue already exists — switch to pass 2.
				$this->is_venue_pass = false;

				return new \WP_Error(
					'telex_gpm_venue_exists',
					sprintf(
						/* translators: %s: venue term name */
						__( 'Venue "%s" already exists as a gatherpress_venue post. Switching to event import mode.', 'telex-gatherpress-migration' ),
						$term
					)
				);
			}

			// Pass 1: Create a gatherpress_venue post from the taxonomy term.
			$venue_post_id = wp_insert_post(
				array(
					'post_title'   => $term,
					'post_name'    => $term_slug,
					'post_content' => '',
					'post_type'    => 'gatherpress_venue',
					'post_status'  => 'publish',
				)
			);

			if ( $venue_post_id && ! is_wp_error( $venue_post_id ) ) {
				// Store a mapping from the original term slug to the new venue post ID.
				update_post_meta( $venue_post_id, '_telex_gpm_source_venue_term_slug', $term_slug );
			}

			// Return WP_Error to prevent inserting a term into the non-existent
			// event-venue taxonomy. The importer will log this but continue.
			return new \WP_Error(
				'telex_gpm_venue_created',
				sprintf(
					/* translators: %s: venue term name */
					__( 'Venue "%s" converted to gatherpress_venue post.', 'telex-gatherpress-migration' ),
					$term
				)
			);
		}

		/**
		 * Flags Event Organiser events for skipping during the venue creation pass.
		 *
		 * During pass 1, we only want to create venue posts from taxonomy
		 * terms. Events should not be imported yet because the venue shadow
		 * taxonomy terms may not be ready. This filter sets an internal flag
		 * when an EO event post is encountered; the `skip_flagged_events()`
		 * method then uses this flag to tell the WordPress Importer to skip
		 * the post silently (no error).
		 *
		 * During pass 2 (`$this->is_venue_pass === false`), events pass
		 * through normally.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $post_data Raw post data from the importer.
		 * @return array<string, mixed> Unmodified post data.
		 */
		public function maybe_flag_events_on_venue_pass( array $post_data ): array {
			// Reset the flag for each post.
			$this->skip_current_event = false;

			if ( ! $this->is_venue_pass ) {
				return $post_data;
			}

			// Only flag Event Organiser events (post_type 'event').
			if ( isset( $post_data['post_type'] ) && 'event' === $post_data['post_type'] ) {
				$this->skip_current_event = true;
			}

			return $post_data;
		}

		/**
		 * Tells the WordPress Importer to skip flagged EO events during pass 1.
		 *
		 * Hooked to `wp_import_existing_post` at priority 10. When an event
		 * has been flagged by `maybe_flag_events_on_venue_pass()`, this method
		 * returns a truthy value (a fake post ID) which makes the importer
		 * treat the post as already existing and skip it silently — without
		 * logging an "Invalid post type" error.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $post_exists The existing post ID (0 if not found).
		 * @param array $post        The post data being checked.
		 * @return int The existing post ID, or a fake ID to trigger a silent skip.
		 */
		public function skip_flagged_events( int $post_exists, array $post ): int {
			if ( $this->skip_current_event ) {
				// Return a non-zero value to make the importer think this post
				// already exists. It will log a "skipped" notice instead of an error.
				// Use PHP_INT_MAX as a fake ID that will never match a real post.
				$this->skip_current_event = false;
				return PHP_INT_MAX;
			}

			return $post_exists;
		}

		/**
		 * Links an imported event to its venue via the _gatherpress_venue shadow taxonomy.
		 *
		 * Called on `save_post_gatherpress_event` (priority 98) during pass 2.
		 * Looks for event-venue term slugs that were stashed during import
		 * and resolves them to gatherpress_venue posts, then assigns the
		 * corresponding `_gatherpress_venue` shadow taxonomy term.
		 *
		 * The method uses the `_telex_gpm_source_venue_term_slug` post meta
		 * stored on venue posts during pass 1 to find the correct venue.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The event post ID.
		 * @return void
		 */
		public function link_event_to_venue_by_term( int $post_id ): void {
			if ( $this->is_venue_pass ) {
				return;
			}

			if ( 'gatherpress_event' !== get_post_type( $post_id ) ) {
				return;
			}

			// Check if this event has _gatherpress_venue terms already assigned.
			$venue_terms = wp_get_object_terms( $post_id, '_gatherpress_venue' );

			if ( ! empty( $venue_terms ) && ! is_wp_error( $venue_terms ) ) {
				// Already linked — nothing more to do.
				return;
			}

			// Look for event-venue term slugs stashed during import.
			$transient_key = 'telex_gpm_eo_venue_terms_' . $post_id;
			$venue_slugs   = get_transient( $transient_key );

			if ( ! is_array( $venue_slugs ) || empty( $venue_slugs ) ) {
				return;
			}

			foreach ( $venue_slugs as $venue_slug ) {
				// Find the gatherpress_venue post created from this term slug.
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
					// Try by meta key as fallback.
					$venue_posts = get_posts(
						array(
							'post_type'      => 'gatherpress_venue',
							'meta_key'       => '_telex_gpm_source_venue_term_slug',
							'meta_value'     => $venue_slug,
							'post_status'    => 'publish',
							'posts_per_page' => 1,
							'fields'         => 'ids',
						)
					);
				}

				if ( ! empty( $venue_posts ) ) {
					$venue_post_id = $venue_posts[0];
					$this->link_venue( $post_id, $venue_post_id );
					break; // GatherPress events have one venue.
				}
			}

			delete_transient( $transient_key );
		}

		/**
		 * Checks whether the adapter is currently in venue creation mode.
		 *
		 * @since 0.1.0
		 *
		 * @return bool True if in venue pass (pass 1), false if in event pass (pass 2).
		 */
		public function is_venue_pass(): bool {
			return $this->is_venue_pass;
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
