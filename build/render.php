<?php
/**
 * Renders the GatherPress Event Migration Guide block on the front end.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package TelexGatherpressMigration
 */

?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<div class="gp-migration-guide">

		<div class="gp-migration-guide__header">
			<span class="gp-migration-guide__icon">📦</span>
			<h2 class="gp-migration-guide__title"><?php esc_html_e( 'GatherPress Event Migration Guide', 'telex-gatherpress-migration' ); ?></h2>
			<p class="gp-migration-guide__subtitle"><?php esc_html_e( 'Import event data from third-party plugins using the standard WordPress Importer.', 'telex-gatherpress-migration' ); ?></p>
		</div>

		<div class="gp-migration-guide__section">
			<h3 class="gp-migration-guide__section-title">
				<span class="gp-migration-guide__section-icon">⚙️</span>
				<?php esc_html_e( 'How It Works', 'telex-gatherpress-migration' ); ?>
			</h3>
			<p class="gp-migration-guide__text">
				<?php esc_html_e( 'This plugin hooks into the standard WordPress XML import process. When you upload a WXR export file from your third-party event plugin, the magic happens automatically:', 'telex-gatherpress-migration' ); ?>
			</p>
			<ol class="gp-migration-guide__steps">
				<li><strong><?php esc_html_e( 'Post type rewriting', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'The wp_import_post_data_raw filter intercepts imported posts at priority 5 and rewrites third-party post types (tribe_events, event, mec-events, ai1ec_event, ajde_events) to gatherpress_event. Venue post types (tribe_venue, location) are rewritten to gatherpress_venue.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Meta key interception', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Third-party date/time meta keys (_EventStartDate, _event_start, mec_start_date, evcal_srow, _eventorganiser_schedule_start_datetime, etc.) are intercepted via the add_post_metadata filter and stashed in a transient instead of being written to wp_postmeta.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Pseudopostmeta conversion', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'After all meta keys are collected, composite callbacks convert the source date/time formats into GatherPress datetime arrays and save them to the gp_event_extended table via Event::save_datetimes().', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Venue linking', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Venue references (_EventVenueID) are resolved using the WordPress Importer old-to-new ID mapping, then linked to events via GatherPress native methods. Taxonomy-based venues (e.g., Event Organiser event-venue terms) are handled by the standard WXR taxonomy import.', 'telex-gatherpress-migration' ); ?></li>
			</ol>
		</div>

		<div class="gp-migration-guide__section">
			<h3 class="gp-migration-guide__section-title">
				<span class="gp-migration-guide__section-icon">🔌</span>
				<?php esc_html_e( 'Supported Source Plugins', 'telex-gatherpress-migration' ); ?>
			</h3>
			<div class="gp-migration-guide__table-wrap">
				<table class="gp-migration-guide__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source Plugin', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'Event CPT', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'Venue CPT', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'Date Format', 'telex-gatherpress-migration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'The Events Calendar (StellarWP)', 'telex-gatherpress-migration' ); ?></td>
							<td><code>tribe_events</code></td>
							<td><code>tribe_venue</code></td>
							<td><?php esc_html_e( 'Y-m-d H:i:s local time + timezone string', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Events Manager', 'telex-gatherpress-migration' ); ?></td>
							<td><code>event</code></td>
							<td><code>location</code></td>
							<td><?php esc_html_e( 'Y-m-d H:i:s + timezone string', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Modern Events Calendar', 'telex-gatherpress-migration' ); ?></td>
							<td><code>mec-events</code></td>
							<td><?php esc_html_e( 'Taxonomy terms', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Y-m-d date + separate h/m/ampm fields', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'All-in-One Event Calendar', 'telex-gatherpress-migration' ); ?></td>
							<td><code>ai1ec_event</code></td>
							<td><?php esc_html_e( 'N/A (custom table)', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Custom table (manual mapping needed)', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'EventON', 'telex-gatherpress-migration' ); ?></td>
							<td><code>ajde_events</code></td>
							<td><?php esc_html_e( 'Taxonomy/meta', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Unix timestamps (evcal_srow / evcal_erow)', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Event Organiser (Stephen Harris)', 'telex-gatherpress-migration' ); ?></td>
							<td><code>event</code></td>
							<td><?php esc_html_e( 'Taxonomy (event-venue)', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Y-m-d H:i:s local time (_eventorganiser_schedule_*)', 'telex-gatherpress-migration' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="gp-migration-guide__tip">
				<?php esc_html_e( '💡 You can add support for additional plugins using the telex_gpm_event_post_type_map and telex_gpm_venue_post_type_map filters.', 'telex-gatherpress-migration' ); ?>
			</div>
		</div>

		<div class="gp-migration-guide__section">
			<h3 class="gp-migration-guide__section-title">
				<span class="gp-migration-guide__section-icon">✅</span>
				<?php esc_html_e( 'Migration Steps', 'telex-gatherpress-migration' ); ?>
			</h3>
			<ol class="gp-migration-guide__steps">
				<li><strong><?php esc_html_e( 'Backup your database', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Always create a full database backup before importing. Run on staging first.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Install prerequisites', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Ensure GatherPress, the WordPress Importer plugin, and this migration plugin are all active.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Export from source plugin', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Go to Tools > Export in your source site. Export the event and venue post types. Download the WXR file.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Import via WordPress Importer', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Go to Tools > Import > WordPress. Upload the WXR file. The migration plugin will automatically rewrite post types and convert date/time data.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Flush permalinks', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Visit Settings > Permalinks and click Save to regenerate rewrite rules.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Verify imported data', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Check event counts, spot-check dates/times/timezones, verify venue links, and confirm featured images.', 'telex-gatherpress-migration' ); ?></li>
			</ol>
		</div>

		<div class="gp-migration-guide__section">
			<h3 class="gp-migration-guide__section-title">
				<span class="gp-migration-guide__section-icon">🗂️</span>
				<?php esc_html_e( 'Data Mapping', 'telex-gatherpress-migration' ); ?>
			</h3>
			<div class="gp-migration-guide__table-wrap">
				<table class="gp-migration-guide__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source Data', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'GatherPress Target', 'telex-gatherpress-migration' ); ?></th>
							<th><?php esc_html_e( 'Handling', 'telex-gatherpress-migration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Event title and content', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'gatherpress_event post', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Automatic (post type rewrite)', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Start/end date, time, timezone', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'gp_event_extended table', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Pseudopostmeta composite callback', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Venue / location', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'gatherpress_venue post + relationship', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Post type rewrite + ID mapping callback', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Categories / tags', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'gatherpress_topic taxonomy', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'May need manual re-assignment', 'telex-gatherpress-migration' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Featured image', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Post thumbnail', 'telex-gatherpress-migration' ); ?></td>
							<td><?php esc_html_e( 'Automatic (standard WXR)', 'telex-gatherpress-migration' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="gp-migration-guide__section">
			<h3 class="gp-migration-guide__section-title">
				<span class="gp-migration-guide__section-icon">⚠️</span>
				<?php esc_html_e( 'Common Pitfalls', 'telex-gatherpress-migration' ); ?>
			</h3>
			<ul class="gp-migration-guide__list">
				<li><strong><?php esc_html_e( 'Timezone mismatches', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Some plugins store local time, others UTC. The converter uses the source timezone when available, falling back to your site timezone.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Recurring events', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'GatherPress treats each occurrence as a separate event. Recurring rules from source plugins are not converted; each exported occurrence imports as its own event.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Venue deduplication', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'If you import the same file twice, duplicate venues may be created. Always import into a clean environment or check for existing venues.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Shortcodes in content', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Source plugin shortcodes will appear as raw text after migration. Review event content and replace or remove them.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Import order', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'The WordPress Importer typically handles all posts in the WXR file in order. Venues should appear before events in the export for reliable linking.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Permalink conflicts', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Deactivate the source event plugin before flushing permalinks to avoid slug conflicts.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Shared post type slugs', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Events Manager and Event Organiser both use the "event" post type. The plugin distinguishes between them by inspecting meta keys during conversion. Ensure only one source plugin\'s data is present per import file.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong><?php esc_html_e( 'Taxonomy-based venues', 'telex-gatherpress-migration' ); ?></strong> &mdash; <?php esc_html_e( 'Event Organiser stores venues as taxonomy terms (event-venue) rather than posts. These terms are imported normally by the WXR importer but are not automatically converted to GatherPress venue posts.', 'telex-gatherpress-migration' ); ?></li>
			</ul>
		</div>

		<div class="gp-migration-guide__section">
			<h3 class="gp-migration-guide__section-title">
				<span class="gp-migration-guide__section-icon">🔧</span>
				<?php esc_html_e( 'Extending for Custom Plugins', 'telex-gatherpress-migration' ); ?>
			</h3>
			<p class="gp-migration-guide__text">
				<?php esc_html_e( 'To add support for a custom event plugin, use these filters:', 'telex-gatherpress-migration' ); ?>
			</p>
			<ul class="gp-migration-guide__list">
				<li><strong>telex_gpm_event_post_type_map</strong> &mdash; <?php esc_html_e( 'Add your custom event post type to the mapping array.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong>telex_gpm_venue_post_type_map</strong> &mdash; <?php esc_html_e( 'Add your custom venue post type to the mapping array.', 'telex-gatherpress-migration' ); ?></li>
				<li><strong>gatherpress_pseudopostmetas</strong> &mdash; <?php esc_html_e( 'Register additional pseudopostmeta keys with import callbacks for your custom meta fields.', 'telex-gatherpress-migration' ); ?></li>
			</ul>
		</div>

		<div class="gp-migration-guide__section">
			<h3 class="gp-migration-guide__section-title">
				<span class="gp-migration-guide__section-icon">📚</span>
				<?php esc_html_e( 'Technical References', 'telex-gatherpress-migration' ); ?>
			</h3>
			<ul class="gp-migration-guide__list">
				<li><a href="https://gatherpress.org/documentation/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GatherPress Official Documentation', 'telex-gatherpress-migration' ); ?></a></li>
				<li><a href="https://github.com/GatherPress/gatherpress" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GatherPress GitHub Repository', 'telex-gatherpress-migration' ); ?></a></li>
				<li><a href="https://developer.wordpress.org/reference/hooks/add_meta_type_metadata/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'add_{meta_type}_metadata filter reference', 'telex-gatherpress-migration' ); ?></a></li>
				<li><a href="https://github.com/WordPress/wordpress-importer" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WordPress Importer Plugin', 'telex-gatherpress-migration' ); ?></a></li>
			</ul>
		</div>

		<div class="gp-migration-guide__footer">
			<p class="gp-migration-guide__footer-text">
				<?php
				printf(
					/* translators: %s: GatherPress community link */
					esc_html__( 'Need help? Join the %s for support and discussion.', 'telex-gatherpress-migration' ),
					'<a class="gp-migration-guide__footer-link" href="https://gatherpress.org/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'GatherPress community', 'telex-gatherpress-migration' ) . '</a>'
				);
				?>
			</p>
		</div>

	</div>
</div>
