<?php
/**
 * Plugin Name:       GatherPress Export Import
 * Description:       Intercepts WordPress XML imports from third-party event plugins and transforms them into GatherPress data using pseudopostmeta callbacks.
 * Version:           0.3.0
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

// Load interfaces.
require_once __DIR__ . '/includes/interfaces/interface-source-adapter.php';
require_once __DIR__ . '/includes/interfaces/interface-hookable-adapter.php';
require_once __DIR__ . '/includes/interfaces/interface-taxonomy-venue-adapter.php';

// Load traits.
require_once __DIR__ . '/includes/traits/trait-datetime-helper.php';
require_once __DIR__ . '/includes/traits/trait-taxonomy-venue-handler.php';
require_once __DIR__ . '/includes/traits/trait-venue-detail-handler.php';
require_once __DIR__ . '/includes/traits/trait-template-block-handler.php';

// Load classes.
require_once __DIR__ . '/includes/classes/class-tec-adapter.php';
require_once __DIR__ . '/includes/classes/class-events-manager-adapter.php';
require_once __DIR__ . '/includes/classes/class-mec-adapter.php';
require_once __DIR__ . '/includes/classes/class-eventon-adapter.php';
require_once __DIR__ . '/includes/classes/class-aioec-adapter.php';
require_once __DIR__ . '/includes/classes/class-event-organiser-adapter.php';

require_once __DIR__ . '/includes/classes/class-adapter-registry.php';
require_once __DIR__ . '/includes/classes/class-post-type-rewriter.php';
require_once __DIR__ . '/includes/classes/class-taxonomy-rewriter.php';
require_once __DIR__ . '/includes/classes/class-meta-stasher.php';
require_once __DIR__ . '/includes/classes/class-stash-processor.php';
require_once __DIR__ . '/includes/classes/class-importer.php';
require_once __DIR__ . '/includes/classes/class-ics-importer.php';
require_once __DIR__ . '/includes/classes/class-migration.php';


// Boot the plugin.
Migration::get_instance();
Importer::get_instance();
ICS_Importer::get_instance();
