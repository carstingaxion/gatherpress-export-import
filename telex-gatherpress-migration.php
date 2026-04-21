<?php
/**
 * Plugin Name:       GatherPress Export Import
 * Description:       Intercepts WordPress XML imports from third-party event plugins and transforms them into GatherPress data using pseudopostmeta callbacks.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Carsten Bach
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       telex-gatherpress-migration
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load architecture files.
require_once __DIR__ . '/class-telex-gpm-importer.php';
require_once __DIR__ . '/interface-telex-gpm-source-adapter.php';
require_once __DIR__ . '/interface-telex-gpm-hookable-adapter.php';
require_once __DIR__ . '/trait-telex-gpm-datetime-helper.php';

require_once __DIR__ . '/class-telex-gpm-tec-adapter.php';
require_once __DIR__ . '/class-telex-gpm-events-manager-adapter.php';
require_once __DIR__ . '/class-telex-gpm-mec-adapter.php';
require_once __DIR__ . '/class-telex-gpm-eventon-adapter.php';
require_once __DIR__ . '/class-telex-gpm-aioec-adapter.php';
require_once __DIR__ . '/class-telex-gpm-event-organiser-adapter.php';

require_once __DIR__ . '/class-telex-gatherpress-migration.php';


// Boot the plugin.
Telex_GatherPress_Migration::get_instance();
Telex_GPM_Importer::get_instance();

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 *
 * @since 0.1.0
 *
 * @return void
 */
if ( ! function_exists( 'telex_gatherpress_migration_block_init' ) ) {
	function telex_gatherpress_migration_block_init(): void {
		register_block_type( __DIR__ . '/build/' );
	}
}
add_action( 'init', 'telex_gatherpress_migration_block_init' );
