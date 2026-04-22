<?php
/**
 * WXR Import Helper trait for integration tests.
 *
 * Provides a reusable method to programmatically run the WordPress
 * Importer against a WXR fixture file within a test method.
 *
 * @package GatherPressExportImport\Tests
 * @since   0.1.0
 */

namespace GatherPressExportImport\Tests;

/**
 * Trait WXRImportHelper.
 *
 * Wraps the WordPress Importer's `import()` method for use in PHPUnit tests.
 * Requires the WordPress Importer plugin to be loaded.
 *
 * @since 0.1.0
 */
trait WXRImportHelper {

	/**
	 * Runs the WordPress Importer against a WXR file.
	 *
	 * Instantiates a fresh `WP_Import` object and imports the given file.
	 * Author mapping defaults to the current user. Attachments are not
	 * fetched by default.
	 *
	 * @since 0.1.0
	 *
	 * @param string $wxr_file         Absolute path to the WXR file.
	 * @param bool   $fetch_attachments Whether to download attachments. Default false.
	 * @return \WP_Import|null The importer instance after import, or null on failure.
	 */
	final protected function import_wxr( string $wxr_file, bool $fetch_attachments = false ): ?\WP_Import {
		if ( ! class_exists( 'WP_Import' ) ) {
			// Try loading the WordPress Importer plugin file.
			$importer_file = WP_PLUGIN_DIR . '/wordpress-importer/wordpress-importer.php';
			if ( file_exists( $importer_file ) ) {
				require_once $importer_file;
			}

			if ( ! class_exists( 'WP_Import' ) ) {
				$this->markTestSkipped( 'WordPress Importer (WP_Import class) is not available.' );
				return null;
			}
		}

		if ( ! file_exists( $wxr_file ) ) {
			$this->fail( 'WXR fixture file not found: ' . $wxr_file );
			return null;
		}

		$importer                    = new \WP_Import();
		$importer->fetch_attachments = $fetch_attachments;

		// Suppress output from the importer.
		ob_start();
		$importer->import( $wxr_file );
		ob_end_clean();

		return $importer;
	}

	/**
	 * Gets the absolute path to a WXR fixture file.
	 *
	 * @since 0.1.0
	 *
	 * @param string $filename The fixture filename (e.g., 'event-organiser.xml').
	 * @return string Absolute file path.
	 */
	final protected function get_wxr_fixture_path( string $filename ): string {
		return dirname( __DIR__, 2 ) . '/fixtures/wxr/' . $filename;
	}
}
