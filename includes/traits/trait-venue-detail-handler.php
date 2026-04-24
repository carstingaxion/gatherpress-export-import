<?php
/**
 * Shared venue detail handler trait for source adapters.
 *
 * Provides reusable logic for intercepting venue detail post meta keys
 * during import (e.g., address, city, state, phone, website) and
 * assembling them into GatherPress's `gatherpress_venue_information`
 * JSON post meta format.
 *
 * Classes using this trait MUST use the `Datetime_Helper` trait (for
 * `save_venue_information()` and `build_full_address()`).
 *
 * @package GatherPressExportImport
 * @since   0.1.0
 */

namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! trait_exists( __NAMESPACE__ . '\Venue_Detail_Handler' ) ) {
	/**
	 * Trait Venue_Detail_Handler.
	 *
	 * Encapsulates the logic for stashing venue detail meta during import,
	 * then processing it at `import_end` to build and save the
	 * `gatherpress_venue_information` post meta on `gatherpress_venue` posts.
	 *
	 * Adapters using this trait must implement `get_venue_detail_meta_map()`
	 * to declare the mapping between source meta keys and address components.
	 *
	 * @since 0.1.0
	 */
	trait Venue_Detail_Handler {

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
					'fullAddress' => $full_address,
					'phoneNumber' => $phone_number,
					'website'     => $website,
					'latitude'    => $latitude,
					'longitude'   => $longitude,
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

		/**
		 * Whether the venue detail handler hooks have been registered.
		 *
		 * Ensures idempotency — calling `setup_venue_detail_hooks()` multiple
		 * times will not register duplicate hooks.
		 *
		 * @since 0.1.0
		 *
		 * @var bool
		 */
		private bool $vdh_hooks_registered = false;

		/**
		 * Gets the mapping of source venue meta keys to address components.
		 *
		 * Returns an associative array where keys are the source plugin's
		 * post meta keys (e.g., `_VenueAddress`, `_location_address`) and
		 * values are the component type used when building the GatherPress
		 * venue information JSON.
		 *
		 * Supported component types:
		 * - `'address'`   — Street address line.
		 * - `'city'`      — City name.
		 * - `'state'`     — State or province.
		 * - `'zip'`       — Postal/ZIP code.
		 * - `'country'`   — Country name or code.
		 * - `'phone'`     — Phone number (maps to `phoneNumber`).
		 * - `'website'`   — Website URL (maps to `website`).
		 * - `'latitude'`  — Latitude coordinate.
		 * - `'longitude'` — Longitude coordinate.
		 *
		 * Address components (`address`, `city`, `state`, `zip`, `country`)
		 * are concatenated (comma-separated) into the `fullAddress` field.
		 *
		 * @since 0.1.0
		 *
		 * @return array<string, string> Map of source_meta_key => component_type.
		 */
		abstract protected function get_venue_detail_meta_map(): array;

		/**
		 * Gets the list of source venue detail meta keys.
		 *
		 * Convenience method that returns just the keys from the venue
		 * detail meta map, suitable for merging into `get_stash_meta_keys()`.
		 *
		 * @since 0.1.0
		 *
		 * @return string[] Array of source meta key strings.
		 */
		final protected function get_venue_detail_meta_keys(): array {
			return array_keys( $this->get_venue_detail_meta_map() );
		}

		/**
		 * Sets up the import hooks for venue detail meta interception.
		 *
		 * Registers hooks to:
		 * - Intercept venue detail meta keys on `gatherpress_venue` posts
		 *   and stash them in a per-post transient.
		 * - Process all stashed venue meta at `import_end`, assembling
		 *   the address and saving `gatherpress_venue_information`.
		 *
		 * Should be called from the adapter's `setup_import_hooks()` method.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		final protected function setup_venue_detail_hooks(): void {
			if ( $this->vdh_hooks_registered ) {
				return;
			}

			$this->vdh_hooks_registered = true;

			// Intercept venue detail meta keys on gatherpress_venue posts.
			// Priority 4 runs before the main migration class's stash at priority 5.
			add_filter( 'add_post_metadata', array( $this, 'vdh_stash_venue_meta_on_import' ), 4, 4 );

			// Process all stashed venue meta after the import completes.
			// Priority 5 runs before the main migration class's processing at priority 10.
			add_action( 'import_end', array( $this, 'vdh_process_stashed_venue_meta' ), 5 );
		}

		/**
		 * Intercepts venue detail meta and stashes it in a transient.
		 *
		 * Hooked to `add_post_metadata` at priority 4. Only intercepts
		 * meta keys declared in `get_venue_detail_meta_map()` on
		 * `gatherpress_venue` posts.
		 *
		 * @since 0.1.0
		 *
		 * @param mixed  $check      Current check value (null to proceed).
		 * @param int    $object_id  Post ID receiving the meta.
		 * @param string $meta_key   The meta key being added.
		 * @param mixed  $meta_value The meta value being added.
		 * @return mixed True to short-circuit meta saving, or original $check.
		 */
		final public function vdh_stash_venue_meta_on_import( $check, int $object_id, string $meta_key, $meta_value ) {
			$meta_map = $this->get_venue_detail_meta_map();

			if ( ! isset( $meta_map[ $meta_key ] ) ) {
				return $check;
			}

			$post_type = get_post_type( $object_id );

			if ( 'gatherpress_venue' !== $post_type ) {
				return $check;
			}

			$transient_key = 'gpei_venue_meta_stash_' . $object_id;
			$stash         = get_transient( $transient_key );
			if ( ! is_array( $stash ) ) {
				$stash = array();
			}
			$stash[ $meta_key ] = $meta_value;
			set_transient( $transient_key, $stash, HOUR_IN_SECONDS );

			// Track this venue post ID for processing at import_end.
			$pending = get_transient( 'gpei_pending_venue_ids' );
			if ( ! is_array( $pending ) ) {
				$pending = array();
			}
			if ( ! in_array( $object_id, $pending, true ) ) {
				$pending[] = $object_id;
				set_transient( 'gpei_pending_venue_ids', $pending, HOUR_IN_SECONDS );
			}

			// Return true to prevent saving to wp_postmeta.
			return true;
		}

		/**
		 * Processes stashed venue meta and saves GatherPress venue information.
		 *
		 * Hooked to `import_end` at priority 5. Iterates through all pending
		 * venue post IDs, reads the stashed meta, maps each key to its
		 * component type, assembles the full address, and saves the
		 * `gatherpress_venue_information` post meta via the shared
		 * `save_venue_information()` method from `Datetime_Helper`.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		final public function vdh_process_stashed_venue_meta(): void {
			$pending = get_transient( 'gpei_pending_venue_ids' );

			if ( ! is_array( $pending ) || empty( $pending ) ) {
				return;
			}

			$meta_map = $this->get_venue_detail_meta_map();

			foreach ( $pending as $venue_post_id ) {
				$venue_post_id = (int) $venue_post_id;
				$transient_key = 'gpei_venue_meta_stash_' . $venue_post_id;
				$stash         = get_transient( $transient_key );

				if ( ! is_array( $stash ) || empty( $stash ) ) {
					continue;
				}

				// Collect components by type.
				$components = array(
					'address'   => '',
					'city'      => '',
					'state'     => '',
					'zip'       => '',
					'country'   => '',
					'phone'     => '',
					'website'   => '',
					'latitude'  => '',
					'longitude' => '',
				);

				foreach ( $stash as $key => $value ) {
					if ( isset( $meta_map[ $key ] ) ) {
						$component_type = $meta_map[ $key ];
						if ( isset( $components[ $component_type ] ) ) {
							$components[ $component_type ] = (string) $value;
						}
					}
				}

				$full_address = $this->build_full_address(
					$components['address'],
					$components['city'],
					$components['state'],
					$components['zip'],
					$components['country']
				);

				$this->save_venue_information(
					$venue_post_id,
					$full_address,
					$components['phone'],
					$components['website'],
					$components['latitude'],
					$components['longitude']
				);

				delete_transient( $transient_key );
			}

			delete_transient( 'gpei_pending_venue_ids' );
		}
	}
}
