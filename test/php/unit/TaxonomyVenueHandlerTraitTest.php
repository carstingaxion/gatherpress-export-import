<?php
/**
 * Unit tests for the Taxonomy_Venue_Handler trait.
 *
 * Tests pass detection, event skipping, term filtering, and
 * post data capture in isolation using stubs.
 *
 * @package GatherPressExportImport\Tests\Unit
 * @since   0.1.0
 */

use GatherPressExportImport\Source_Adapter;
use GatherPressExportImport\Hookable_Adapter;
use GatherPressExportImport\Taxonomy_Venue_Adapter;
use GatherPressExportImport\Datetime_Helper;
use GatherPressExportImport\Taxonomy_Venue_Handler;

/**
 * Concrete class using the Taxonomy Venue Handler trait for testing.
 *
 * Implements all required interfaces to exercise the trait methods.
 *
 * @since 0.1.0
 */
class TaxonomyVenueHandlerTestClass implements Hookable_Adapter, Source_Adapter, Taxonomy_Venue_Adapter {
	use Datetime_Helper;
	use Taxonomy_Venue_Handler;

	/** @return string */
	public function get_name(): string {
		return 'Test Adapter';
	}

	/** @return string */
	public function get_venue_taxonomy_slug(): string {
		return 'test-venue-tax';
	}

	/** @return string[] */
	public function get_skippable_event_post_types(): array {
		return array( 'test_event' );
	}

	/** @return array<string, string> */
	public function get_event_post_type_map(): array {
		return array( 'test_event' => 'gatherpress_event' );
	}

	/** @return array<string, string> */
	public function get_venue_post_type_map(): array {
		return array();
	}

	/** @return string[] */
	public function get_stash_meta_keys(): array {
		return array();
	}

	/** @return array */
	public function get_pseudopostmetas(): array {
		return array();
	}

	/** @return bool */
	public function can_handle( array $stash ): bool {
		return false;
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
		$this->setup_taxonomy_venue_hooks();
	}

	/**
	 * Public proxy to test is_venue_pass().
	 *
	 * @return bool
	 */
	public function public_is_venue_pass(): bool {
		return $this->is_venue_pass();
	}
}

/**
 * Class TaxonomyVenueHandlerTraitTest.
 *
 * @since 0.1.0
 */
class TaxonomyVenueHandlerTraitTest extends \WP_UnitTestCase {

	/**
	 * The handler instance under test.
	 *
	 * @since 0.1.0
	 *
	 * @var TaxonomyVenueHandlerTestClass
	 */
	private TaxonomyVenueHandlerTestClass $handler;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->handler = new TaxonomyVenueHandlerTestClass();
	}

	/**
	 * Tests that the handler defaults to venue pass mode.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_defaults_to_venue_pass(): void {
		$this->assertTrue( $this->handler->public_is_venue_pass() );
	}

	/**
	 * Tests that tvh_capture_current_post_data() passes through non-matching types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_capture_ignores_non_matching_post_types(): void {
		$data = array(
			'post_type'  => 'page',
			'post_title' => 'Some Page',
		);

		$result = $this->handler->tvh_capture_current_post_data( $data );
		$this->assertSame( $data, $result );
	}

	/**
	 * Tests that tvh_capture_current_post_data() captures matching post types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_capture_records_matching_post_type(): void {
		$data = array(
			'post_type'  => 'test_event',
			'post_title' => 'My Test Event',
		);

		$result = $this->handler->tvh_capture_current_post_data( $data );
		$this->assertSame( $data, $result ); // Data should pass through unmodified.
	}

	/**
	 * Tests that tvh_maybe_flag_events_on_venue_pass() skips events during venue pass.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_flag_events_skips_during_venue_pass(): void {
		$data = array(
			'post_type'  => 'test_event',
			'post_title' => 'Test Event Title',
		);

		$result = $this->handler->tvh_maybe_flag_events_on_venue_pass( $data );
		$this->assertSame( '_gpei_skip', $result['post_type'] );
	}

	/**
	 * Tests that tvh_maybe_flag_events_on_venue_pass() ignores non-skippable types.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_flag_events_ignores_non_skippable_types(): void {
		$data = array(
			'post_type'  => 'post',
			'post_title' => 'Blog Post',
		);

		$result = $this->handler->tvh_maybe_flag_events_on_venue_pass( $data );
		$this->assertSame( 'post', $result['post_type'] );
	}

	/**
	 * Tests that tvh_filter_venue_terms() removes venue terms from the list.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_filter_venue_terms_removes_venue_terms(): void {
		$terms = array(
			array(
				'domain' => 'test-venue-tax',
				'slug'   => 'test-venue-slug',
				'name'   => 'Test Venue',
			),
			array(
				'domain' => 'category',
				'slug'   => 'uncategorized',
				'name'   => 'Uncategorized',
			),
		);

		$result = $this->handler->tvh_filter_venue_terms( $terms );

		// The venue term should be removed.
		$this->assertCount( 1, $result );
		$this->assertSame( 'category', $result[0]['domain'] );
	}

	/**
	 * Tests that tvh_filter_venue_terms() preserves non-venue terms.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_filter_venue_terms_preserves_other_terms(): void {
		$terms = array(
			array(
				'domain' => 'post_tag',
				'slug'   => 'wordpress',
				'name'   => 'WordPress',
			),
			array(
				'domain' => 'category',
				'slug'   => 'events',
				'name'   => 'Events',
			),
		);

		$result = $this->handler->tvh_filter_venue_terms( $terms );
		$this->assertCount( 2, $result );
	}

	/**
	 * Tests that tvh_intercept_venue_term_creation() blocks venue terms.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_intercept_venue_term_creation_blocks_venue_terms(): void {
		$result = $this->handler->tvh_intercept_venue_term_creation( 'My Venue', 'test-venue-tax' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gpei_venue_handled', $result->get_error_code() );
	}

	/**
	 * Tests that tvh_intercept_venue_term_creation() passes through non-venue terms.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_intercept_venue_term_creation_passes_other_taxonomies(): void {
		$result = $this->handler->tvh_intercept_venue_term_creation( 'My Category', 'category' );
		$this->assertSame( 'My Category', $result );
	}

	/**
	 * Tests that tvh_track_saved_post_id() does not throw.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_track_saved_post_id_no_error(): void {
		// With the stub for get_post_type() returning '', this should
		// exit early without error.
		$this->handler->tvh_track_saved_post_id( 42 );
		$this->assertTrue( true );
	}
}
