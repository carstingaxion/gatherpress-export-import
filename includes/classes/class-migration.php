<?php
/**
 * Main migration orchestrator (singleton).
 *
 * Composes the focused SOC classes (Adapter_Registry, Post_Type_Rewriter,
 * Taxonomy_Rewriter, Meta_Stasher, Stash_Processor) and wires their hooks.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Migration' ) ) {
	/**
	 * Class Migration.
	 *
	 * Singleton orchestrator that wires together the adapter registry,
	 * post type rewriter, taxonomy rewriter, meta stasher, and stash
	 * processor. Provides access to the individual SOC class instances
	 * for direct use by tests and external consumers.
	 *
	 * @since 0.1.0
	 */
	class Migration {

		/**
		 * Singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @var Migration|null
		 */
		private static ?Migration $instance = null;

		/**
		 * Adapter registry.
		 *
		 * @since 0.3.0
		 *
		 * @var Adapter_Registry
		 */
		private Adapter_Registry $registry;

		/**
		 * Post type rewriter.
		 *
		 * @since 0.3.0
		 *
		 * @var Post_Type_Rewriter
		 */
		private Post_Type_Rewriter $post_type_rewriter;

		/**
		 * Taxonomy rewriter.
		 *
		 * @since 0.3.0
		 *
		 * @var Taxonomy_Rewriter
		 */
		private Taxonomy_Rewriter $taxonomy_rewriter;

		/**
		 * Meta stasher.
		 *
		 * @since 0.3.0
		 *
		 * @var Meta_Stasher
		 */
		private Meta_Stasher $meta_stasher;

		/**
		 * Stash processor.
		 *
		 * @since 0.3.0
		 *
		 * @var Stash_Processor
		 */
		private Stash_Processor $stash_processor;

		/**
		 * Gets the singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @return Migration The singleton instance.
		 */
		public static function get_instance(): Migration {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private constructor — composes SOC classes and wires hooks.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			$this->registry           = new Adapter_Registry();
			$this->post_type_rewriter = new Post_Type_Rewriter( $this->registry );
			$this->taxonomy_rewriter  = new Taxonomy_Rewriter( $this->registry );
			$this->meta_stasher       = new Meta_Stasher( $this->registry );
			$this->stash_processor    = new Stash_Processor( $this->registry );

			$this->register_default_adapters();
			$this->setup_hooks();
		}

		/**
		 * Prevents cloning of the singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Registers the built-in source adapters.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function register_default_adapters(): void {
			$this->register_adapter( new TEC_Adapter() );
			$this->register_adapter( new Events_Manager_Adapter() );
			$this->register_adapter( new MEC_Adapter() );
			$this->register_adapter( new EventON_Adapter() );
			$this->register_adapter( new AIOEC_Adapter() );
			$this->register_adapter( new Event_Organiser_Adapter() );
		}

		/**
		 * Registers a source adapter.
		 *
		 * Delegates to the adapter registry.
		 *
		 * @since 0.1.0
		 *
		 * @param Source_Adapter $adapter The adapter to register.
		 * @return void
		 */
		public function register_adapter( Source_Adapter $adapter ): void {
			$this->registry->register( $adapter );
		}

		/**
		 * Gets all registered adapters.
		 *
		 * @since 0.1.0
		 *
		 * @return Source_Adapter[]
		 */
		public function get_adapters(): array {
			return $this->registry->get_adapters();
		}

		/**
		 * Gets the adapter registry instance.
		 *
		 * @since 0.3.0
		 *
		 * @return Adapter_Registry
		 */
		public function get_registry(): Adapter_Registry {
			return $this->registry;
		}

		/**
		 * Gets the post type rewriter instance.
		 *
		 * @since 0.3.0
		 *
		 * @return Post_Type_Rewriter
		 */
		public function get_post_type_rewriter(): Post_Type_Rewriter {
			return $this->post_type_rewriter;
		}

		/**
		 * Gets the taxonomy rewriter instance.
		 *
		 * @since 0.3.0
		 *
		 * @return Taxonomy_Rewriter
		 */
		public function get_taxonomy_rewriter(): Taxonomy_Rewriter {
			return $this->taxonomy_rewriter;
		}

		/**
		 * Gets the meta stasher instance.
		 *
		 * @since 0.3.0
		 *
		 * @return Meta_Stasher
		 */
		public function get_meta_stasher(): Meta_Stasher {
			return $this->meta_stasher;
		}

		/**
		 * Gets the stash processor instance.
		 *
		 * @since 0.3.0
		 *
		 * @return Stash_Processor
		 */
		public function get_stash_processor(): Stash_Processor {
			return $this->stash_processor;
		}

		/**
		 * Sets up all WordPress hooks by delegating to SOC classes.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function setup_hooks(): void {
			$this->post_type_rewriter->setup_hooks();
			$this->taxonomy_rewriter->setup_hooks();
			$this->meta_stasher->setup_hooks();
			$this->stash_processor->setup_hooks();

			add_filter( 'gatherpress_pseudopostmetas', array( $this, 'register_pseudopostmetas' ) );
		}

		/**
		 * Registers pseudopostmeta entries from all adapters.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array{post_type: string, import_callback: callable}> $pseudopostmetas Existing entries.
		 * @return array<string, array{post_type: string, import_callback: callable}> Merged entries.
		 */
		public function register_pseudopostmetas( array $pseudopostmetas ): array {
			return $this->registry->merge_pseudopostmetas( $pseudopostmetas );
		}
	}
}