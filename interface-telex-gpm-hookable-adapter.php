<?php
/**
 * Interface for adapters that need to register their own import hooks.
 *
 * Some source adapters (e.g., Event Organiser) require custom import
 * hooks beyond the standard post type rewriting and meta stashing
 * handled by the main migration class. This interface allows adapters
 * to declare that they need to register additional hooks during the
 * import process.
 *
 * The main migration class calls `setup_import_hooks()` on any adapter
 * that implements this interface when the adapter is registered.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'Telex_GPM_Hookable_Adapter' ) ) {
	/**
	 * Interface Telex_GPM_Hookable_Adapter.
	 *
	 * Defines a contract for adapters that require their own WordPress
	 * hooks to be registered during the import process. This keeps
	 * adapter-specific import logic encapsulated within the adapter
	 * rather than in the main migration orchestrator.
	 *
	 * @since 0.1.0
	 */
	interface Telex_GPM_Hookable_Adapter {

		/**
		 * Sets up adapter-specific import hooks.
		 *
		 * Called by the main migration class when the adapter is registered.
		 * The adapter should register any WordPress hooks it needs for
		 * its custom import logic (e.g., term interception, post skipping,
		 * venue linking).
		 *
		 * Implementations should be idempotent — calling this method
		 * multiple times must not register duplicate hooks.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function setup_import_hooks(): void;
	}
}
