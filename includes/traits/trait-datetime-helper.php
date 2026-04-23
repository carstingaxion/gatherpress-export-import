<?php
/**
 * Shared datetime helper trait for source adapters.
 *
 * Provides common functionality for saving datetimes to GatherPress
 * and linking venues. Used by all adapters that follow the standard
 * pattern of converting source date/time data to GatherPress format.
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( __NAMESPACE__ . '\Datetime_Helper' ) ) {
	/**
	 * Trait Datetime_Helper.
	 *
	 * Provides reusable methods for saving GatherPress datetimes and
	 * linking venues to events during import. Intended to be used by
	 * classes implementing the Source_Adapter interface.
	 *
	 * @since 0.1.0
	 */
	trait Datetime_Helper {

		/**
		 * Saves datetimes into GatherPress via its Event class.
		 *
		 * Creates a new GatherPress Event instance and calls `save_datetimes()`
		 * with the provided start/end times and timezone.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $post_id        The event post ID.
		 * @param string $datetime_start Start datetime in 'Y-m-d H:i:s' format.
		 * @param string $datetime_end   End datetime in 'Y-m-d H:i:s' format.
		 * @param string $timezone       Timezone string (e.g., 'America/New_York').
		 * @return bool|mixed The result of Event::save_datetimes(), or false on failure.
		 */
		final protected function save_gatherpress_datetimes( int $post_id, string $datetime_start, string $datetime_end, string $timezone ) {
			if ( ! class_exists( '\GatherPress\Core\Event' ) ) {
				return false;
			}

			$event = new \GatherPress\Core\Event( $post_id );

			if ( ! method_exists( $event, 'save_datetimes' ) ) {
				return false;
			}

			$params = array(
				'datetime_start' => $datetime_start,
				'datetime_end'   => $datetime_end,
				'timezone'       => $timezone,
			);

			return $event->save_datetimes( $params );
		}

		/**
		 * Generates a venue term slug for the _gatherpress_venue taxonomy.
		 *
		 * Replicates GatherPress's own `get_venue_term_slug()` method, which
		 * generates the shadow taxonomy term slug by prefixing the venue post
		 * slug with an underscore.
		 *
		 * @since 0.1.0
		 *
		 * @param string $post_name Post name (slug) of the venue post.
		 * @return string The generated term slug (e.g., '_my-venue-slug').
		 */
		final protected function get_venue_term_slug( string $post_name ): string {
			return sprintf( '_%s', $post_name );
		}

		/**
		 * Links a venue to an event using the _gatherpress_venue shadow taxonomy.
		 *
		 * GatherPress creates `gatherpress_venue` posts which are automatically
		 * shadowed into a hidden `_gatherpress_venue` taxonomy. Events reference
		 * venues by being assigned the corresponding shadow taxonomy term. This
		 * method generates the expected term slug using the same convention as
		 * GatherPress (underscore-prefixed post slug) and assigns it to the
		 * event post via `wp_set_object_terms()`.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id      The event post ID.
		 * @param int $new_venue_id The venue post ID to link.
		 * @return void
		 */
		final public function link_venue( int $post_id, int $new_venue_id ): void {
			$venue_post = get_post( $new_venue_id );

			if ( ! $venue_post || 'gatherpress_venue' !== $venue_post->post_type ) {
				return;
			}

			if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
				return;
			}

			// Generate the expected term slug using the same convention as
			// GatherPress: underscore-prefixed venue post slug.
			$term_slug = $this->get_venue_term_slug( $venue_post->post_name );
			$term      = get_term_by( 'slug', $term_slug, '_gatherpress_venue' );

			if ( $term && ! is_wp_error( $term ) ) {
				wp_set_object_terms( $post_id, array( $term->term_id ), '_gatherpress_venue', false );
			}
		}

		/**
		 * Gets the default timezone from WordPress settings.
		 *
		 * Uses `wp_timezone_string()` to retrieve the timezone configured
		 * in Settings > General.
		 *
		 * @since 0.1.0
		 *
		 * @return string The WordPress site timezone string.
		 */
		final protected function get_default_timezone(): string {
			return wp_timezone_string();
		}

		/**
		 * Saves venue information meta on a gatherpress_venue post.
		 *
		 * Builds the JSON structure expected by GatherPress for the
		 * `gatherpress_venue_information` post meta key and persists it.
		 * Only updates the meta if the post exists and is a `gatherpress_venue`.
		 *
		 * All parameters are optional; omitted or empty values are stored
		 * as empty strings in the JSON, matching GatherPress's own behaviour.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $venue_post_id The gatherpress_venue post ID.
		 * @param string $full_address  Full address string (e.g., "WUK, holzplatz 7a, Halle (Saale)").
		 * @param string $phone_number  Phone number. Default empty.
		 * @param string $website       Website URL. Default empty.
		 * @param string $latitude      Latitude coordinate. Default empty.
		 * @param string $longitude     Longitude coordinate. Default empty.
		 * @return bool True on success, false on failure.
		 */
		final protected function save_venue_information(
			int $venue_post_id,
			string $full_address = '',
			string $phone_number = '',
			string $website = '',
			string $latitude = '',
			string $longitude = ''
		): bool {
			$venue_post = get_post( $venue_post_id );

			if ( ! $venue_post || 'gatherpress_venue' !== $venue_post->post_type ) {
				return false;
			}

			$venue_information = wp_json_encode(
				array(
					'fullAddress'  => $full_address,
					'phoneNumber'  => $phone_number,
					'website'      => $website,
					'latitude'     => $latitude,
					'longitude'    => $longitude,
				)
			);

			if ( false === $venue_information ) {
				return false;
			}

			return (bool) update_post_meta( $venue_post_id, 'gatherpress_venue_information', $venue_information );
		}

		/**
		 * Builds a full address string from individual address components.
		 *
		 * Concatenates non-empty components with a comma separator.
		 * Useful for adapters that store address parts in separate meta fields
		 * (e.g., TEC's `_VenueAddress`, `_VenueCity`, `_VenueState`, `_VenueZip`,
		 * `_VenueCountry`).
		 *
		 * @since 0.1.0
		 *
		 * @param string ...$parts Variable number of address component strings.
		 * @return string Comma-separated address string, or empty if all parts are empty.
		 */
		final protected function build_full_address( string ...$parts ): string {
			$non_empty = array_filter(
				$parts,
				function ( string $part ): bool {
					return '' !== trim( $part );
				}
			);

			return implode( ', ', array_map( 'trim', $non_empty ) );
		}
	}
}
