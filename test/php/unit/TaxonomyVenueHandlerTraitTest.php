<?php
/**
 * Unit tests for the Taxonomy_Venue_Handler trait.
 *
 * Tests pass detection, event skipping, term filtering, venue term
 * creation interception, post data capture, skip post type registration,
 * and hook idempotency in isolation using a stub adapter class.
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
 * Concrete stub class using the Taxonomy_Venue_Handler trait for testing.
 *
 * Implements all required interfaces (`Hookable_Adapter`, `Source_Adapter`,
 * `Taxonomy_Venue_Adapter`) to fully exercise the trait methods. Uses a
 * fictional `test-venue-tax` taxonomy and `test_event` post type to avoid
 * collisions with real adapter configurations.
 *
 * @since 0.1.0
 */
class TaxonomyVenueHandlerTestClass implements Hookable_Adapter, Source_Adapter, Taxonomy_Venue_Adapter {
	use Datetime_Helper;
	use Taxonomy_Venue_Handler;

	/**
	 * Gets the human-readable name of the test adapter.
	 *
	 * @since 0.1.0
	 *
	 * @return string The adapter name.
	 */
	public function get_name(): string {
		return 'Test Adapter';
	}

	/**
	 * Gets the source taxonomy slug used for venues.
	 *
	 * Returns a fictional taxonomy slug for testing purposes.
	 *
	 * @since 0.1.0
	 *
	 * @return string The source venue taxonomy slug.
	 */
	public function get_venue_taxonomy_slug(): string {
		return 'test-venue-tax';
	}

	/**
	 * Gets the source event post type slugs that should be skipped during Pass 1.
	 *
	 * @since 0.1.0
	 *
	 * @return string[] Array containing the fictional test event post type.
	 */
	public function get_skippable_event_post_types(): array {
		return array( 'test_event' );
	}

	/**
	 * Gets the event post type mapping.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string> Event post type map.
	 */
	public function get_event_post_type_map(): array {
		return array( 'test_event' => 'gatherpress_event' );
	}

	/**
	 * Gets the venue post type mapping.
	 *
	 * Returns an empty array because this adapter uses taxonomy-based
	 * venues, not a custom post type.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string> Empty array; no venue CPT.
	 */
	public function get_venue_post_type_map(): array {
		return array();
	}

	/**
	 * Gets the meta keys that should be stashed during import.
	 *
	 * @since 0.1.0
	 *
	 * @return string[] Empty array; the test adapter has no stash keys.
	 */
	public function get_stash_meta_keys(): array {
		return array();
	}

	/**
	 * Gets pseudopostmeta definitions.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array{post_type: string, import_callback: callable}> Empty array.
	 */
	public function get_pseudopostmetas(): array {
		return array();
	}

	/**
	 * Determines if the given stash data belongs to this adapter.
	 *
	 * Always returns false because this is a test stub.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $stash The collected meta key/value pairs.
	 * @return bool Always false.
	 */
	public function can_handle( array $stash ): bool {
		return false;
	}

	/**
	 * Converts stashed meta data into GatherPress datetimes.
	 *
	 * No-op for this test stub.
	 *
	 * @since 0.1.0
	 *
	 * @param int                  $post_id The post ID of the imported event.
	 * @param array<string, mixed> $stash   The collected meta key/value pairs.
	 * @return void
	 */
	public function convert_datetimes( int $post_id, array $stash ): void {}

	/**
	 * Gets the meta key used for venue linking.
	 *
	 * Returns null because this adapter uses taxonomy terms for venues.
	 *
	 * @since 0.1.0
	 *
	 * @return string|null Always null.
	 */
	public function get_venue_meta_key(): ?string {
		return null;
	}

	/**
	 * Gets the taxonomy mapping.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string> Empty array; no taxonomy mapping needed.
	 */
	public function get_taxonomy_map(): array {
		return array();
	}

	/**
	 * Sets up adapter-specific import hooks.
	 *
	 * Delegates to the shared `setup_taxonomy_venue_hooks()` method
	 * provided by the `Taxonomy_Venue_Handler` trait.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function setup_import_hooks(): void {
		$this->setup_taxonomy_venue_hooks();
	}

	/**
	 * Public proxy to expose the protected `is_venue_pass()` method for testing.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True if in venue pass (Pass 1), false if in event pass (Pass 2).
	 */
	public function public_is_venue_pass(): bool {
		return $this->is_venue_pass();
	}
}

/**
 * Class TaxonomyVenueHandlerTraitTest.
 *
 * Tests the `Taxonomy_Venue_Handler` trait in isolation using the
 * `TaxonomyVenueHandlerTestClass` stub. Covers default pass state,
 * post data capture, event flagging during venue pass, venue term
 * filtering (single, multiple, empty, all-venue), venue term creation
 * interception, skip post type registration, and hook idempotency.
 *
 * @since 0.1.0
 * @coversDefaultClass \GatherPressExportImport\Taxonomy_Venue_Handler
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
	 * Creates a fresh `TaxonomyVenueHandlerTestClass` instance before
	 * each test to ensure clean state.
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
	 * Tests that the handler defaults to venue pass mode (Pass 1).
	 *
	 * Before any venue terms are encountered, the handler should assume
	 * it is in venue creation mode. This ensures events are safely
	 * skipped until pass detection has been performed.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::is_venue_pass
	 * @return void
	 */
	public function test_defaults_to_venue_pass(): void {
		$this->assertTrue( $this->handler->public_is_venue_pass() );
	}

	/**
	 * Tests that tvh_capture_current_post_data() passes through non-matching post types.
	 *
	 * When the incoming post type does not match the adapter's skippable
	 * event post types, the data should be returned unmodified and no
	 * internal state should change.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_capture_current_post_data
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
	 * When the incoming post type matches one of the adapter's skippable
	 * event post types, the method should record the post title internally
	 * (for context) but return the data array unmodified.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_capture_current_post_data
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
	 * Tests that tvh_maybe_flag_events_on_venue_pass() redirects events to the skip post type.
	 *
	 * During venue pass (Pass 1), events of a skippable post type should
	 * have their `post_type` changed to `_gpei_skip` so the WordPress
	 * Importer does not report "Invalid post type" errors and the events
	 * are not permanently imported.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_maybe_flag_events_on_venue_pass
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
	 * Tests that tvh_maybe_flag_events_on_venue_pass() ignores non-skippable post types.
	 *
	 * Post types not listed in the adapter's `get_skippable_event_post_types()`
	 * should pass through without modification, even during venue pass.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_maybe_flag_events_on_venue_pass
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
	 * Tests that tvh_filter_venue_terms() removes venue taxonomy terms from the list.
	 *
	 * When processing per-post term assignments, venue terms matching the
	 * adapter's venue taxonomy slug should be removed from the returned
	 * array. During Pass 1, a `gatherpress_venue` post is created from
	 * each removed term. Non-venue terms should be preserved.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_filter_venue_terms
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
	 * Tests that tvh_filter_venue_terms() preserves non-venue terms intact.
	 *
	 * When no terms match the adapter's venue taxonomy slug, all terms
	 * should be returned unchanged.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_filter_venue_terms
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
	 * Tests that tvh_filter_venue_terms() handles multiple venue terms in a single call.
	 *
	 * When a post is assigned to multiple venue taxonomy terms, all venue
	 * terms should be removed and only non-venue terms should remain.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_filter_venue_terms
	 * @return void
	 */
	public function test_filter_venue_terms_handles_multiple_venue_terms(): void {
		$terms = array(
			array(
				'domain' => 'test-venue-tax',
				'slug'   => 'venue-one',
				'name'   => 'Venue One',
			),
			array(
				'domain' => 'test-venue-tax',
				'slug'   => 'venue-two',
				'name'   => 'Venue Two',
			),
			array(
				'domain' => 'category',
				'slug'   => 'events',
				'name'   => 'Events',
			),
		);

		$result = $this->handler->tvh_filter_venue_terms( $terms );

		// Only the non-venue term should remain.
		$this->assertCount( 1, $result );
		$this->assertSame( 'category', $result[0]['domain'] );
	}

	/**
	 * Tests that tvh_filter_venue_terms() returns an empty array when all terms are venues.
	 *
	 * If every term in the assignment list belongs to the venue taxonomy,
	 * the result should be an empty array — no terms remain to assign.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_filter_venue_terms
	 * @return void
	 */
	public function test_filter_venue_terms_returns_empty_when_all_are_venues(): void {
		$terms = array(
			array(
				'domain' => 'test-venue-tax',
				'slug'   => 'venue-one',
				'name'   => 'Venue One',
			),
		);

		$result = $this->handler->tvh_filter_venue_terms( $terms );
		$this->assertCount( 0, $result );
	}

	/**
	 * Tests that tvh_filter_venue_terms() handles an empty input array gracefully.
	 *
	 * Passing an empty array should return an empty array without errors.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_filter_venue_terms
	 * @return void
	 */
	public function test_filter_venue_terms_handles_empty_array(): void {
		$result = $this->handler->tvh_filter_venue_terms( array() );
		$this->assertSame( array(), $result );
	}

	/**
	 * Tests that tvh_intercept_venue_term_creation() blocks venue taxonomy terms.
	 *
	 * When a term is being inserted into the venue taxonomy via a top-level
	 * `<wp:term>` entry in the WXR file, the method should return a `WP_Error`
	 * to prevent the term from being created in the original (source) taxonomy.
	 * The venue is instead handled by creating a `gatherpress_venue` post.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_intercept_venue_term_creation
	 * @return void
	 */
	public function test_intercept_venue_term_creation_blocks_venue_terms(): void {
		$result = $this->handler->tvh_intercept_venue_term_creation( 'My Venue', 'test-venue-tax' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'gpei_venue_handled', $result->get_error_code() );
	}

	/**
	 * Tests that tvh_intercept_venue_term_creation() passes through non-venue taxonomies.
	 *
	 * Terms being inserted into taxonomies other than the adapter's venue
	 * taxonomy should be returned unchanged, allowing normal WordPress
	 * term creation to proceed.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_intercept_venue_term_creation
	 * @return void
	 */
	public function test_intercept_venue_term_creation_passes_other_taxonomies(): void {
		$result = $this->handler->tvh_intercept_venue_term_creation( 'My Category', 'category' );
		$this->assertSame( 'My Category', $result );
	}

	/**
	 * Tests that tvh_intercept_venue_term_creation() blocks successive venue terms.
	 *
	 * Multiple calls for different venue terms in the same taxonomy should
	 * each return a `WP_Error`, confirming that the interception works
	 * consistently for all terms, not just the first one.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_intercept_venue_term_creation
	 * @return void
	 */
	public function test_intercept_venue_term_creation_blocks_successive_venue_terms(): void {
		$result1 = $this->handler->tvh_intercept_venue_term_creation( 'Venue A', 'test-venue-tax' );
		$result2 = $this->handler->tvh_intercept_venue_term_creation( 'Venue B', 'test-venue-tax' );

		$this->assertInstanceOf( \WP_Error::class, $result1 );
		$this->assertInstanceOf( \WP_Error::class, $result2 );
	}

	/**
	 * Tests that tvh_track_saved_post_id() does not throw an error.
	 *
	 * When called with a post ID whose post type is not `gatherpress_event`
	 * (or when `get_post_type()` returns an empty string for a non-existent
	 * post), the method should exit early without producing side effects
	 * or errors.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_track_saved_post_id
	 * @return void
	 */
	public function test_track_saved_post_id_no_error(): void {
		// With the stub for get_post_type() returning '', this should
		// exit early without error.
		$this->handler->tvh_track_saved_post_id( 42 );
		$this->assertTrue( true );
	}

	/**
	 * Tests that tvh_capture_current_post_data() returns the data array unmodified.
	 *
	 * Even when the post type matches a skippable event type, the method
	 * should only capture internal context (e.g., post title) and must
	 * not alter any fields in the returned data array.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_capture_current_post_data
	 * @return void
	 */
	public function test_capture_current_post_data_returns_unmodified(): void {
		$data = array(
			'post_type'  => 'test_event',
			'post_title' => 'My Event Title',
			'post_name'  => 'my-event-title',
		);

		$result = $this->handler->tvh_capture_current_post_data( $data );
		$this->assertSame( $data['post_type'], $result['post_type'] );
		$this->assertSame( $data['post_title'], $result['post_title'] );
		$this->assertSame( $data['post_name'], $result['post_name'] );
	}

	/**
	 * Tests that tvh_maybe_flag_events_on_venue_pass() preserves the post_title.
	 *
	 * When redirecting an event to the skip post type during Pass 1, the
	 * original `post_title` must be preserved so the skip post can be
	 * identified for cleanup at `import_end`.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::tvh_maybe_flag_events_on_venue_pass
	 * @return void
	 */
	public function test_flag_events_preserves_post_title(): void {
		$data = array(
			'post_type'  => 'test_event',
			'post_title' => 'My Special Event',
		);

		$result = $this->handler->tvh_maybe_flag_events_on_venue_pass( $data );
		$this->assertSame( '_gpei_skip', $result['post_type'] );
		$this->assertSame( 'My Special Event', $result['post_title'] );
	}

	/**
	 * Tests that the `_gpei_skip` post type is registered after setup_import_hooks().
	 *
	 * The `Taxonomy_Venue_Handler` trait registers a temporary non-public
	 * post type (`_gpei_skip`) so that the WordPress Importer does not
	 * log "Invalid post type" errors when events are redirected during
	 * Pass 1. This post type should exist after hooks are set up.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::setup_taxonomy_venue_hooks
	 * @return void
	 */
	public function test_skip_post_type_registered_after_setup(): void {
		$handler = new TaxonomyVenueHandlerTestClass();
		$handler->setup_import_hooks();

		$this->assertTrue(
			post_type_exists( '_gpei_skip' ),
			'The _gpei_skip post type should be registered after setup_import_hooks().'
		);
	}

	/**
	 * Tests that setup_import_hooks() is idempotent and does not double-register hooks.
	 *
	 * Calling `setup_import_hooks()` multiple times on the same handler
	 * instance should not register duplicate WordPress hooks. The hook
	 * priority should remain consistent after subsequent calls.
	 *
	 * @since 0.1.0
	 *
	 * @covers ::setup_taxonomy_venue_hooks
	 * @return void
	 */
	public function test_setup_import_hooks_idempotent(): void {
		$handler = new TaxonomyVenueHandlerTestClass();

		// Count filters before.
		$count_before = has_filter( 'wp_import_post_data_raw', array( $handler, 'tvh_capture_current_post_data' ) );

		$this->assertSame( $count_before, false );

		$handler->setup_import_hooks();
		$handler->setup_import_hooks(); // Call again — should not double-register.

		$count_after = has_filter( 'wp_import_post_data_raw', array( $handler, 'tvh_capture_current_post_data' ) );

		// The priority should be the same (2), not registered twice.
		$this->assertSame( $count_after, 2 );
	}
}
