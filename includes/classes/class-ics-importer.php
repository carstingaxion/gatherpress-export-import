<?php
/**
 * ICS file importer for GatherPress events.
 *
 * Registers an import tool under Tools that accepts .ics file uploads,
 * parses VEVENT components, and creates GatherPress events as drafts.
 *
 * Supports properties: SUMMARY, DESCRIPTION, X-ALT-DESC (HTML description),
 * DTSTART, DTEND, LOCATION, GEO, URL, and CATEGORIES.
 *
 * @package GatherPressExportImport
 * @since   0.3.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\ICS_Importer' ) ) {
	/**
	 * Class ICS_Importer.
	 *
	 * Provides a card on the Tools page for uploading and importing
	 * ICS calendar files into GatherPress events.
	 *
	 * @since 0.3.0
	 */
	class ICS_Importer {

		/**
		 * Singleton instance.
		 *
		 * @since 0.3.0
		 *
		 * @var ICS_Importer|null
		 */
		private static ?ICS_Importer $instance = null;

		/**
		 * Gets the singleton instance.
		 *
		 * @since 0.3.0
		 *
		 * @return ICS_Importer
		 */
		public static function get_instance(): ICS_Importer {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private constructor — sets up hooks.
		 *
		 * @since 0.3.0
		 */
		private function __construct() {
			add_action( 'admin_init', array( $this, 'handle_upload' ) );
			add_action( 'tool_box', array( $this, 'render_card' ) );
		}

		/**
		 * Prevents cloning.
		 *
		 * @since 0.3.0
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Renders the import card on the Tools page.
		 *
		 * Styled to match the WordPress core "Category and Tags Converter" card.
		 *
		 * @since 0.3.0
		 *
		 * @return void
		 */
		public function render_card(): void {
			if ( ! current_user_can( 'import' ) ) {
				return;
			}

			if ( ! class_exists( '\GatherPress\Core\Event' ) ) {
				return;
			}
			?>
			<div class="card" style="max-width: 520px;">
				<h2 class="title"><?php esc_html_e( 'Import Events from ICS File', 'gatherpress-export-import' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Upload an ICS calendar file to import events into GatherPress. All imported events will be created as drafts for review.', 'gatherpress-export-import' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" id="gpei-ics-form">
					<?php wp_nonce_field( 'gpei_ics_import', 'gpei_ics_nonce' ); ?>
					<div id="gpei-ics-dropzone" style="
						border: 2px dashed #c3c4c7;
						border-radius: 8px;
						padding: 32px 24px;
						text-align: center;
						cursor: pointer;
						transition: border-color 0.2s ease, background-color 0.2s ease;
						background: #f6f7f7;
						margin: 16px 0;
						position: relative;
					">
						<div style="margin-bottom: 8px;">
							<span class="dashicons dashicons-calendar-alt" style="font-size: 36px; width: 36px; height: 36px; color: #8c8f94;"></span>
						</div>
						<p style="margin: 0 0 4px; font-size: 14px; font-weight: 500; color: #1d2327;">
							<?php esc_html_e( 'Drag & drop your .ics file here', 'gatherpress-export-import' ); ?>
						</p>
						<p style="margin: 0; color: #8c8f94; font-size: 13px;">
							<?php esc_html_e( 'or click to browse', 'gatherpress-export-import' ); ?>
						</p>
						<p id="gpei-ics-filename" style="margin: 12px 0 0; font-size: 13px; color: #2271b1; font-weight: 500; display: none;"></p>
						<input type="file" id="gpei_ics_file" name="gpei_ics_file" accept=".ics,text/calendar" style="
							position: absolute;
							top: 0;
							left: 0;
							width: 100%;
							height: 100%;
							opacity: 0;
							cursor: pointer;
						" />
					</div>
					<p class="description" style="margin-top: 0; font-size: 12px; color: #a7aaad;">
						<?php esc_html_e( 'Accepted format: .ics (iCalendar). Exports from Google Calendar, Outlook, Apple Calendar, and Event Organiser are supported.', 'gatherpress-export-import' ); ?>
					</p>
					<p>
						<input type="submit" name="gpei_ics_submit" id="gpei-ics-submit" class="button button-primary" value="<?php esc_attr_e( 'Import Events', 'gatherpress-export-import' ); ?>" disabled />
					</p>
				</form>
			</div>
			<script>
			( function() {
				var dropzone = document.getElementById( 'gpei-ics-dropzone' );
				var fileInput = document.getElementById( 'gpei_ics_file' );
				var filenameEl = document.getElementById( 'gpei-ics-filename' );
				var submitBtn = document.getElementById( 'gpei-ics-submit' );

				if ( ! dropzone || ! fileInput || ! filenameEl || ! submitBtn ) {
					return;
				}

				function showFile( file ) {
					if ( file ) {
						filenameEl.textContent = file.name;
						filenameEl.style.display = 'block';
						submitBtn.disabled = false;
						dropzone.style.borderColor = '#2271b1';
						dropzone.style.backgroundColor = '#f0f6fc';
					}
				}

				fileInput.addEventListener( 'change', function() {
					if ( fileInput.files.length > 0 ) {
						showFile( fileInput.files[0] );
					}
				} );

				dropzone.addEventListener( 'dragover', function( e ) {
					e.preventDefault();
					e.stopPropagation();
					dropzone.style.borderColor = '#2271b1';
					dropzone.style.backgroundColor = '#f0f6fc';
				} );

				dropzone.addEventListener( 'dragleave', function( e ) {
					e.preventDefault();
					e.stopPropagation();
					if ( ! fileInput.files.length ) {
						dropzone.style.borderColor = '#c3c4c7';
						dropzone.style.backgroundColor = '#f6f7f7';
					}
				} );

				dropzone.addEventListener( 'drop', function( e ) {
					e.preventDefault();
					e.stopPropagation();
					if ( e.dataTransfer.files.length > 0 ) {
						fileInput.files = e.dataTransfer.files;
						showFile( e.dataTransfer.files[0] );
					}
				} );
			} )();
			</script>
			<?php
		}

		/**
		 * Handles the ICS file upload and import processing.
		 *
		 * Hooked to `admin_init` to process before headers are sent,
		 * allowing redirects after successful import.
		 *
		 * @since 0.3.0
		 *
		 * @return void
		 */
		public function handle_upload(): void {
			if ( ! isset( $_POST['gpei_ics_submit'] ) ) {
				return;
			}

			if ( ! current_user_can( 'import' ) ) {
				wp_die( esc_html__( 'You do not have permission to import events.', 'gatherpress-export-import' ) );
			}

			check_admin_referer( 'gpei_ics_import', 'gpei_ics_nonce' );

			if ( empty( $_FILES['gpei_ics_file']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['gpei_ics_file']['error'] ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-error is-dismissible"><p>';
						esc_html_e( 'Please select a valid ICS file to upload.', 'gatherpress-export-import' );
						echo '</p></div>';
					}
				);
				return;
			}

			$file_content = file_get_contents( $_FILES['gpei_ics_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( false === $file_content || empty( trim( $file_content ) ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-error is-dismissible"><p>';
						esc_html_e( 'The uploaded file could not be read or is empty.', 'gatherpress-export-import' );
						echo '</p></div>';
					}
				);
				return;
			}

			$events = $this->parse_ics( $file_content );

			if ( empty( $events ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-warning is-dismissible"><p>';
						esc_html_e( 'No events found in the uploaded ICS file.', 'gatherpress-export-import' );
						echo '</p></div>';
					}
				);
				return;
			}

			$created_ids = $this->create_events( $events );

			if ( empty( $created_ids ) ) {
				add_action(
					'admin_notices',
					function () {
						echo '<div class="notice notice-error is-dismissible"><p>';
						esc_html_e( 'Failed to create any events from the ICS file.', 'gatherpress-export-import' );
						echo '</p></div>';
					}
				);
				return;
			}

			if ( 1 === count( $created_ids ) ) {
				wp_safe_redirect( get_edit_post_link( $created_ids[0], 'raw' ) );
				exit;
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'post_type'     => 'gatherpress_event',
						'post_status'   => 'draft',
						'gpei_imported' => count( $created_ids ),
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		/**
		 * Parses an ICS file content string into an array of event data.
		 *
		 * Extracts VEVENT components and their properties: SUMMARY,
		 * DESCRIPTION, X-ALT-DESC (HTML), DTSTART, DTEND, LOCATION,
		 * GEO, URL, and CATEGORIES.
		 *
		 * @since 0.3.0
		 *
		 * @param string $ics_content The raw ICS file content.
		 * @return array<int, array<string, string>> Parsed events.
		 */
		private function parse_ics( string $ics_content ): array {
			// Unfold lines per RFC 5545 (line continuations).
			$ics_content = str_replace( array( "\r\n ", "\r\n\t" ), '', $ics_content );
			$ics_content = str_replace( "\r\n", "\n", $ics_content );
			$ics_content = str_replace( "\r", "\n", $ics_content );

			$events   = array();
			$lines    = explode( "\n", $ics_content );
			$in_event = false;
			$current  = array();

			foreach ( $lines as $line ) {
				$line = trim( $line );

				if ( 'BEGIN:VEVENT' === strtoupper( $line ) ) {
					$in_event = true;
					$current  = array(
						'summary'      => '',
						'description'  => '',
						'html_desc'    => '',
						'dtstart'      => '',
						'dtend'        => '',
						'location'     => '',
						'geo'          => '',
						'url'          => '',
						'categories'   => '',
						'dtstart_params' => '',
						'dtend_params'   => '',
					);
					continue;
				}

				if ( 'END:VEVENT' === strtoupper( $line ) ) {
					$in_event = false;
					if ( ! empty( $current['summary'] ) && ! empty( $current['dtstart'] ) ) {
						$events[] = $current;
					}
					continue;
				}

				if ( ! $in_event ) {
					continue;
				}

				// Parse property;params:value or property:value.
				$colon_pos = strpos( $line, ':' );
				if ( false === $colon_pos ) {
					continue;
				}

				$left  = substr( $line, 0, $colon_pos );
				$value = substr( $line, $colon_pos + 1 );

				// Strip parameters from the property name.
				$semicolon_pos = strpos( $left, ';' );
				$property      = strtoupper( false !== $semicolon_pos ? substr( $left, 0, $semicolon_pos ) : $left );
				$params_str    = false !== $semicolon_pos ? substr( $left, $semicolon_pos + 1 ) : '';

				// Unescape ICS text values per RFC 5545.
				$value = str_replace( '\\n', "\n", $value );
				$value = str_replace( '\\,', ',', $value );
				$value = str_replace( '\\;', ';', $value );
				$value = str_replace( '\\\\', '\\', $value );

				switch ( $property ) {
					case 'SUMMARY':
						$current['summary'] = $value;
						break;
					case 'DESCRIPTION':
						$current['description'] = $value;
						break;
					case 'X-ALT-DESC':
						// HTML description — check for FMTTYPE=text/html in params.
						if ( false !== stripos( $params_str, 'text/html' ) ) {
							$current['html_desc'] = $value;
						}
						break;
					case 'DTSTART':
						$current['dtstart']        = $value;
						$current['dtstart_params'] = $params_str;
						break;
					case 'DTEND':
						$current['dtend']        = $value;
						$current['dtend_params'] = $params_str;
						break;
					case 'LOCATION':
						$current['location'] = $value;
						break;
					case 'GEO':
						// GEO value is "latitude;longitude".
						$current['geo'] = $value;
						break;
					case 'URL':
						$current['url'] = $value;
						break;
					case 'CATEGORIES':
						// CATEGORIES can appear multiple times; accumulate with comma.
						if ( ! empty( $current['categories'] ) ) {
							$current['categories'] .= ',' . $value;
						} else {
							$current['categories'] = $value;
						}
						break;
				}
			}

			return $events;
		}

		/**
		 * Creates GatherPress event posts from parsed ICS event data.
		 *
		 * All events are created as drafts. Datetimes are saved via
		 * GatherPress's Event::save_datetimes() method. CATEGORIES are
		 * assigned as gatherpress_topic terms. LOCATION values are
		 * resolved to gatherpress_venue posts.
		 *
		 * @since 0.3.0
		 *
		 * @param array<int, array<string, string>> $events Parsed events.
		 * @return int[] Array of created post IDs.
		 */
		private function create_events( array $events ): array {
			$created_ids = array();
			$venue_cache = array();

			foreach ( $events as $event_data ) {
				// Prefer HTML description if available, otherwise plain text.
				$description = '';
				if ( ! empty( $event_data['html_desc'] ) ) {
					$description = wp_kses_post( $event_data['html_desc'] );
				} elseif ( ! empty( $event_data['description'] ) ) {
					$description = wp_kses_post( nl2br( $event_data['description'] ) );
				}

				$post_id = wp_insert_post(
					array(
						'post_title'   => sanitize_text_field( $event_data['summary'] ),
						'post_content' => $description,
						'post_type'    => 'gatherpress_event',
						'post_status'  => 'draft',
					),
					true
				);

				if ( is_wp_error( $post_id ) ) {
					continue;
				}

				// Parse and save datetimes.
				$timezone = $this->extract_timezone( $event_data );
				$start    = $this->parse_ics_datetime( $event_data['dtstart'], $timezone );
				$end      = $this->parse_ics_datetime(
					! empty( $event_data['dtend'] ) ? $event_data['dtend'] : $event_data['dtstart'],
					$timezone
				);

				if ( ! empty( $start ) && class_exists( '\GatherPress\Core\Event' ) ) {
					$gp_event = new \GatherPress\Core\Event( $post_id );
					$gp_event->save_datetimes(
						array(
							'datetime_start' => $start,
							'datetime_end'   => $end,
							'timezone'       => $timezone,
						)
					);
				}

				// Save online event link only for pure online events
				// (indicated by having no LOCATION).
				if ( ! empty( $event_data['url'] ) && empty( $event_data['location'] ) ) {
					update_post_meta( $post_id, 'gatherpress_online_event_link', esc_url_raw( $event_data['url'] ) );
				}

				// Assign CATEGORIES as gatherpress_topic terms.
				if ( ! empty( $event_data['categories'] ) && taxonomy_exists( 'gatherpress_topic' ) ) {
					$cat_names = array_map( 'trim', explode( ',', $event_data['categories'] ) );
					$cat_names = array_filter( $cat_names );
					if ( ! empty( $cat_names ) ) {
						$term_ids = array();
						foreach ( $cat_names as $cat_name ) {
							$existing = term_exists( $cat_name, 'gatherpress_topic' );
							if ( $existing ) {
								$term_ids[] = is_array( $existing ) ? intval( $existing['term_id'] ) : intval( $existing );
							} else {
								$inserted = wp_insert_term( $cat_name, 'gatherpress_topic' );
								if ( ! is_wp_error( $inserted ) ) {
									$term_ids[] = intval( $inserted['term_id'] );
								}
							}
						}
						if ( ! empty( $term_ids ) ) {
							wp_set_object_terms( $post_id, $term_ids, 'gatherpress_topic', false );
						}
					}
				}

				// Handle LOCATION — create or reuse a gatherpress_venue post and link.
				if ( ! empty( $event_data['location'] ) ) {
					$location_name = sanitize_text_field( $event_data['location'] );
					$cache_key     = strtolower( $location_name );

					if ( isset( $venue_cache[ $cache_key ] ) ) {
						$venue_post_id = $venue_cache[ $cache_key ];
					} else {
						$venue_post_id = $this->find_or_create_venue(
							$location_name,
							$event_data['geo']
						);
						$venue_cache[ $cache_key ] = $venue_post_id;
					}

					if ( $venue_post_id > 0 ) {
						$this->link_event_to_venue( $post_id, $venue_post_id );
					}
				}

				$created_ids[] = $post_id;
			}

			return $created_ids;
		}

		/**
		 * Finds an existing gatherpress_venue post by title or creates a new one.
		 *
		 * When creating a new venue, saves the GEO coordinates and location
		 * name as gatherpress_venue_information JSON.
		 *
		 * @since 0.3.0
		 *
		 * @param string $location_name The LOCATION property value.
		 * @param string $geo           The GEO property value (latitude;longitude) or empty.
		 * @return int The venue post ID, or 0 on failure.
		 */
		private function find_or_create_venue( string $location_name, string $geo ): int {
			if ( empty( $location_name ) ) {
				return 0;
			}

			// Look for an existing venue by title.
			$existing = get_posts(
				array(
					'post_type'      => 'gatherpress_venue',
					'title'          => $location_name,
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				return $existing[0];
			}

			// Create a new venue post.
			$venue_id = wp_insert_post(
				array(
					'post_title'  => $location_name,
					'post_type'   => 'gatherpress_venue',
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $venue_id ) ) {
				return 0;
			}

			// Parse GEO coordinates.
			$latitude  = '';
			$longitude = '';
			if ( ! empty( $geo ) ) {
				$geo_parts = explode( ';', $geo );
				if ( 2 === count( $geo_parts ) ) {
					$latitude  = trim( $geo_parts[0] );
					$longitude = trim( $geo_parts[1] );
				}
			}

			// Save venue information JSON.
			$venue_info = wp_json_encode(
				array(
					'fullAddress' => $location_name,
					'phoneNumber' => '',
					'website'     => '',
					'latitude'    => $latitude,
					'longitude'   => $longitude,
				)
			);

			if ( false !== $venue_info ) {
				update_post_meta( $venue_id, 'gatherpress_venue_information', $venue_info );
			}

			return $venue_id;
		}

		/**
		 * Links an event post to a venue post via the _gatherpress_venue shadow taxonomy.
		 *
		 * @since 0.3.0
		 *
		 * @param int $event_id The event post ID.
		 * @param int $venue_id The venue post ID.
		 * @return void
		 */
		private function link_event_to_venue( int $event_id, int $venue_id ): void {
			if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
				return;
			}

			$venue_post = get_post( $venue_id );
			if ( ! $venue_post || 'gatherpress_venue' !== $venue_post->post_type ) {
				return;
			}

			$term_slug = sprintf( '_%s', $venue_post->post_name );
			$term      = get_term_by( 'slug', $term_slug, '_gatherpress_venue' );

			if ( $term instanceof \WP_Term ) {
				wp_set_object_terms( $event_id, array( $term->term_id ), '_gatherpress_venue', false );
			}
		}

		/**
		 * Extracts the timezone from ICS event data.
		 *
		 * Checks for TZID parameter on DTSTART, then falls back to
		 * detecting UTC ('Z' suffix), then site default timezone.
		 *
		 * @since 0.3.0
		 *
		 * @param array<string, string> $event_data Parsed event data.
		 * @return string PHP timezone string.
		 */
		private function extract_timezone( array $event_data ): string {
			// Check for TZID parameter on DTSTART.
			$params = isset( $event_data['dtstart_params'] ) ? $event_data['dtstart_params'] : '';
			if ( ! empty( $params ) ) {
				$parts = explode( ';', $params );
				foreach ( $parts as $part ) {
					$kv = explode( '=', $part, 2 );
					if ( 2 === count( $kv ) && 'TZID' === strtoupper( trim( $kv[0] ) ) ) {
						$tz_string = trim( $kv[1] );
						$tz_string = trim( $tz_string, '"' );
						if ( ! empty( $tz_string ) ) {
							return $tz_string;
						}
					}
				}
			}

			// Check for UTC indicator.
			$dtstart = isset( $event_data['dtstart'] ) ? $event_data['dtstart'] : '';
			if ( ! empty( $dtstart ) && 'Z' === strtoupper( substr( $dtstart, -1 ) ) ) {
				return 'UTC';
			}

			return wp_timezone_string();
		}

		/**
		 * Parses an ICS datetime string into 'Y-m-d H:i:s' format.
		 *
		 * Supports formats:
		 * - `20250915T090000Z` (UTC)
		 * - `20250915T090000` (local or with TZID)
		 * - `20250915` (all-day)
		 *
		 * @since 0.3.0
		 *
		 * @param string $ics_datetime The ICS datetime value.
		 * @param string $timezone     The timezone context.
		 * @return string Datetime in 'Y-m-d H:i:s' format, or empty on failure.
		 */
		private function parse_ics_datetime( string $ics_datetime, string $timezone ): string {
			// Remove trailing Z (timezone already determined).
			$dt = rtrim( $ics_datetime, 'Zz' );

			// Remove non-digit characters except T.
			$dt = preg_replace( '/[^0-9T]/', '', $dt );

			if ( empty( $dt ) ) {
				return '';
			}

			// All-day format: YYYYMMDD.
			if ( 8 === strlen( $dt ) && false === strpos( $dt, 'T' ) ) {
				$year  = substr( $dt, 0, 4 );
				$month = substr( $dt, 4, 2 );
				$day   = substr( $dt, 6, 2 );
				return sprintf( '%s-%s-%s 00:00:00', $year, $month, $day );
			}

			// Full datetime: YYYYMMDDTHHMMSS.
			$parts = explode( 'T', $dt );
			if ( 2 !== count( $parts ) ) {
				return '';
			}

			$date_part = $parts[0];
			$time_part = $parts[1];

			if ( strlen( $date_part ) < 8 ) {
				return '';
			}

			$year  = substr( $date_part, 0, 4 );
			$month = substr( $date_part, 4, 2 );
			$day   = substr( $date_part, 6, 2 );

			// Pad time part if needed.
			$time_part = str_pad( $time_part, 6, '0', STR_PAD_RIGHT );
			$hour      = substr( $time_part, 0, 2 );
			$minute    = substr( $time_part, 2, 2 );
			$second    = substr( $time_part, 4, 2 );

			return sprintf( '%s-%s-%s %s:%s:%s', $year, $month, $day, $hour, $minute, $second );
		}
	}
}
