<?php
/**
 * Base test case for integration tests.
 *
 * Extends WP_UnitTestCase with helper methods for creating events,
 * venues, and testing the import pipeline.
 *
 * @package GatherPressExportImport\Tests\Integration
 * @since   0.1.0
 */

namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\Migration;
use GatherPressExportImport\Event_Organiser_Adapter;

/**
 * Class TestCase.
 *
 * Base test case providing shared helpers for integration tests.
 *
 * @since 0.1.0
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * Gets the main migration singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Migration The migration instance.
	 */
	protected function get_migration_instance(): Migration {
		return Migration::get_instance();
	}

	/**
	 * Gets the Event Organiser adapter from the registered adapters.
	 *
	 * @since 0.1.0
	 *
	 * @return Event_Organiser_Adapter|null The EO adapter or null.
	 */
	protected function get_eo_adapter(): ?Event_Organiser_Adapter {
		$migration = $this->get_migration_instance();
		foreach ( $migration->get_adapters() as $adapter ) {
			if ( $adapter instanceof Event_Organiser_Adapter ) {
				return $adapter;
			}
		}
		return null;
	}

	/**
	 * Creates a gatherpress_venue post.
	 *
	 * @since 0.1.0
	 *
	 * @param string $title The venue title.
	 * @param string $slug  Optional. The venue slug.
	 * @return int The venue post ID.
	 */
	protected function create_venue( string $title, string $slug = '' ): int {
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $title );
		}

		return $this->factory()->post->create(
			array(
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_type'   => 'gatherpress_venue',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Creates a gatherpress_event post.
	 *
	 * @since 0.1.0
	 *
	 * @param string $title The event title.
	 * @return int The event post ID.
	 */
	protected function create_event( string $title ): int {
		return $this->factory()->post->create(
			array(
				'post_title'  => $title,
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Checks whether GatherPress is active and its Event class is available.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if GatherPress is available.
	 */
	protected function is_gatherpress_active(): bool {
		return class_exists( '\GatherPress\Core\Event' );
	}
}
