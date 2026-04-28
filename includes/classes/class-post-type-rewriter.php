<?php
/**
 * Post type rewriter — rewrites source post types to GatherPress types.
 *
 * Hooks into `wp_import_post_data_raw` to transparently convert third-party
 * event and venue post type slugs to their GatherPress equivalents during
 * a standard WordPress XML import.
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Post_Type_Rewriter' ) ) {
	/**
	 * Class Post_Type_Rewriter.
	 *
	 * @since 0.3.0
	 */
	class Post_Type_Rewriter {

		/**
		 * The adapter registry providing merged type maps.
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
		 * Registers the WordPress hook.
		 *
		 * @since 0.3.0
		 *
		 * @return void
		 */
		public function setup_hooks(): void {
			add_filter( 'wp_import_post_data_raw', array( $this, 'rewrite_post_type_on_import' ), 5 );
		}

		/**
		 * Rewrites third-party post types to GatherPress post types during import.
		 *
		 * Hooked to `wp_import_post_data_raw` at priority 5.
		 *
		 * @since 0.3.0
		 *
		 * @param array<string, mixed> $post_data Raw post data from the importer.
		 * @return array<string, mixed> Modified post data.
		 */
		public function rewrite_post_type_on_import( array $post_data ): array {
			if ( empty( $post_data['post_type'] ) ) {
				return $post_data;
			}

			$source_type = $post_data['post_type'];

			$event_map = $this->registry->get_event_post_type_map();
			if ( isset( $event_map[ $source_type ] ) ) {
				$post_data['post_type']         = $event_map[ $source_type ];
				$post_data['_gpei_source_type'] = $source_type;
				return $post_data;
			}

			$venue_map = $this->registry->get_venue_post_type_map();
			if ( isset( $venue_map[ $source_type ] ) ) {
				$post_data['post_type']         = $venue_map[ $source_type ];
				$post_data['_gpei_source_type'] = $source_type;
				return $post_data;
			}

			return $post_data;
		}
	}
}