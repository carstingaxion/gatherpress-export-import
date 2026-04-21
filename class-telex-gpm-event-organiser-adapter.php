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
 *   encountered in per-post term assignments, they are automatically
 *   converted into `gatherpress_venue` posts. Events in this pass are
 *   skipped (not imported).
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
		const SKIP_POST_TYPE = '_telex_gpm_skip';

		/**
		 * Whether this adapter is currently in venue-creation mode (pass 1).
		 *
		 * When true, events are skipped and only venue taxonomy terms are
		 * converted into gatherpress_venue posts. Determined during the
		 * first call to `filter_event_venue_terms()` by checking whether
		 * matching `gatherpress_venue` posts already exist.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $is_venue_pass = true;

		/**
		 * Whether the venue pass detection has been performed.
		 *
		 * Set to true after the first `event-venue` term is encountered
		 * in `filter_event_venue_terms()`, so the pass detection logic
		 * only runs once per import.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $pass_detected = false;

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
		 * The post title of the event currently being imported.
		 *
		 * Set by `capture_current_event_post_data()` from the raw post data
		 * BEFORE the importer creates the post. This is used to track which
		 * event's term assignments are being processed by
		 * `filter_event_venue_terms()`.
		 *
		 * @since 0.1.0
		 *
		 * @var string
		 */
		private string $current_post_title = '';

		/**
		 * The post ID of the last gatherpress_event that was saved.
		 *
		 * Set by `track_saved_post_id()` when `save_post_gatherpress_event`
		 * fires. Because the WordPress Importer processes terms AFTER
		 * `wp_insert_post()`, this ID is available when
		 * `filter_event_venue_terms()` runs.
		 *
		 * @since 0.1.0
		 *
		 * @var int
		 */
		private int $last_saved_event_id = 0;

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
		private array $deferred_venue_links = array();

		/**
		 * Whether the current event post should be skipped during pass 1.
		 *
		 * Set by `maybe_flag_events_on_venue_pass()` and consumed by
		 * `skip_flagged_events()`.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $skip_current_event = false;

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
		 * Registers the temporary skip post type used during pass 1.
		 *
		 * This non-public post type exists solely so that `post_type_exists()`
		 * returns true when the WordPress Importer checks it. This prevents
		 * the "Invalid post type" error that occurs when setting a post's
		 * type to an unregistered slug. Posts created with this type are
		 * cleaned up at `import_end`.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function register_skip_post_type(): void {
			if ( post_type_exists( self::SKIP_POST_TYPE ) ) {
				return;
			}

			register_post_type(
				self::SKIP_POST_TYPE,
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
		 * Called at `import_end` to remove the throwaway posts that were
		 * created by the WordPress Importer during pass 1 when events
		 * were redirected to the skip post type.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function cleanup_skip_posts(): void {
			$skip_posts = get_posts(
				array(
					'post_type'      => self::SKIP_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			if ( empty( $skip_posts ) ) {
				return;
			}

			foreach ( $skip_posts as $skip_post_id ) {
				wp_delete_post( $skip_post_id, true );
			}
		}

		/**
		 * Sets up the import hooks for the two-pass venue/event strategy.
		 *
		 * This method is called by the main migration class when this adapter
		 * is registered, because it implements `Telex_GPM_Hookable_Adapter`.
		 *
		 * It registers hooks for:
		 * - Registering a temporary skip post type for silent event skipping
		 * - Capturing the current post title from raw import data (priority 2)
		 * - Intercepting `event-venue` taxonomy term creation via `pre_insert_term`
		 *   as a fallback for WXR files that include top-level `<wp:term>` entries
		 * - Filtering `event-venue` terms from per-post term assignments, creating
		 *   venue posts on the fly during pass 1, and stashing slugs for pass 2
		 * - Skipping event posts during pass 1 (via post type redirect to skip type)
		 * - Tracking the saved post ID when a gatherpress_event is created
		 * - Deferred venue linking and skip post cleanup at `import_end`
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

			// Register the temporary skip post type early.
			$this->register_skip_post_type();

			// Capture the post title from raw data BEFORE any other processing.
			// Priority 2 ensures this runs before our flag check at priority 3.
			add_filter( 'wp_import_post_data_raw', array( $this, 'capture_current_event_post_data' ), 2 );

			// Flag EO events for skipping during pass 1 (before post type rewrite).
			add_filter( 'wp_import_post_data_raw', array( $this, 'maybe_flag_events_on_venue_pass' ), 3 );

			// Hook into term creation to intercept event-venue terms from
			// WXR <wp:term> entries. Priority 3 runs BEFORE the main migration
			// class's intercept_term_creation() at priority 5.
			add_filter( 'pre_insert_term', array( $this, 'intercept_venue_term_creation' ), 3, 2 );

			// Hook into per-post term assignments to intercept event-venue terms.
			// Priority 4 ensures this runs BEFORE the main migration class's
			// rewrite_post_terms_taxonomy() at priority 5.
			add_filter( 'wp_import_post_terms', array( $this, 'filter_event_venue_terms' ), 4 );

			// Track the post ID when a gatherpress_event is saved.
			// The WordPress Importer calls wp_insert_post(), which triggers
			// save_post_gatherpress_event. This runs BEFORE wp_import_post_terms.
			add_action( 'save_post_gatherpress_event', array( $this, 'track_saved_post_id' ), 1 );

			// Process all deferred venue links and clean up skip posts
			// after the entire import completes.
			add_action( 'import_end', array( $this, 'process_deferred_venue_links' ) );
		}

		/**
		 * Captures the current event post data from the raw import data.
		 *
		 * Hooked to `wp_import_post_data_raw` at priority 2 (before the
		 * skip flag check at priority 3). Records the post title so that
		 * subsequent hooks can associate data with the correct event,
		 * even if the post hasn't been created yet.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $post_data Raw post data from the importer.
		 * @return array<string, mixed> Unmodified post data.
		 */
		public function capture_current_event_post_data( array $post_data ): array {
			$post_type = $post_data['post_type'] ?? '';

			if ( 'event' === $post_type ) {
				$this->current_post_title = $post_data['post_title'] ?? '(untitled)';
			}

			return $post_data;
		}

		/**
		 * Tracks the post ID when a gatherpress_event is saved during import.
		 *
		 * Hooked to `save_post_gatherpress_event` at priority 1 (very early).
		 * The WordPress Importer calls `wp_insert_post()` which triggers
		 * `save_post`, and THEN processes the post's term assignments via
		 * `wp_import_post_terms`. By recording the post ID here, the
		 * subsequent `filter_event_venue_terms()` call can associate
		 * venue slugs with this post.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The event post ID.
		 * @return void
		 */
		public function track_saved_post_id( int $post_id ): void {
			$post_type = get_post_type( $post_id );

			if ( 'gatherpress_event' !== $post_type ) {
				return;
			}

			$this->last_saved_event_id = $post_id;
		}

		/**
		 * Determines whether this is pass 1 or pass 2 based on existing venue posts.
		 *
		 * Checks whether a `gatherpress_venue` post matching the given slug
		 * already exists. If it does, we are in pass 2 (event import mode).
		 * If not, we are in pass 1 (venue creation mode).
		 *
		 * Only runs once — the first `event-venue` term encountered determines
		 * the pass for the entire import.
		 *
		 * @since 0.1.0
		 *
		 * @param string $venue_slug The sanitized venue term slug to check.
		 * @return void
		 */
		private function detect_pass( string $venue_slug ): void {
			if ( $this->pass_detected ) {
				return;
			}

			$this->pass_detected = true;

			// Check if a gatherpress_venue post with this slug already exists.
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
				$this->is_venue_pass = false;
			} else {
				$this->is_venue_pass = true;
			}
		}

		/**
		 * Creates a gatherpress_venue post from an event-venue term name and slug.
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
		private function create_venue_post( string $venue_name, string $venue_slug ): int {
			// Check if already created (idempotent).
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

			if ( is_wp_error( $venue_post_id ) || ! $venue_post_id ) {
				return 0;
			}

			// Store a mapping from the original term slug to the new venue post ID.
			// This is used as a fallback lookup in process_deferred_venue_links()
			// if WordPress modifies the post slug during wp_insert_post().
			update_post_meta( $venue_post_id, '_telex_gpm_source_venue_term_slug', $venue_slug );

			// GatherPress hooks into save_post_gatherpress_venue to
			// automatically create a shadow term in _gatherpress_venue.

			return $venue_post_id;
		}

		/**
		 * Filters event-venue terms from per-post term assignments.
		 *
		 * Hooked to `wp_import_post_terms` at priority 4 (before the main
		 * migration class's taxonomy rewriting at priority 5).
		 *
		 * **Pass 1 (venue creation):** For each `event-venue` term found,
		 * creates a `gatherpress_venue` post if one doesn't already exist.
		 * The term is removed from the assignment list.
		 *
		 * **Pass 2 (event import):** Removes `event-venue` terms from the
		 * assignment list and records the venue slugs for deferred linking.
		 * Uses `$this->last_saved_event_id` which was set by
		 * `track_saved_post_id()` when `save_post_gatherpress_event` fired
		 * (before the importer processes terms).
		 *
		 * @since 0.1.0
		 *
		 * @param array $terms Array of term assignment arrays, each with 'domain', 'slug', 'name' keys.
		 * @return array Filtered term assignments with event-venue terms removed.
		 */
		public function filter_event_venue_terms( array $terms ): array {
			$event_post_id = $this->last_saved_event_id;

			$venue_slugs    = array();
			$filtered_terms = array();

			foreach ( $terms as $term ) {
				$domain = $term['domain'] ?? '';
				$slug   = $term['slug'] ?? '';
				$name   = $term['name'] ?? '';

				if ( 'event-venue' === $domain ) {
					$venue_slug = sanitize_title( $slug );

					// Detect which pass we're in (only on first venue term).
					$this->detect_pass( $venue_slug );

					if ( $this->is_venue_pass ) {
						// Pass 1: Create a gatherpress_venue post from this term.
						$this->create_venue_post( $name, $venue_slug );
					} else {
						// Pass 2: Record the slug for deferred venue linking.
						$venue_slugs[] = $venue_slug;
					}

					// In both passes, remove event-venue terms from the assignment list.
					continue;
				}

				$filtered_terms[] = $term;
			}

			// In pass 2, associate the venue slugs with the event post for deferred linking.
			if ( ! $this->is_venue_pass && ! empty( $venue_slugs ) && $event_post_id > 0 ) {
				$this->deferred_venue_links[ $event_post_id ] = $venue_slugs;
			}

			return $filtered_terms;
		}

		/**
		 * Intercepts event-venue taxonomy term creation from top-level WXR entries.
		 *
		 * Hooked to `pre_insert_term` at priority 3 (before the main migration
		 * class's `intercept_term_creation` at priority 5). This is a FALLBACK
		 * mechanism for top-level `<wp:term>` entries.
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

			// Detect which pass we're in.
			$this->detect_pass( $term_slug );

			if ( $this->is_venue_pass ) {
				$this->create_venue_post( $term, $term_slug );
			}

			return new \WP_Error(
				'telex_gpm_eo_venue_handled',
				sprintf(
					/* translators: %s: venue term name */
					__( 'Event Organiser venue "%s" handled by GatherPress migration.', 'telex-gatherpress-migration' ),
					$term
				)
			);
		}

		/**
		 * Redirects Event Organiser events to a temporary skip post type during pass 1.
		 *
		 * During pass 1, events should not be imported because venue shadow
		 * taxonomy terms may not be ready. This method sets the `post_type`
		 * to a registered but non-public temporary post type, which the
		 * WordPress Importer accepts without error (since `post_type_exists()`
		 * returns true). The resulting throwaway posts are cleaned up at
		 * `import_end`.
		 *
		 * During pass 2, events pass through normally.
		 *
		 * Runs at priority 3 on `wp_import_post_data_raw`, BEFORE the main
		 * migration class rewrites the post type at priority 5. The post_type
		 * is still `event` at this point.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $post_data Raw post data from the importer.
		 * @return array<string, mixed> Modified post data with skip post_type, or unmodified.
		 */
		public function maybe_flag_events_on_venue_pass( array $post_data ): array {
			// Reset the flag for each post.
			$this->skip_current_event = false;

			$post_type = $post_data['post_type'] ?? '';

			// In pass 2, let events through.
			if ( ! $this->is_venue_pass && $this->pass_detected ) {
				return $post_data;
			}

			// Only skip Event Organiser events (post_type 'event') during pass 1.
			if ( 'event' === $post_type ) {
				// Redirect to the temporary skip post type. This is a registered
				// (non-public) post type, so the importer won't log an error.
				// The resulting posts are cleaned up at import_end.
				$post_data['post_type'] = self::SKIP_POST_TYPE;
				$this->skip_current_event = true;
			}

			return $post_data;
		}

		/**
		 * Processes all deferred venue links after the entire import completes.
		 *
		 * Hooked to `import_end`. Iterates through `$this->deferred_venue_links`
		 * and calls `link_venue()` for each event-venue pair. Also cleans up
		 * any temporary skip posts created during pass 1.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function process_deferred_venue_links(): void {
			// Always clean up skip posts, regardless of pass.
			$this->cleanup_skip_posts();

			if ( empty( $this->deferred_venue_links ) ) {
				return;
			}

			foreach ( $this->deferred_venue_links as $post_id => $venue_slugs ) {
				$post_type = get_post_type( $post_id );
				if ( 'gatherpress_event' !== $post_type ) {
					continue;
				}

				// Check if already linked.
				if ( taxonomy_exists( '_gatherpress_venue' ) ) {
					$existing_terms = wp_get_object_terms( $post_id, '_gatherpress_venue' );
					if ( ! empty( $existing_terms ) && ! is_wp_error( $existing_terms ) ) {
						continue;
					}
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
						$venue_post_id  = $venue_posts[0];
						$linked         = false;
						$venue_post_obj = get_post( $venue_post_id );

						if ( $venue_post_obj && taxonomy_exists( '_gatherpress_venue' ) ) {
							$term_slug   = $this->get_venue_term_slug( $venue_post_obj->post_name );
							$shadow_term = get_term_by( 'slug', $term_slug, '_gatherpress_venue' );

							if ( $shadow_term && ! is_wp_error( $shadow_term ) ) {
								$result = wp_set_object_terms( $post_id, array( $shadow_term->term_id ), '_gatherpress_venue', false );
								$linked = ! is_wp_error( $result );
							}
						}

						if ( ! $linked ) {
							$this->link_venue( $post_id, $venue_post_id );
							$linked = true;
						}

						// Clean up the temporary source venue term slug meta.
						if ( $linked ) {
							delete_post_meta( $venue_post_id, '_telex_gpm_source_venue_term_slug' );
						}

						break; // GatherPress events have one venue.
					}
				}
			}

			// Clear the deferred links after processing.
			$this->deferred_venue_links = array();
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
