<?php
/**
 * Custom importer registration for the Tools > Import screen.
 *
 * Registers a "GatherPress Event Migration" importer that displays
 * detailed instructions about import order, prerequisites, and the
 * conversion process before handing off to the WordPress Importer
 * for the actual file upload and processing.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Telex_GPM_Importer' ) ) {
	/**
	 * Class Telex_GPM_Importer.
	 *
	 * Singleton class that registers a custom importer entry on the
	 * Tools > Import admin screen. Provides a dedicated instructions
	 * page with prerequisite checks, step-by-step import guidance,
	 * and supported plugin information before redirecting to the
	 * standard WordPress Importer for file upload.
	 *
	 * @since 0.1.0
	 */
	class Telex_GPM_Importer {

		/**
		 * Singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @var Telex_GPM_Importer|null
		 */
		private static ?Telex_GPM_Importer $instance = null;

		/**
		 * Gets the singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @return Telex_GPM_Importer The singleton instance.
		 */
		public static function get_instance(): Telex_GPM_Importer {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private constructor — sets up admin hooks.
		 *
		 * @since 0.1.0
		 */
		private function __construct() {
			add_action( 'admin_init', array( $this, 'register_importer' ) );
			add_action( 'admin_init', array( $this, 'maybe_redirect_to_wp_importer' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
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
		 * Registers the custom importer with WordPress.
		 *
		 * Adds a "GatherPress Event Migration" entry to the Tools > Import
		 * screen using `register_importer()`.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function register_importer(): void {
			if ( ! function_exists( 'register_importer' ) ) {
				return;
			}

			register_importer(
				'gatherpress-migration',
				__( 'GatherPress Event Migration', 'telex-gatherpress-migration' ),
				__( 'Import events and venues from third-party event plugins (The Events Calendar, Events Manager, MEC, EventON, All-in-One Event Calendar, Event Organiser) into GatherPress.', 'telex-gatherpress-migration' ),
				array( $this, 'dispatch' )
			);
		}

		/**
		 * Enqueues admin styles for the importer screen.
		 *
		 * Only loads the stylesheet on the GatherPress migration importer page.
		 *
		 * @since 0.1.0
		 *
		 * @param string $hook_suffix The current admin page hook suffix.
		 * @return void
		 */
		public function enqueue_admin_styles( string $hook_suffix ): void {
			if ( 'admin.php' !== $hook_suffix ) {
				return;
			}

			if ( ! isset( $_GET['import'] ) || 'gatherpress-migration' !== $_GET['import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			wp_enqueue_style(
				'telex-gpm-importer',
				plugins_url( 'assets/css/importer.css', __FILE__ ),
				array(),
				'0.1.0'
			);
		}

		/**
		 * Redirects to the WordPress Importer early, before headers are sent.
		 *
		 * Hooked to `admin_init` to intercept the step=1 request before
		 * any output has been generated. This avoids the "headers already
		 * sent" problem that occurs when `wp_safe_redirect()` is called
		 * from within the importer's `dispatch()` callback, which runs
		 * after WordPress has already started rendering the admin page.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function maybe_redirect_to_wp_importer(): void {
			if ( ! isset( $_GET['import'] ) || 'gatherpress-migration' !== $_GET['import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 1 !== $step ) {
				return;
			}

			$url = admin_url( 'admin.php?import=wordpress' );
			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * Dispatches the importer screen based on the current step.
		 *
		 * Step 0 (default): Renders the instructions and prerequisites screen.
		 * Step 1 is handled earlier via `maybe_redirect_to_wp_importer()`
		 * on the `admin_init` hook, before any output is sent.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function dispatch(): void {
			$this->render_instructions();
		}

		/**
		 * Renders the importer instructions screen.
		 *
		 * Displays prerequisite checks (GatherPress and WordPress Importer
		 * activation status), critical import order instructions, data
		 * conversion details, supported plugin listing, and important notes.
		 * Provides a proceed button that redirects to the WordPress Importer
		 * when all prerequisites are met.
		 *
		 * Uses only WordPress core admin CSS classes and the admin color
		 * scheme for consistent styling.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		private function render_instructions(): void {
			$proceed_url = add_query_arg(
				array(
					'import' => 'gatherpress-migration',
					'step'   => 1,
				),
				admin_url( 'admin.php' )
			);

			$migration   = Telex_GatherPress_Migration::get_instance();
			$adapters    = $migration->get_adapters();
			$wp_importer = defined( 'WP_IMPORTER_VERSION' ) || class_exists( 'WP_Import' );
			$gatherpress = class_exists( '\GatherPress\Core\Event' );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'GatherPress Event Migration', 'telex-gatherpress-migration' ); ?></h1>

				<p class="description" style="font-size: 14px; max-width: 700px;">
					<?php esc_html_e( 'This tool helps you migrate event data from third-party event plugins into GatherPress. It uses the standard WordPress Importer under the hood — follow the instructions below before uploading your WXR export file.', 'telex-gatherpress-migration' ); ?>
				</p>

				<h2><?php esc_html_e( 'Prerequisites', 'telex-gatherpress-migration' ); ?></h2>
				<table class="widefat striped" style="max-width: 700px;">
					<tbody>
						<tr>
							<td style="width: 36px; text-align: center;">
								<?php if ( $gatherpress ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php esc_html_e( 'GatherPress', 'telex-gatherpress-migration' ); ?></strong><br>
								<?php if ( $gatherpress ) : ?>
									<span style="color: #00a32a;"><?php esc_html_e( 'Active and ready.', 'telex-gatherpress-migration' ); ?></span>
								<?php else : ?>
									<span style="color: #d63638;"><?php esc_html_e( 'Not detected. Please install and activate GatherPress before importing.', 'telex-gatherpress-migration' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td style="width: 36px; text-align: center;">
								<?php if ( $wp_importer ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
								<?php else : ?>
									<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php esc_html_e( 'WordPress Importer', 'telex-gatherpress-migration' ); ?></strong><br>
								<?php if ( $wp_importer ) : ?>
									<span style="color: #00a32a;"><?php esc_html_e( 'Active and ready.', 'telex-gatherpress-migration' ); ?></span>
								<?php else : ?>
									<span style="color: #d63638;"><?php esc_html_e( 'Not detected. Please install and activate the WordPress Importer plugin.', 'telex-gatherpress-migration' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<hr />

				<h2><?php esc_html_e( 'Critical: Import Order Matters', 'telex-gatherpress-migration' ); ?></h2>

				<div class="notice notice-warning inline" style="max-width: 700px;">
					<p><strong><?php esc_html_e( 'Venues must be imported BEFORE events.', 'telex-gatherpress-migration' ); ?></strong></p>
					<p><?php esc_html_e( 'GatherPress uses a shadow taxonomy (_gatherpress_venue) to link events to venues. When a gatherpress_venue post is created, GatherPress automatically creates a corresponding hidden taxonomy term. Events reference venues by being assigned this taxonomy term.', 'telex-gatherpress-migration' ); ?></p>
					<p><?php esc_html_e( 'If venues are not imported first, the shadow taxonomy terms will not exist when events are imported, and venue links will be lost.', 'telex-gatherpress-migration' ); ?></p>
				</div>

				<h3><?php esc_html_e( 'Recommended import sequence', 'telex-gatherpress-migration' ); ?></h3>
				<ol class="telex-gpm-steps" style="max-width: 700px;">
					<li>
						<strong><?php esc_html_e( 'Export venues first', 'telex-gatherpress-migration' ); ?></strong>
						<p class="description"><?php esc_html_e( 'On your source site, go to Tools > Export and export only the venue post type (e.g., tribe_venue, location). Download the WXR file.', 'telex-gatherpress-migration' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Import venues', 'telex-gatherpress-migration' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Upload the venues WXR file via the WordPress Importer. The plugin will automatically convert venue post types to gatherpress_venue. GatherPress will then create the corresponding _gatherpress_venue shadow taxonomy terms.', 'telex-gatherpress-migration' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Export events', 'telex-gatherpress-migration' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Back on the source site, export the event post type (e.g., tribe_events, event, mec-events). Download the WXR file.', 'telex-gatherpress-migration' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Import events', 'telex-gatherpress-migration' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Upload the events WXR file. The plugin will rewrite post types, convert datetimes, and link venues to events via the _gatherpress_venue shadow taxonomy using the ID mapping from step 2.', 'telex-gatherpress-migration' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Flush permalinks', 'telex-gatherpress-migration' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Go to Settings > Permalinks and click Save. Deactivate the source event plugin first if it is still active to avoid slug conflicts.', 'telex-gatherpress-migration' ); ?></p>
					</li>
				</ol>

				<div class="notice notice-info inline" style="max-width: 700px;">
					<p><strong><?php esc_html_e( 'Single-file imports:', 'telex-gatherpress-migration' ); ?></strong> <?php esc_html_e( 'If your WXR file contains both venues and events, ensure venues appear before events in the file. Most export tools list venues first, but verify by opening the XML file.', 'telex-gatherpress-migration' ); ?></p>
				</div>

				<div class="notice notice-info inline" style="max-width: 700px;">
					<p><strong><?php esc_html_e( 'Taxonomy-based venues (Event Organiser):', 'telex-gatherpress-migration' ); ?></strong> <?php esc_html_e( 'Event Organiser stores venues as taxonomy terms, not posts. Import the same WXR file twice: the first import creates gatherpress_venue posts from venue terms (events are skipped), the second import creates events and links them to venues.', 'telex-gatherpress-migration' ); ?></p>
				</div>

				<hr />

				<h2><?php esc_html_e( 'What Gets Converted', 'telex-gatherpress-migration' ); ?></h2>
				<table class="widefat striped" style="max-width: 700px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source Data', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'GatherPress Target', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'Method', 'telex-gatherpress-migration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Event post type', 'telex-gatherpress-migration' ); ?></td>
							<td><code>gatherpress_event</code></td>
							<td><?php esc_html_e( 'Post type rewrite via wp_import_post_data_raw', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Venue post type', 'telex-gatherpress-migration' ); ?></td>
							<td><code>gatherpress_venue</code></td>
							<td><?php esc_html_e( 'Post type rewrite via wp_import_post_data_raw', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Start/end datetimes', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'gp_event_extended table', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Meta stash + adapter conversion', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Venue reference', 'telex-gatherpress-migration' ); ?></td>
							<td><code>_gatherpress_venue</code></td>
							<td><?php esc_html_e( 'ID mapping + shadow taxonomy assignment', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Title, content, featured image', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Standard post fields', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Automatic (WordPress Importer)', 'telex-gatherpress-migration' ); ?></td>
						</tr>
					</tbody>
				</table>

				<hr />

				<h2><?php esc_html_e( 'Supported Source Plugins', 'telex-gatherpress-migration' ); ?></h2>
				<table class="widefat striped" style="max-width: 700px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Plugin', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'Event CPT', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'Venue Handling', 'telex-gatherpress-migration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $adapters as $adapter ) : ?>
							<tr>
								<td><?php echo esc_html( $adapter->get_name() ); ?></td>
								<td>
									<?php
									$event_types = array_keys( $adapter->get_event_post_type_map() );
									if ( ! empty( $event_types ) ) {
										echo '<code>' . esc_html( implode( '</code>, <code>', $event_types ) ) . '</code>';
									} else {
										echo '&mdash;';
									}
									?>
								</td>
								<td>
									<?php
									$venue_types = array_keys( $adapter->get_venue_post_type_map() );
									if ( ! empty( $venue_types ) ) {
										echo '<code>' . esc_html( implode( ', ', $venue_types ) ) . '</code> &rarr; <code>gatherpress_venue</code>';
									} else {
										$venue_key = $adapter->get_venue_meta_key();
										if ( $venue_key ) {
											echo esc_html__( 'Via meta key: ', 'telex-gatherpress-migration' ) . '<code>' . esc_html( $venue_key ) . '</code>';
										} else {
											echo esc_html__( 'Taxonomy or N/A', 'telex-gatherpress-migration' );
										}
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<hr />

				<h2><?php esc_html_e( 'Important Notes', 'telex-gatherpress-migration' ); ?></h2>
				<ul style="max-width: 700px; list-style: disc; padding-left: 20px;">
					<li>
						<strong><?php esc_html_e( 'Always backup first.', 'telex-gatherpress-migration' ); ?></strong>
						<?php esc_html_e( 'Create a full database backup and test the import on a staging site before running it on production.', 'telex-gatherpress-migration' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Shared post type slugs.', 'telex-gatherpress-migration' ); ?></strong>
						<?php esc_html_e( 'Events Manager and Event Organiser both use the "event" post type. The plugin distinguishes between them by inspecting meta keys. Import data from only one source plugin at a time.', 'telex-gatherpress-migration' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Recurring events.', 'telex-gatherpress-migration' ); ?></strong>
						<?php esc_html_e( 'GatherPress treats each occurrence as a separate event. Recurrence rules are not converted — each exported occurrence becomes its own GatherPress event.', 'telex-gatherpress-migration' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Taxonomy-based venues (two-pass import).', 'telex-gatherpress-migration' ); ?></strong>
						<?php esc_html_e( 'Plugins like Event Organiser store venues as taxonomy terms. Import the same WXR file twice: first pass creates venue posts, second pass imports events and links them to venues.', 'telex-gatherpress-migration' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Duplicate prevention.', 'telex-gatherpress-migration' ); ?></strong>
						<?php esc_html_e( 'Importing the same file twice may create duplicates. Always import into a clean environment or verify existing data first.', 'telex-gatherpress-migration' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Shortcodes in content.', 'telex-gatherpress-migration' ); ?></strong>
						<?php esc_html_e( 'Source plugin shortcodes will appear as raw text. Review imported event content and clean up as needed.', 'telex-gatherpress-migration' ); ?>
					</li>
				</ul>

				<hr />

				<?php if ( $gatherpress && $wp_importer ) : ?>
					<p>
						<?php esc_html_e( 'Ready to import? Click the button below to proceed to the WordPress Importer file upload screen. The migration plugin will automatically intercept and convert the data during import.', 'telex-gatherpress-migration' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( $proceed_url ); ?>" class="button button-primary button-hero">
							<?php esc_html_e( 'Proceed to WordPress Importer', 'telex-gatherpress-migration' ); ?>
						</a>
					</p>
				<?php else : ?>
					<div class="notice notice-error inline" style="max-width: 700px;">
						<p><strong><?php esc_html_e( 'Cannot proceed.', 'telex-gatherpress-migration' ); ?></strong> <?php esc_html_e( 'Please install and activate the required plugins listed above before importing.', 'telex-gatherpress-migration' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}
