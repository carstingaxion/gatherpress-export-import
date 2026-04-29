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
	 * Uses WordPress core's `serialize_blocks()` function for
	 * correct output of all block types, including dynamic blocks
	 * that require the container format (open + close comments).
	 *
	 * @since 0.3.0
	 */
	trait Template_Block_Handler {

		/**
		 * Gets the serialized block template content for a given post type.
		 *
		 * Reads the `template` argument from the registered post type object,
		 * converts the template block specifications to the format expected
		 * by `serialize_blocks()`, and returns the serialized HTML content.
		 *
		 * Returns an empty string if the post type does not exist, has no
		 * template, or the template is not an array.
		 *
		 * @since 0.3.0
		 *
		 * @param string $post_type The post type slug.
		 * @return string Serialized block content, or empty string if no template.
		 */
		final protected function get_post_type_template_content( string $post_type ): string {
			$post_type_obj = get_post_type_object( $post_type );

			// @phpstan-ignore-next-line
			if ( ! $post_type_obj || empty( $post_type_obj->template ) || ! is_array( $post_type_obj->template ) ) {
				return '';
			}

			/**
			 * This should be the shape coming from WP core.
			 *
			 * @var array<int, array{0?: string, 1?: array<string, mixed>, 2?: array<int, array<mixed>>}> $raw_template
			 */
			$raw_template     = $post_type_obj->template;
			$formatted_blocks = $this->convert_template_to_blocks( $raw_template );

			if ( empty( $formatted_blocks ) ) {
				return '';
			}

			$template_content = serialize_blocks( $formatted_blocks );

			if ( empty( $template_content ) ) {
				return '';
			}

			return $template_content;
		}

		/**
		 * Convert post type template to block format.
		 *
		 * WordPress post type templates are arrays where each element is:
		 * [
		 *   0 => string,                 // Block name (e.g. 'core/paragraph')
		 *   1 => array<string, mixed>,   // Block attributes (optional)
		 *   2 => array<int, array>       // Inner blocks in template format (optional, recursive)
		 * ]
		 *
		 * This converts them to the structure expected by serialize_blocks():
		 * [
		 *   'blockName'    => string|null,
		 *   'attrs'        => array<string, mixed>,
		 *   'innerBlocks'  => array<int, array>,
		 *   'innerHTML'    => string,
		 *   'innerContent' => array<int, string>
		 * ]
		 *
		 * @since 0.3.0
		 *
		 * @phpstan-param array<int, array{0?: string, 1?: array<string, mixed>, 2?: array<int, array<mixed>>}> $template_blocks
		 *
		 * @param array<int, array<mixed>> $template_blocks Post type template blocks (recursive).
		 *
		 * @phpstan-return array<int, array{blockName: string, attrs: array<string, mixed>, innerBlocks: array<int, array<mixed>>, innerHTML: string, innerContent: array<int, string>}>
		 *
		 * @return array<int, array<string, mixed>> Blocks formatted for serialize_blocks().
		 */
		final protected function convert_template_to_blocks( array $template_blocks ): array {
			$blocks = array();

			foreach ( $template_blocks as $template_block ) {
				$block = $this->convert_single_template_block( $template_block );
				if ( null !== $block ) {
					$blocks[] = $block;
				}
			}

			return $blocks;
		}

		/**
		 * Convert a single template block entry to block parser format.
		 *
		 * Parses one entry from a WordPress post type template array
		 * and converts it to the format expected by serialize_blocks().
		 * Recursively converts inner blocks if present.
		 *
		 * @since 0.3.0
		 *
		 * @param mixed $template_block A single template block entry.
		 *                              Expected format: array{0: string, 1?: array<string, mixed>, 2?: array<int, array>}.
		 *
		 * @phpstan-return array{blockName: string, attrs: array<string, mixed>, innerBlocks: array<int, array<mixed>>, innerHTML: string, innerContent: array<int, string>}|null
		 *
		 * @return array<string, mixed>|null Block in parser format, or null if the entry is invalid.
		 */
		private function convert_single_template_block( $template_block ): ?array {
			if ( ! is_array( $template_block ) ) {
				return null;
			}

			$block_name = isset( $template_block[0] ) && is_string( $template_block[0] ) ? $template_block[0] : '';
			if ( empty( $block_name ) ) {
				return null;
			}

			/**
			 * This should be the shape coming from WP core.
			 *
			 * @var array<string, mixed> $block_attrs
			 */
			$block_attrs = isset( $template_block[1] ) && is_array( $template_block[1] ) ? $template_block[1] : array();

			/**
			 * This should be the shape coming from WP core.
			 *
			 * @var array<int, array{0?: string, 1?: array<string, mixed>, 2?: array<int, array<mixed>>}> $inner_blocks
			 */
			$inner_blocks = isset( $template_block[2] ) && is_array( $template_block[2] ) ? $template_block[2] : array();

			return array(
				'blockName'    => $block_name,
				'attrs'        => $block_attrs,
				'innerBlocks'  => ! empty( $inner_blocks ) ? $this->convert_template_to_blocks( $inner_blocks ) : array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}

		/**
		 * Pre-validates serialized block content by round-tripping it
		 * through WordPress's block parser.
		 *
		 * Parses the serialized block markup via `parse_blocks()`, filters
		 * out any freeform (null blockName) entries that are whitespace-only,
		 * and re-serializes the result. This produces canonical block markup
		 * that the block editor recognizes without triggering "attempt block
		 * recovery" warnings.
		 *
		 * @since 0.3.0
		 *
		 * @param string $block_content Serialized block markup.
		 * @return string Validated and re-serialized block markup.
		 */
		final protected function validate_block_content( string $block_content ): string {
			if ( empty( $block_content ) ) {
				return '';
			}

			$parsed = parse_blocks( $block_content );

			if ( empty( $parsed ) ) {
				return '';
			}

			// Filter out freeform (null blockName) entries that are just whitespace.
			// These are inter-block gaps that parse_blocks() produces and that
			// serialize_blocks() would turn into empty lines. Keep only real blocks.
			$filtered = array_values(
				array_filter(
					$parsed,
					function ( array $block ): bool {
						if ( null === $block['blockName'] ) {
							// Keep freeform blocks only if they have non-whitespace content.
							return ! empty( trim( $block['innerHTML'] ?? '' ) );
						}
						return true;
					}
				)
			);

			if ( empty( $filtered ) ) {
				return '';
			}

			return serialize_blocks( $filtered );
		}

		/**
		 * Merges template block content with imported content.
		 *
		 * Combines the serialized template blocks with the provided content
		 * string, placing the template either before or after the content
		 * based on the `$template_before` flag. The template content is
		 * pre-validated by round-tripping through the block parser to
		 * ensure canonical markup that the editor recognizes.
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

			// Pre-validate the template blocks through the block parser
			// to produce canonical markup the editor will accept.
			$validated = $this->validate_block_content( $template_content );

			if ( empty( $validated ) ) {
				return $content;
			}

			if ( $template_before ) {
				return $validated . "\n" . $content;
			}

			return $content . "\n" . $validated;
		}
	}
}
