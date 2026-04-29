<?php
/**
 * Plugin Name:       GatherPress Export Import
 * Description:       Intercepts WordPress XML imports from third-party event plugins and transforms them into GatherPress data using pseudopostmeta callbacks.
 * Version:           0.4.1
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

// Load adapter classes.
require_once __DIR__ . '/includes/classes/adapters/class-tec-adapter.php';
require_once __DIR__ . '/includes/classes/adapters/class-events-manager-adapter.php';
require_once __DIR__ . '/includes/classes/adapters/class-mec-adapter.php';
require_once __DIR__ . '/includes/classes/adapters/class-eventon-adapter.php';
require_once __DIR__ . '/includes/classes/adapters/class-aioec-adapter.php';
require_once __DIR__ . '/includes/classes/adapters/class-event-organiser-adapter.php';

// Load classes.
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

/**
 * Enqueues the editor script that registers the command palette entry
 * for the ICS importer and localises the nonce / tools URL.
 *
 * @since 0.3.0
 *
 * @return void
 */
if ( ! function_exists( 'gpei_enqueue_admin_assets' ) ) {
	/**
	 * Enqueues the script that registers the command palette entry
	 * for the ICS importer on every admin screen so the command is
	 * available dashboard-wide, not only inside the block editor.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	function gpei_enqueue_admin_assets(): void {
		$asset_file = __DIR__ . '/build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'gpei-ics-command',
			plugins_url( 'build/index.js', __FILE__ ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'gpei-ics-command',
			'gpeiIcsImporter',
			array(
				'nonce'    => wp_create_nonce( 'gpei_ics_import' ),
				'toolsUrl' => admin_url( 'tools.php' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\gpei_enqueue_admin_assets' );
