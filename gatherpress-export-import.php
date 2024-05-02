<?php
/*
Plugin Name: GatherPress | Export & Import
Plugin URI:
Description: Use WordPress' native export & import tools for GatherPress data
Version: 0.1.0
Author: Carsten Bach
Author URI: https://gatherpress.org
Text Domain: gatherpress-export-import
Domain Path: /languages
*/

/**
 * Try to solve:
 * 
 * Export / Import of events does not recognise date & time
 * https://github.com/GatherPress/gatherpress/issues/650
 */

namespace GatherPress\ExportImport;

/**
 * 1. Preparation
 */
function pseudopostmetas(): array {
	return \apply_filters(
		'gatherpress_pseudopostmetas',
		[
			'gatherpress_pseudopostmeta_KEY' => [
				'export_callback' => __NAMESPACE__ . '\\export_KEY_callback',
				'import_callback' => __NAMESPACE__ . '\\import_KEY_callback',
			],
			'gatherpress_datetimes' => [
				'export_callback' => __NAMESPACE__ . '\\export_datetimes_callback',
				'import_callback' => __NAMESPACE__ . '\\import_datetimes_callback',
			],
		]
	);
}


/**
 * 2. Export
 *
 * WordPress' native Export could maybe extended in hacky way at 
 * https://github.com/WordPress/wordpress-develop/blob/6.5/src/wp-admin/includes/export.php#L655-L677,
 * using - wxr_export_skip_postmeta where we could echo out some pseudo-post-meta fields,
 * before returning false like the default.
 *
 * Filters whether to selectively skip post meta used for WXR exports.
 * Returning a truthy value from the filter will skip the current meta object from being exported.
 *
 * @see https://developer.wordpress.org/reference/hooks/wxr_export_skip_postmeta/
 *
 * A problem or caveat could be, that this filter only runs,
 * if a post has real-existing data in the post_meta table.
 * Right now, this whole operation relies on the existence of the 'online_event_link' post meta key.
 */
\add_filter(
	'wxr_export_skip_postmeta',
	__NAMESPACE__ . '\\wxr_export_skip_postmeta',
	10,
	3
);


/**
 * No need to use this filter in real,
 * GatherPress just uses it as entry-point into
 * WordPress' native export process.
 *
 * @param  bool   $skip
 * @param  string $meta_key
 * @param  mixed  $meta_data
 *
 * @return bool
 */
function wxr_export_skip_postmeta( bool $skip, string $meta_key, mixed $meta_data ): bool {
	if ( validate_export_object( $meta_key ) ) {
		\do_action( 'gatherpress_export', \get_post() );
	}
	return $skip;
}

function validate_export_object( string $meta_key = '' ): bool {
	
	if ( 'gp_event' !== \get_post_type() ) {
		return false;
	}
	// if ( '_edit_last' !== $meta_key ) {
	if ( 'online_event_link' !== $meta_key ) {
		return false;
	}
	return true;
}

\add_action(
	'gatherpress_export',
	__NAMESPACE__ . '\\export'
);
function export( \WP_Post $post ): void {
	$pseudopostmetas = pseudopostmetas();
	array_walk(
		$pseudopostmetas,
		function ( array $callbacks, string $key ) use ( $post ) {
			if ( ! isset( $callbacks['export_callback'] ) || ! is_callable( $callbacks['export_callback'] ) ) {
				return;
			}
			$value = call_user_func( $callbacks['export_callback'], $post );
			?>
			<wp:postmeta>
				<wp:meta_key><?php echo \wxr_cdata( $key ); ?></wp:meta_key>
				<wp:meta_value><?php echo \wxr_cdata( $value ); ?></wp:meta_value>
			</wp:postmeta>
			<?php
		}
	);
}




/**
 * 3. Import
 * 
 * Import will be relatively easy I think,
 * because we can use the 'wp_import_post_meta' filter
 * to unset the pseudo-post-meta and save the data into the custom DB table.
 * 
 * @source https://github.com/WordPress/wordpress-importer/blob/71bdd41a2aa2c6a0967995ee48021037b39a1097/src/class-wp-import.php#L872
 */
\add_filter(
	'wp_import_post_meta',
	__NAMESPACE__ . '\\wp_import_post_meta',
	10,
	3
);

/**
 * 
 *
 * @param  array $postmeta
 * @param  int   $post_id
 * @param  array $post_data_raw The result of 'wp_import_post_data_raw'. @see https://github.com/WordPress/wordpress-importer/blob/71bdd41a2aa2c6a0967995ee48021037b39a1097/src/class-wp-import.php#L631
 *
 * @return array
 */
function wp_import_post_meta( array $postmeta, int $post_id, array $post_data_raw ): array {
	if ( validate_import_object( $post_data_raw ) ) {
		\do_action( 'gatherpress_import' );
	}
	return $postmeta;
}


function validate_import_object( array $postdata ): bool {
	if ( ! isset( $postdata['post_type'] ) || 'gp_event' !== $postdata['post_type'] ) {
		return false;
	}
	return true;
}

\add_action(
	'gatherpress_import',
	__NAMESPACE__ . '\\import'
);
function import(): void {
	\add_filter(
		'add_post_metadata',
		__NAMESPACE__ . '\\add_post_metadata',
		10,
		5
	);
}

/**
 * 
 * 
 * @see https://developer.wordpress.org/reference/hooks/add_meta_type_metadata/
 * @see https://www.ibenic.com/hook-wordpress-metadata/
 *
 * @param  [type] $save
 * @param  int    $object_id
 * @param  string $meta_key
 * @param  mixed  $meta_value
 * @param  bool   $unique
 *
 * @return void
 */
function add_post_metadata( null|bool $save, int $object_id, string $meta_key, mixed $meta_value, bool $unique ): ?bool {
	$pseudopostmetas = pseudopostmetas();
	if ( ! isset( $pseudopostmetas[ $meta_key ] ) ) {
		return $save;
	}
	if ( ! isset( $pseudopostmetas[ $meta_key ], $pseudopostmetas[ $meta_key ]['import_callback'] ) || ! is_callable( $pseudopostmetas[ $meta_key ]['import_callback'] ) ) {
		return $save;
	}
	/**
	 * Save data, e.g. into a custom DB table.
	 */
	call_user_func( 
		$pseudopostmetas[ $meta_key ]['import_callback'],
		$object_id,
		$meta_value
	);
	/**
	 * Returning a non-null value will effectively short-circuit the saving of 'normal' meta data.
	 */
	return false;
}





/**
 * 4. Misc.
 * 
 * Functions, that could live anywhere inside or outside of GatherPress.
 */
function export_KEY_callback( \WP_Post $post ) : string {
	return 'some data, that is safed somewhere, but not inside of post_meta. Belonging to ' . $post->ID;
}
function import_KEY_callback( int $post_id, string $data ) : void {
	// Save $data into some place, which is not post_meta.
	error_log( 
		var_export(
			[
				__FUNCTION__,
				__FILE__, // helpful to find origin of debug statements
				$post_id,
				$data,
			],
			true
		)
	);
}


function export_datetimes_callback( \WP_Post $post ): string {
	// Make sure to not get any user-related data.
	\remove_all_filters( 'gatherpress_timezone' );
	//
	$event = new \GatherPress\Core\Event( $post->ID );
	return \maybe_serialize( $event->get_datetime() );
}

function import_datetimes_callback( int $post_id, array $data ): void {
	// Save $data into some place, which is not post_meta.
	$event = new \GatherPress\Core\Event( $post_id );
	$event->save_datetimes( \maybe_unserialize( $data ) );
}

