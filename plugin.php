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
 * Text Domain:       gatherpress-export-import
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load architecture files.
require_once __DIR__ . '/interface-source-adapter.php';
require_once __DIR__ . '/interface-hookable-adapter.php';
require_once __DIR__ . '/interface-taxonomy-venue-adapter.php';
require_once __DIR__ . '/trait-datetime-helper.php';
require_once __DIR__ . '/trait-taxonomy-venue-handler.php';

require_once __DIR__ . '/class-tec-adapter.php';
require_once __DIR__ . '/class-events-manager-adapter.php';
require_once __DIR__ . '/class-mec-adapter.php';
require_once __DIR__ . '/class-eventon-adapter.php';
require_once __DIR__ . '/class-aioec-adapter.php';
require_once __DIR__ . '/class-event-organiser-adapter.php';

require_once __DIR__ . '/class-importer.php';
require_once __DIR__ . '/class-migration.php';


// Boot the plugin.
Migration::get_instance();
Importer::get_instance();
