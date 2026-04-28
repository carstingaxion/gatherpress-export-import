<?php
/**
 * Taxonomy rewriter — rewrites source taxonomies to GatherPress taxonomies.
 *
 * Hooks into `wp_import_post_terms` and `pre_insert_term` to transparently
 * convert third-party taxonomy slugs to their GatherPress equivalents
 * during a standard WordPress XML import.
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Taxonomy_Rewriter' ) ) {
	/**
	 * Class Taxonomy_Rewriter.
	 *
	 * @since 0.3.0
	 */
	class Taxonomy_Rewriter {

		/**
		 * The adapter registry providing the merged taxonomy map.
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
			add_filter( 'wp_import_post_terms', array( $this, 'rewrite_post_terms_taxonomy' ), 5 );
			add_filter( 'pre_insert_term', array( $this, 'intercept_term_creation' ), 5, 2 );
		}

		/**
		 * Rewrites taxonomy slugs in per-post term assignments during import.
		 *
		 * Hooked to `wp_import_post_terms` at priority 5.
		 *
		 * @since 0.3.0
		 *
		 * @param array $terms Array of term assignment arrays.
		 * @return array Modified term assignments.
		 */
		public function rewrite_post_terms_taxonomy( array $terms ): array {
			$tax_map = $this->registry->get_taxonomy_map();

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
		 * Intercepts term creation during the WordPress import.
		 *
		 * Hooked to `pre_insert_term` at priority 5.
		 *
		 * @since 0.3.0
		 *
		 * @param string $term     The term name being inserted.
		 * @param string $taxonomy The taxonomy slug for the term.
		 * @return string|\WP_Error The term name or WP_Error to block insertion.
		 */
		public function intercept_term_creation( $term, string $taxonomy ) {
			$tax_map = $this->registry->get_taxonomy_map();

			if ( empty( $tax_map ) || ! isset( $tax_map[ $taxonomy ] ) ) {
				return $term;
			}

			$target_taxonomy = $tax_map[ $taxonomy ];

			if ( ! taxonomy_exists( $target_taxonomy ) ) {
				return $term;
			}

			$existing = term_exists( $term, $target_taxonomy );
			if ( ! $existing ) {
				wp_insert_term( $term, $target_taxonomy );
			}

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
	}
}