<?php
/**
 * Custom importer registration for the Tools > Import screen.
 *
 * Registers a "GatherPress Event Migration" importer that displays
 * detailed instructions about import order, prerequisites, and the
 * conversion process before handing off to the WordPress Importer
 * for the actual file upload and processing.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Importer' ) ) {
	/**
	 * Class Importer.
	 *
	 * Singleton class that registers a custom importer entry on the
	 * Tools > Import admin screen. Provides a dedicated instructions
	 * page with prerequisite checks, step-by-step import guidance,
	 * and supported plugin information before redirecting to the
	 * standard WordPress Importer for file upload.
	 *
	 * @since 0.1.0
	 */
	class Importer {

		/**
		 * Singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @var Importer|null
		 */
		private static ?Importer $instance = null;

		/**
		 * Gets the singleton instance.
		 *
		 * @since 0.1.0
		 *
		 * @return Importer The singleton instance.
		 */
		public static function get_instance(): Importer {
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
			// KEEP // Maybe used for two-pass imports later on!
			// add_action( 'admin_init', array( $this, 'maybe_redirect_to_wp_importer' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
			add_action( 'admin_notices', array( $this, 'maybe_show_two_pass_notice' ) );
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
				__( 'GatherPress Event Migration', 'gatherpress-export-import' ),
				__( 'Import events and venues from third-party event plugins (The Events Calendar, Events Manager, MEC, EventON, All-in-One Event Calendar, Event Organiser) into GatherPress.', 'gatherpress-export-import' ),
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
				'gpei-importer',
				plugins_url( 'assets/css/importer.css', dirname( __DIR__ ) . '/../plugin.php' ),
				array(),
				'0.1.0'
			);
		}

		/**
		 * KEEP // Maybe used for two-pass imports later on!
		 *
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

		public function maybe_redirect_to_wp_importer(): void {
			if ( ! isset( $_GET['import'] ) || 'gatherpress-migration' !== $_GET['import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 1 !== $step ) {
				return;
			}

			$url = admin_url( 'admin.php?import=WordPress' );
			wp_safe_redirect( $url );
			exit;
		} */

		/**
		 * Shows an admin notice reminding the user to run Pass 2 of a two-pass import.
		 *
		 * Checks for the existence of `gatherpress_venue` posts that still have
		 * the `_gpei_source_venue_term_slug` post meta. This meta is created
		 * during Pass 1 (venue creation from taxonomy terms) and cleaned up
		 * during Pass 2 (event import with venue linking). Its presence indicates
		 * that Pass 1 has been completed but Pass 2 has not yet run.
		 *
		 * The notice is displayed on the Tools > Import page and on the
		 * GatherPress migration importer page.
		 *
		 * @since 0.2.0
		 *
		 * @return void
		 */
		public function maybe_show_two_pass_notice(): void {
			$screen = get_current_screen();

			if ( ! $screen ) {
				return;
			}

			// Show only on import-related admin pages.
			$allowed_screens = array( 'import', 'admin' );
			if ( ! in_array( $screen->base, $allowed_screens, true ) ) {
				return;
			}

			// Check for venue posts that still have the source venue term slug meta,
			// which indicates Pass 1 has completed but Pass 2 has not.
			$venues_pending_pass2 = get_posts(
				array(
					'post_type'      => 'gatherpress_venue',
					'meta_key'       => '_gpei_source_venue_term_slug',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( empty( $venues_pending_pass2 ) ) {
				return;
			}

			// Count how many venues are waiting.
			$venue_count = count(
				get_posts(
					array(
						'post_type'      => 'gatherpress_venue',
						'meta_key'       => '_gpei_source_venue_term_slug',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
					)
				)
			);

			$importer_url = admin_url( 'admin.php?import=wordpress' ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e( 'GatherPress Migration — Step 2 Required', 'gatherpress-export-import' ); ?></strong>
				</p>
				<p>
					<?php
					printf(
						esc_html(
							/* translators: %d: number of venue posts waiting for event linking */
							_n(
								'%d venue was created from taxonomy terms during the first import pass, but events have not been imported yet.',
								'%d venues were created from taxonomy terms during the first import pass, but events have not been imported yet.',
								$venue_count,
								'gatherpress-export-import'
							)
						),
						esc_html( $venue_count )
					);
					?>
				</p>
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: %s: URL to the WordPress Importer */
							__( 'To complete the migration, <a href="%s"><strong>import the same WXR file again</strong></a>. This second pass will create the events and link them to the venues created in step 1.', 'gatherpress-export-import' ),
							array(
								'a'      => array( 'href' => array() ),
								'strong' => array(),
							)
						),
						esc_url( $importer_url )
					);
					?>
				</p>
			</div>
			<?php
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
					// KEEP // Maybe used for two-pass imports later on!
					// 'import' => 'gatherpress-migration',
					'import' => 'wordpress',
					// 'step'   => 1,
				),
				admin_url( 'admin.php' )
			);

			$wp_importer = defined( 'WP_IMPORTER_VERSION' ) || class_exists( 'WP_Import' );
			$gatherpress = class_exists( '\GatherPress\Core\Event' );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'GatherPress Event Migration', 'gatherpress-export-import' ); ?></h1>

				<p class="description" style="font-size: 14px; max-width: 700px;">
					<?php esc_html_e( 'This tool helps you migrate event data from third-party event plugins into GatherPress. It uses the standard WordPress Importer under the hood — follow the instructions below before uploading your WXR export file.', 'gatherpress-export-import' ); ?>
				</p>

				<h2><?php esc_html_e( 'Prerequisites', 'gatherpress-export-import' ); ?></h2>
				<table class="widefat striped" style="max-width: 700px;">
					<tbody>
						<tr>
							<td style="width: 36px; text-align: center;">
								<?php if ( $gatherpress ) { ?>
									<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
								<?php } else { ?>
									<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
								<?php } ?>
							</td>
							<td>
								<strong><?php esc_html_e( 'GatherPress', 'gatherpress-export-import' ); ?></strong><br>
								<?php if ( $gatherpress ) { ?>
									<span style="color: #00a32a;"><?php esc_html_e( 'Active and ready.', 'gatherpress-export-import' ); ?></span>
								<?php } else { ?>
									<span style="color: #d63638;"><?php esc_html_e( 'Not detected. Please install and activate GatherPress before importing.', 'gatherpress-export-import' ); ?></span>
								<?php } ?>
							</td>
						</tr>
						<tr>
							<td style="width: 36px; text-align: center;">
								<?php if ( $wp_importer ) { ?>
									<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
								<?php } else { ?>
									<span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
								<?php } ?>
							</td>
							<td>
								<strong><?php esc_html_e( 'WordPress Importer', 'gatherpress-export-import' ); ?></strong><br>
								<?php if ( $wp_importer ) { ?>
									<span style="color: #00a32a;"><?php esc_html_e( 'Active and ready.', 'gatherpress-export-import' ); ?></span>
								<?php } else { ?>
									<span style="color: #d63638;"><?php esc_html_e( 'Not detected. Please install and activate the WordPress Importer plugin.', 'gatherpress-export-import' ); ?></span>
								<?php } ?>
							</td>
						</tr>
					</tbody>
				</table>

				<hr />

				<h3><?php esc_html_e( 'Recommended import sequence', 'gatherpress-export-import' ); ?></h3>
				<ol class="gpei-steps" style="max-width: 700px;">
					<li>
						<strong><?php esc_html_e( 'Export venues first', 'gatherpress-export-import' ); ?></strong>
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: 1: tribe_venue post type, 2: location post type */
									__( 'On your source site, go to Tools &gt; Export and export only the venue post type (e.g., <code>%1$s</code>, <code>%2$s</code>). Download the WXR file.', 'gatherpress-export-import' ),
									array( 'code' => array() )
								),
								'tribe_venue',
								'location'
							);
							?>
						</p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Import venues', 'gatherpress-export-import' ); ?></strong>
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: 1: gatherpress_venue post type, 2: _gatherpress_venue taxonomy */
									__( 'Upload the venues WXR file via the WordPress Importer. The plugin will automatically convert venue post types to <code>%1$s</code>. GatherPress will then create the corresponding <code>%2$s</code> shadow taxonomy terms.', 'gatherpress-export-import' ),
									array( 'code' => array() )
								),
								'gatherpress_venue',
								'_gatherpress_venue'
							);
							?>
						</p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Export events', 'gatherpress-export-import' ); ?></strong>
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: 1: tribe_events post type, 2: event post type, 3: mec-events post type */
									__( 'Back on the source site, export the event post type (e.g., <code>%1$s</code>, <code>%2$s</code>, <code>%3$s</code>). Download the WXR file.', 'gatherpress-export-import' ),
									array( 'code' => array() )
								),
								'tribe_events',
								'event',
								'mec-events'
							);
							?>
						</p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Import events', 'gatherpress-export-import' ); ?></strong>
						<p class="description">
							<?php
							printf(
								wp_kses(
									/* translators: %s: _gatherpress_venue taxonomy */
									__( 'Upload the events WXR file. The plugin will rewrite post types, convert datetimes, and link venues to events via the <code>%s</code> shadow taxonomy using the ID mapping from step 2.', 'gatherpress-export-import' ),
									array( 'code' => array() )
								),
								'_gatherpress_venue'
							);
							?>
						</p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Flush permalinks', 'gatherpress-export-import' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Go to Settings > Permalinks and click Save. Deactivate the source event plugin first if it is still active to avoid slug conflicts.', 'gatherpress-export-import' ); ?></p>
					</li>
				</ol>

				<hr />

				<h2><?php esc_html_e( 'Supported Source Plugins', 'gatherpress-export-import' ); ?></h2>
				<table class="widefat striped" style="max-width: 700px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source Plugin', 'gatherpress-export-import' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'Import', 'gatherpress-export-import' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'Manually Tested', 'gatherpress-export-import' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'PHPUnit Tested', 'gatherpress-export-import' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'The Events Calendar', 'gatherpress-export-import' ); ?></strong> <span class="description">(StellarWP)</span></td>
							<td style="text-align: center;">✅</td>
							<td style="text-align: center;">✅</td>
							<td style="text-align: center;">✅</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Events Manager', 'gatherpress-export-import' ); ?></strong></td>
							<td style="text-align: center;">✅</td>
							<td style="text-align: center;">✅</td>
							<td style="text-align: center;">✅</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Modern Events Calendar', 'gatherpress-export-import' ); ?></strong> <span class="description">(Webnus)</span></td>
							<td style="text-align: center;">⚠️</td>
							<td style="text-align: center;">❌</td>
							<td style="text-align: center;">❌</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'All-in-One Event Calendar', 'gatherpress-export-import' ); ?></strong></td>
							<td style="text-align: center;">⚠️</td>
							<td style="text-align: center;">❌</td>
							<td style="text-align: center;">❌</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'EventON', 'gatherpress-export-import' ); ?></strong></td>
							<td style="text-align: center;">⚠️</td>
							<td style="text-align: center;">❌</td>
							<td style="text-align: center;">❌</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Event Organiser', 'gatherpress-export-import' ); ?></strong> <span class="description">(Stephen Harris)</span></td>
							<td style="text-align: center;">⚠️</td>
							<td style="text-align: center;">✅</td>
							<td style="text-align: center;">✅</td>
						</tr>
					</tbody>
				</table>
				<p class="description" style="max-width: 700px;">
					<?php esc_html_e( '✅ Fully supported/tested — ⚠️ Partial (some data unavailable via WXR) — ❌ Not yet', 'gatherpress-export-import' ); ?>
				</p>

				<hr />

				<details style="max-width: 700px;">
					<summary><h2 style="display: inline; cursor: pointer;"><?php esc_html_e( 'Notes', 'gatherpress-export-import' ); ?></h2></summary>
					<ul style="list-style: disc; padding-left: 20px; margin-top: 12px;">
						<li>
							<strong><?php esc_html_e( 'Always backup first.', 'gatherpress-export-import' ); ?></strong>
							<?php esc_html_e( 'Create a full database backup and test the import on a staging site before running it on production.', 'gatherpress-export-import' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Shared post type slugs.', 'gatherpress-export-import' ); ?></strong>
							<?php esc_html_e( 'Events Manager and Event Organiser both use the "event" post type. The plugin distinguishes between them by inspecting meta keys. Import data from only one source plugin at a time.', 'gatherpress-export-import' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Recurring events.', 'gatherpress-export-import' ); ?></strong>
							<?php esc_html_e( 'GatherPress treats each occurrence as a separate event. Recurrence rules are not converted — each exported occurrence becomes its own GatherPress event.', 'gatherpress-export-import' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Taxonomy-based venues (two-pass import).', 'gatherpress-export-import' ); ?></strong>
							<?php esc_html_e( 'Plugins like Event Organiser, MEC, and EventON store venues as taxonomy terms. Import the same WXR file twice: first pass creates venue posts, second pass imports events and links them to venues.', 'gatherpress-export-import' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Duplicate prevention.', 'gatherpress-export-import' ); ?></strong>
							<?php esc_html_e( 'Importing the same file twice may create duplicates (except for the intentional two-pass workflow). Always import into a clean environment or verify existing data first.', 'gatherpress-export-import' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Shortcodes in content.', 'gatherpress-export-import' ); ?></strong>
							<?php esc_html_e( 'Source plugin shortcodes will appear as raw text. Review imported event content and clean up as needed.', 'gatherpress-export-import' ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Timezone handling.', 'gatherpress-export-import' ); ?></strong>
							<?php esc_html_e( 'Some plugins store local time, others UTC. The converter uses the source timezone when available, falling back to your site timezone. Verify datetimes after import.', 'gatherpress-export-import' ); ?>
						</li>
					</ul>
				</details>

				<hr />

				<?php if ( $gatherpress && $wp_importer ) { ?>
					<p>
						<?php esc_html_e( 'Ready to import? Click the button below to proceed to the WordPress Importer file upload screen. The migration plugin will automatically intercept and convert the data during import.', 'gatherpress-export-import' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( $proceed_url ); ?>" class="button button-primary button-hero">
							<?php esc_html_e( 'Proceed to WordPress Importer', 'gatherpress-export-import' ); ?>
						</a>
					</p>
				<?php } else { ?>
					<div class="notice notice-error inline" style="max-width: 700px;">
						<p><strong><?php esc_html_e( 'Cannot proceed.', 'gatherpress-export-import' ); ?></strong> <?php esc_html_e( 'Please install and activate the required plugins listed above before importing.', 'gatherpress-export-import' ); ?></p>
					</div>
				<?php } ?>
			</div>
			<?php
		}
	}
}
