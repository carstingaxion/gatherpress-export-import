<?php
/**
 * Unit tests for the Venue_Detail_Handler trait.
 *
 * Tests address building, meta key extraction, venue information
 * saving, and meta stashing in isolation.
 *
 * @package GatherPressExportImport\Tests\Unit
 * @since   0.1.0
 */

use GatherPressExportImport\Source_Adapter;
use GatherPressExportImport\Hookable_Adapter;
use GatherPressExportImport\Datetime_Helper;
use GatherPressExportImport\Venue_Detail_Handler;

/**
 * Concrete class using the Venue_Detail_Handler trait for testing.
 *
 * @since 0.1.0
 */
class VenueDetailHandlerTestClass implements Hookable_Adapter, Source_Adapter {
	use Datetime_Helper;
	use Venue_Detail_Handler;

	/** @return string */
	public function get_name(): string {
		return 'Test Venue Detail Adapter';
	}

	/** @return array<string, string> */
	public function get_event_post_type_map(): array {
		return array( 'test_event' => 'gatherpress_event' );
	}

	/** @return array<string, string> */
	public function get_venue_post_type_map(): array {
		return array( 'test_venue' => 'gatherpress_venue' );
	}

	/** @return string[] */
	public function get_stash_meta_keys(): array {
		return array_merge(
			array( '_test_start', '_test_end' ),
			$this->get_venue_detail_meta_keys()
		);
	}

	/** @return array */
	public function get_pseudopostmetas(): array {
		return array();
	}

	/** @return bool */
	public function can_handle( array $stash ): bool {
		return isset( $stash['_test_start'] );
	}

	/** @return void */
	public function convert_datetimes( int $post_id, array $stash ): void {}

	/** @return string|null */
	public function get_venue_meta_key(): ?string {
		return null;
	}

	/** @return array<string, string> */
	public function get_taxonomy_map(): array {
		return array();
	}

	/** @return void */
	public function setup_import_hooks(): void {
		$this->setup_venue_detail_hooks();
	}

	/**
	 * Expose protected get_venue_detail_meta_map for testing.
	 *
	 * @return array<string, string>
	 */
	protected function get_venue_detail_meta_map(): array {
		return array(
			'_test_address' => 'address',
			'_test_city'    => 'city',
			'_test_state'   => 'state',
			'_test_zip'     => 'zip',
			'_test_country' => 'country',
			'_test_phone'   => 'phone',
			'_test_website' => 'website',
		);
	}

	/**
	 * Public proxy for the protected build_full_address() method.
	 *
	 * @param string ...$parts Address components.
	 * @return string Built address string.
	 */
	public function public_build_full_address( string ...$parts ): string {
		return $this->build_full_address( ...$parts );
	}

	/**
	 * Public proxy for the protected save_venue_information() method.
	 *
	 * @param int    $venue_post_id The venue post ID.
	 * @param string $full_address  Full address string.
	 * @param string $phone_number  Phone number.
	 * @param string $website       Website URL.
	 * @param string $latitude      Latitude.
	 * @param string $longitude     Longitude.
	 * @return bool
	 */
	public function public_save_venue_information(
		int $venue_post_id,
		string $full_address = '',
		string $phone_number = '',
		string $website = '',
		string $latitude = '',
		string $longitude = ''
	): bool {
		return $this->save_venue_information( $venue_post_id, $full_address, $phone_number, $website, $latitude, $longitude );
	}

	/**
	 * Public proxy for the protected get_venue_detail_meta_keys() method.
	 *
	 * @return string[]
	 */
	public function public_get_venue_detail_meta_keys(): array {
		return $this->get_venue_detail_meta_keys();
	}
}

/**
 * Class VenueDetailHandlerTraitTest.
 *
 * @since 0.1.0
 */
class VenueDetailHandlerTraitTest extends \WP_UnitTestCase {

	/**
	 * The handler instance under test.
	 *
	 * @since 0.1.0
	 *
	 * @var VenueDetailHandlerTestClass
	 */
	private VenueDetailHandlerTestClass $handler;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->handler = new VenueDetailHandlerTestClass();
	}

	/**
	 * Tests that build_full_address() concatenates all parts with commas.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_build_full_address_all_parts(): void {
		$result = $this->handler->public_build_full_address(
			'123 Main Street',
			'Portland',
			'OR',
			'97201',
			'United States'
		);

		$this->assertSame( '123 Main Street, Portland, OR, 97201, United States', $result );
	}

	/**
	 * Tests that build_full_address() skips empty parts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_build_full_address_skips_empty_parts(): void {
		$result = $this->handler->public_build_full_address(
			'456 River Road',
			'Austin',
			'TX',
			'',
			'United States'
		);

		$this->assertSame( '456 River Road, Austin, TX, United States', $result );
	}

	/**
	 * Tests that build_full_address() handles all empty parts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_build_full_address_all_empty(): void {
		$result = $this->handler->public_build_full_address( '', '', '', '', '' );
		$this->assertSame( '', $result );
	}

	/**
	 * Tests that build_full_address() handles a single part.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_build_full_address_single_part(): void {
		$result = $this->handler->public_build_full_address( 'Berlin' );
		$this->assertSame( 'Berlin', $result );
	}

	/**
	 * Tests that build_full_address() trims whitespace from parts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_build_full_address_trims_parts(): void {
		$result = $this->handler->public_build_full_address(
			'  123 Main St  ',
			'  Portland  ',
			' OR '
		);

		$this->assertSame( '123 Main St, Portland, OR', $result );
	}

	/**
	 * Tests that build_full_address() skips whitespace-only parts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_build_full_address_skips_whitespace_only_parts(): void {
		$result = $this->handler->public_build_full_address(
			'Berlin',
			'   ',
			'Germany'
		);

		$this->assertSame( 'Berlin, Germany', $result );
	}

	/**
	 * Tests that get_venue_detail_meta_keys() returns the correct keys.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_venue_detail_meta_keys(): void {
		$keys = $this->handler->public_get_venue_detail_meta_keys();

		$this->assertContains( '_test_address', $keys );
		$this->assertContains( '_test_city', $keys );
		$this->assertContains( '_test_state', $keys );
		$this->assertContains( '_test_zip', $keys );
		$this->assertContains( '_test_country', $keys );
		$this->assertContains( '_test_phone', $keys );
		$this->assertContains( '_test_website', $keys );
		$this->assertCount( 7, $keys );
	}

	/**
	 * Tests that get_stash_meta_keys() includes venue detail keys.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_meta_keys_include_venue_detail_keys(): void {
		$keys = $this->handler->get_stash_meta_keys();

		$this->assertContains( '_test_start', $keys );
		$this->assertContains( '_test_end', $keys );
		$this->assertContains( '_test_address', $keys );
		$this->assertContains( '_test_phone', $keys );
	}

	/**
	 * Tests that save_venue_information() saves valid JSON on a venue post.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_save_venue_information_on_venue_post(): void {
		$venue_id = $this->factory()->post->create(
			array(
				'post_type'   => 'gatherpress_venue',
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		$result = $this->handler->public_save_venue_information(
			$venue_id,
			'123 Main St, Portland, OR',
			'+1-503-555-0100',
			'https://example.com',
			'45.5231',
			'-122.6765'
		);

		$this->assertTrue( $result );

		$meta = get_post_meta( $venue_id, 'gatherpress_venue_information', true );
		$this->assertNotEmpty( $meta );

		$decoded = json_decode( $meta, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( '123 Main St, Portland, OR', $decoded['fullAddress'] );
		$this->assertSame( '+1-503-555-0100', $decoded['phoneNumber'] );
		$this->assertSame( 'https://example.com', $decoded['website'] );
		$this->assertSame( '45.5231', $decoded['latitude'] );
		$this->assertSame( '-122.6765', $decoded['longitude'] );
	}

	/**
	 * Tests that save_venue_information() stores empty strings for omitted fields.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_save_venue_information_empty_fields(): void {
		$venue_id = $this->factory()->post->create(
			array(
				'post_type'   => 'gatherpress_venue',
				'post_title'  => 'Minimal Venue',
				'post_status' => 'publish',
			)
		);

		$result = $this->handler->public_save_venue_information(
			$venue_id,
			'Berlin, Germany'
		);

		$this->assertTrue( $result );

		$decoded = json_decode( get_post_meta( $venue_id, 'gatherpress_venue_information', true ), true );
		$this->assertSame( 'Berlin, Germany', $decoded['fullAddress'] );
		$this->assertSame( '', $decoded['phoneNumber'] );
		$this->assertSame( '', $decoded['website'] );
		$this->assertSame( '', $decoded['latitude'] );
		$this->assertSame( '', $decoded['longitude'] );
	}

	/**
	 * Tests that save_venue_information() returns false for non-venue posts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_save_venue_information_fails_for_non_venue_post(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Blog Post',
				'post_status' => 'publish',
			)
		);

		$result = $this->handler->public_save_venue_information( $post_id, 'Some Address' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests that save_venue_information() returns false for non-existent post.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_save_venue_information_fails_for_nonexistent_post(): void {
		$result = $this->handler->public_save_venue_information( 99999, 'Some Address' );
		$this->assertFalse( $result );
	}

	/**
	 * Tests that vdh_stash_venue_meta_on_import intercepts venue detail keys.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_intercepts_venue_detail_keys(): void {
		$venue_id = $this->factory()->post->create(
			array(
				'post_type'   => 'gatherpress_venue',
				'post_title'  => 'Stash Test Venue',
				'post_status' => 'publish',
			)
		);

		$result = $this->handler->vdh_stash_venue_meta_on_import(
			null,
			$venue_id,
			'_test_address',
			'123 Main Street',
			false
		);

		$this->assertTrue( $result, 'Should return true to block meta saving.' );

		$stash = get_transient( 'gpei_venue_meta_stash_' . $venue_id );
		$this->assertIsArray( $stash );
		$this->assertArrayHasKey( '_test_address', $stash );
		$this->assertSame( '123 Main Street', $stash['_test_address'] );

		// Clean up.
		delete_transient( 'gpei_venue_meta_stash_' . $venue_id );
		delete_transient( 'gpei_pending_venue_ids' );
	}

	/**
	 * Tests that vdh_stash_venue_meta_on_import passes through non-venue posts.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_passes_through_for_non_venue_posts(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Blog Post',
				'post_status' => 'publish',
			)
		);

		$result = $this->handler->vdh_stash_venue_meta_on_import(
			null,
			$post_id,
			'_test_address',
			'123 Main Street',
			false
		);

		$this->assertNull( $result, 'Should return null to allow normal meta saving.' );
	}

	/**
	 * Tests that vdh_stash_venue_meta_on_import passes through non-mapped keys.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_passes_through_for_non_mapped_keys(): void {
		$venue_id = $this->factory()->post->create(
			array(
				'post_type'   => 'gatherpress_venue',
				'post_title'  => 'Pass Through Venue',
				'post_status' => 'publish',
			)
		);

		$result = $this->handler->vdh_stash_venue_meta_on_import(
			null,
			$venue_id,
			'_some_unknown_key',
			'some value',
			false
		);

		$this->assertNull( $result, 'Should return null for unknown meta keys.' );
	}

	/**
	 * Tests that vdh_stash_venue_meta_on_import accumulates multiple keys.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_accumulates_multiple_keys(): void {
		$venue_id = $this->factory()->post->create(
			array(
				'post_type'   => 'gatherpress_venue',
				'post_title'  => 'Multi Key Venue',
				'post_status' => 'publish',
			)
		);

		$this->handler->vdh_stash_venue_meta_on_import( null, $venue_id, '_test_address', '123 Main St', false );
		$this->handler->vdh_stash_venue_meta_on_import( null, $venue_id, '_test_city', 'Portland', false );
		$this->handler->vdh_stash_venue_meta_on_import( null, $venue_id, '_test_phone', '+1-503-555-0100', false );

		$stash = get_transient( 'gpei_venue_meta_stash_' . $venue_id );
		$this->assertIsArray( $stash );
		$this->assertCount( 3, $stash );
		$this->assertSame( '123 Main St', $stash['_test_address'] );
		$this->assertSame( 'Portland', $stash['_test_city'] );
		$this->assertSame( '+1-503-555-0100', $stash['_test_phone'] );

		// Clean up.
		delete_transient( 'gpei_venue_meta_stash_' . $venue_id );
		delete_transient( 'gpei_pending_venue_ids' );
	}

	/**
	 * Tests that vdh_stash tracks pending venue IDs without duplicates.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_stash_tracks_pending_venue_ids(): void {
		$venue_id = $this->factory()->post->create(
			array(
				'post_type'   => 'gatherpress_venue',
				'post_title'  => 'Pending ID Venue',
				'post_status' => 'publish',
			)
		);

		$this->handler->vdh_stash_venue_meta_on_import( null, $venue_id, '_test_address', '123 Main St', false );
		$this->handler->vdh_stash_venue_meta_on_import( null, $venue_id, '_test_city', 'Portland', false );

		$pending = get_transient( 'gpei_pending_venue_ids' );
		$this->assertIsArray( $pending );

		// Should only be listed once despite two stash calls.
		$occurrences = array_count_values( array_map( 'intval', $pending ) );
		$this->assertSame( 1, $occurrences[ $venue_id ] );

		// Clean up.
		delete_transient( 'gpei_venue_meta_stash_' . $venue_id );
		delete_transient( 'gpei_pending_venue_ids' );
	}

	/**
	 * Tests that setup_venue_detail_hooks() is idempotent.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_setup_venue_detail_hooks_idempotent(): void {
		$handler = new VenueDetailHandlerTestClass();

		$handler->setup_import_hooks();
		$handler->setup_import_hooks(); // Call again.

		$priority = has_filter( 'add_post_metadata', array( $handler, 'vdh_stash_venue_meta_on_import' ) );
		$this->assertSame( 4, $priority );
	}
}
