<?php
/**
 * Shared template block handler trait.
 *
 * Provides reusable logic for reading the registered block template
 * from a post type object and serializing it into HTML block markup.
 * This is useful when programmatically creating posts that should
 * include the same default blocks that the block editor would insert.
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( __NAMESPACE__ . '\Template_Block_Handler' ) ) {
	/**
	 * Trait Template_Block_Handler.
	 *
	 * Encapsulates the logic for reading a post type's registered
	 * `template` argument and serializing the block specifications
	 * into WordPress block comment markup (HTML).
	 *
	 * @since 0.3.0
	 */
	trait Template_Block_Handler {

		/**
		 * Gets the serialized block template content for a given post type.
		 *
		 * Reads the `template` argument from the registered post type object
		 * and serializes the blocks into HTML content. Returns an empty string
		 * if the post type does not exist, has no template, or the template
		 * is not an array.
		 *
		 * @since 0.3.0
		 *
		 * @param string $post_type The post type slug.
		 * @return string Serialized block content, or empty string if no template.
		 */
		final protected function get_post_type_template_content( string $post_type ): string {
			$post_type_obj = get_post_type_object( $post_type );

			if ( ! $post_type_obj || empty( $post_type_obj->template ) || ! is_array( $post_type_obj->template ) ) {
				return '';
			}

			$blocks_content = '';

			foreach ( $post_type_obj->template as $block_spec ) {
				if ( ! is_array( $block_spec ) || empty( $block_spec[0] ) ) {
					continue;
				}

				$block_name   = $block_spec[0];
				$block_attrs  = isset( $block_spec[1] ) && is_array( $block_spec[1] ) ? $block_spec[1] : array();
				$inner_blocks = isset( $block_spec[2] ) && is_array( $block_spec[2] ) ? $block_spec[2] : array();

				$blocks_content .= $this->serialize_block_template_entry( $block_name, $block_attrs, $inner_blocks );
			}

			return $blocks_content;
		}

		/**
		 * Serializes a single block template entry into HTML comment markup.
		 *
		 * Recursively handles inner blocks. Produces the standard WordPress
		 * block comment format:
		 * - Void blocks: `<!-- wp:name attrs /-->`
		 * - Container blocks: `<!-- wp:name attrs -->inner<!-- /wp:name -->`
		 *
		 * @since 0.3.0
		 *
		 * @param string $block_name   The block name (e.g., 'core/paragraph').
		 * @param array  $block_attrs  Block attributes.
		 * @param array  $inner_blocks Inner block specifications.
		 * @return string Serialized block HTML.
		 */
		final protected function serialize_block_template_entry( string $block_name, array $block_attrs, array $inner_blocks ): string {
			$attrs_json = ! empty( $block_attrs ) ? ' ' . wp_json_encode( $block_attrs ) : '';

			if ( empty( $inner_blocks ) ) {
				// Self-closing or void block.
				return sprintf( "<!-- wp:%s%s /-->\n", $block_name, $attrs_json );
			}

			$inner_content = '';
			foreach ( $inner_blocks as $inner_spec ) {
				if ( ! is_array( $inner_spec ) || empty( $inner_spec[0] ) ) {
					continue;
				}
				$inner_name  = $inner_spec[0];
				$inner_attrs = isset( $inner_spec[1] ) && is_array( $inner_spec[1] ) ? $inner_spec[1] : array();
				$inner_inner = isset( $inner_spec[2] ) && is_array( $inner_spec[2] ) ? $inner_spec[2] : array();

				$inner_content .= $this->serialize_block_template_entry( $inner_name, $inner_attrs, $inner_inner );
			}

			return sprintf( "<!-- wp:%s%s -->\n%s<!-- /wp:%s -->\n", $block_name, $attrs_json, $inner_content, $block_name );
		}

		/**
		 * Merges template block content with imported content.
		 *
		 * Combines the serialized template blocks with the provided content
		 * string, placing the template either before or after the content
		 * based on the `$template_before` flag.
		 *
		 * @since 0.3.0
		 *
		 * @param string $content         The imported content.
		 * @param string $template_content The serialized template block content.
		 * @param bool   $template_before  Whether to place template before content.
		 * @return string Merged content.
		 */
		final protected function merge_template_with_content( string $content, string $template_content, bool $template_before ): string {
			if ( empty( $template_content ) ) {
				return $content;
			}

			if ( $template_before ) {
				return $template_content . "\n" . $content;
			}

			return $content . "\n" . $template_content;
		}
	}
}