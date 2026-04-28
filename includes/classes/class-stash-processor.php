<?php
/**
 * Stash processor — processes stashed meta at import_end.
 *
 * Iterates through all pending event post IDs, delegates datetime
 * conversion to the appropriate adapter, and resolves venue ID
 * mapping for venue linking.
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Stash_Processor' ) ) {
	/**
	 * Class Stash_Processor.
	 *
	 * @since 0.3.0
	 */
	class Stash_Processor {

		/**
		 * The adapter registry.
		 *
		 * @since 0.3.0
		 *
		 * @var Adapter_Registry
		 */
		private Adapter_Registry $registry;

		/**
		 * Tracks post IDs already processed to prevent duplicates.
		 *
		 * @since 0.3.0
		 *
		 * @var array<int, bool>
		 */
		private array $processed_posts = array();

		/**
		 * Constructor.
		 *
		 * @since 0.3.0
		 *
		 * @param Adapter_Registry $registry The adapter registry.
		 */
		public function __construct( Adapter_Registry $registry ) {
			$this->registry = $registry;
		}

		/**
		 * Registers the WordPress hooks.
		 *
		 * @since 0.3.0
		 *
		 * @return void
		 */
		public function setup_hooks(): void {
			add_action( 'import_end', array( $this, 'process_all_remaining_stashes' ) );
		}

		/**
		 * Processes all remaining stashed meta after the import completes.
		 *
		 * Hooked to `import_end`.
		 *
		 * @since 0.3.0
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

			delete_transient( 'gpei_pending_event_ids' );
		}

		/**
		 * Processes stashed meta for a single gatherpress_event post.
		 *
		 * Finds the appropriate adapter via `can_handle()`, delegates
		 * datetime conversion, and resolves venue linking.
		 *
		 * @since 0.3.0
		 *
		 * @param int $post_id The event post ID.
		 * @return void
		 */
		public function process_stashed_meta( int $post_id ): void {
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

			$this->processed_posts[ $post_id ] = true;
			$adapters                          = $this->registry->get_adapters();

			// Datetime conversion.
			foreach ( $adapters as $adapter ) {
				if ( $adapter->can_handle( $stash ) ) {
					$adapter->convert_datetimes( $post_id, $stash );
					break;
				}
			}

			// Venue linking.
			foreach ( $adapters as $adapter ) {
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
		 * Resolves an old venue ID to a new venue ID via the importer mapping.
		 *
		 * @since 0.3.0
		 *
		 * @param int $old_venue_id The original venue post ID.
		 * @return int The new venue post ID, or 0.
		 */
		private function resolve_venue_id( int $old_venue_id ): int {
			if ( isset( $GLOBALS['wp_import'] ) && isset( $GLOBALS['wp_import']->processed_posts[ $old_venue_id ] ) ) {
				return intval( $GLOBALS['wp_import']->processed_posts[ $old_venue_id ] );
			}

			return 0;
		}
	}
}
