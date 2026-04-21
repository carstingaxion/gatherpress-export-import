<?php
/**
 * Shared datetime helper trait for source adapters.
 *
 * Provides common functionality for saving datetimes to GatherPress
 * and linking venues. Used by all adapters that follow the standard
 * pattern of converting source date/time data to GatherPress format.
 *
 * @package TelexGatherpressMigration
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( 'Telex_GPM_Datetime_Helper' ) ) {
	/**
	 * Trait Telex_GPM_Datetime_Helper.
	 *
	 * Provides reusable methods for saving GatherPress datetimes and
	 * linking venues to events during import. Intended to be used by
	 * classes implementing the Telex_GPM_Source_Adapter interface.
	 *
	 * @since 0.1.0
	 */
	trait Telex_GPM_Datetime_Helper {

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
		protected function save_gatherpress_datetimes( int $post_id, string $datetime_start, string $datetime_end, string $timezone ) {
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
		protected function get_venue_term_slug( string $post_name ): string {
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
		public function link_venue( int $post_id, int $new_venue_id ): void {
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
		protected function get_default_timezone(): string {
			return wp_timezone_string();
		}
	}
}
