<?php
/**
 * Meta stasher — intercepts third-party meta and stores it in transients.
 *
 * Hooks into `add_post_metadata` to prevent source-plugin meta keys from
 * being saved to `wp_postmeta` during import. Instead, values are
 * collected in per-post transients for later processing by the
 * `Stash_Processor` class.
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Meta_Stasher' ) ) {
	/**
	 * Class Meta_Stasher.
	 *
	 * @since 0.3.0
	 */
	class Meta_Stasher {

		/**
		 * The adapter registry providing stash meta keys.
		 *
		 * @since 0.3.0
		 *
		 * @var Adapter_Registry
		 */
		private Adapter_Registry $registry;

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
			add_filter( 'add_post_metadata', array( $this, 'stash_meta_on_import' ), 5, 5 );
			add_filter( 'wp_import_post_meta', array( $this, 'filter_post_meta_on_import' ), 20, 3 );
		}

		/**
		 * Stashes a meta key/value pair into a per-post transient.
		 *
		 * Shared helper used by both the main stasher (for event meta)
		 * and the `Venue_Detail_Handler` trait (for venue detail meta).
		 *
		 * Returns `true` to short-circuit `add_post_metadata`, preventing
		 * the meta from being saved to `wp_postmeta`.
		 *
		 * @since 0.2.0
		 *
		 * @param int    $object_id   The post ID receiving the meta.
		 * @param string $meta_key    The meta key to stash.
		 * @param mixed  $meta_value  The meta value to stash.
		 * @param string $prefix      Transient key prefix.
		 * @param string $pending_key Transient key for the pending ID list.
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
		 * Intercepts add_post_metadata to stash third-party meta.
		 *
		 * Hooked to `add_post_metadata` at priority 5.
		 *
		 * @since 0.3.0
		 *
		 * @param mixed  $check      Current check value.
		 * @param int    $object_id  Post ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 * @param bool   $unique     Whether meta should be unique.
		 * @return mixed True to block, or original $check.
		 */
		public function stash_meta_on_import( $check, int $object_id, string $meta_key, $meta_value, bool $unique ) {
			$stash_keys = $this->registry->get_stash_meta_keys();

			if ( ! in_array( $meta_key, $stash_keys, true ) ) {
				return $check;
			}

			$post_type = get_post_type( $object_id );

			if ( 'gatherpress_event' !== $post_type && 'gatherpress_venue' !== $post_type ) {
				return $check;
			}

			// Venue meta stashing is handled by adapter-specific hooks
			// (e.g., Venue_Detail_Handler at priority 4).
			if ( 'gatherpress_venue' === $post_type ) {
				return $check;
			}

			return self::stash_meta( $object_id, $meta_key, $meta_value, 'gpei_meta_stash_', 'gpei_pending_event_ids' );
		}

		/**
		 * Ensures the post ID is tracked in the pending list.
		 *
		 * Hooked to `wp_import_post_meta` at priority 20.
		 *
		 * @since 0.3.0
		 *
		 * @param array $postmeta Post meta arrays.
		 * @param int   $post_id  Post ID.
		 * @param array $post     Full post data.
		 * @return array Unmodified postmeta.
		 */
		public function filter_post_meta_on_import( array $postmeta, int $post_id, array $post ): array {
			if ( 'gatherpress_event' !== $post['post_type'] ) {
				return $postmeta;
			}

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
	}
}
