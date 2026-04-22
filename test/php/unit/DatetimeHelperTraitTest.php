<?php
/**
 * Unit tests for the Datetime_Helper trait.
 *
 * Tests the venue term slug generation and default timezone retrieval
 * in isolation without a running WordPress environment.
 *
 * @package GatherPressExportImport\Tests\Unit
 * @since   0.1.0
 */

use GatherPressExportImport\Datetime_Helper;

/**
 * Concrete class to expose the protected trait methods for testing.
 *
 * @since 0.1.0
 */
class DatetimeHelperTestClass {
	use Datetime_Helper;

	/**
	 * Public proxy for the protected get_venue_term_slug() method.
	 *
	 * @param string $post_name Post slug.
	 * @return string Generated term slug.
	 */
	public function public_get_venue_term_slug( string $post_name ): string {
		return $this->get_venue_term_slug( $post_name );
	}

	/**
	 * Public proxy for the protected get_default_timezone() method.
	 *
	 * @return string Timezone string.
	 */
	public function public_get_default_timezone(): string {
		return $this->get_default_timezone();
	}
}

/**
 * Class DatetimeHelperTraitTest.
 *
 * @since 0.1.0
 */
class DatetimeHelperTraitTest extends \WP_UnitTestCase {

	/**
	 * The test instance using the trait.
	 *
	 * @since 0.1.0
	 *
	 * @var DatetimeHelperTestClass
	 */
	private DatetimeHelperTestClass $helper;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->helper = new DatetimeHelperTestClass();
	}

	/**
	 * Tests that get_venue_term_slug() prepends an underscore to the post slug.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_venue_term_slug_prepends_underscore(): void {
		$this->assertSame( '_university-lecture-theatre', $this->helper->public_get_venue_term_slug( 'university-lecture-theatre' ) );
	}

	/**
	 * Tests venue term slug generation with various slug formats.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_venue_term_slug_various_formats(): void {
		$this->assertSame( '_my-venue', $this->helper->public_get_venue_term_slug( 'my-venue' ) );
		$this->assertSame( '_a', $this->helper->public_get_venue_term_slug( 'a' ) );
		$this->assertSame( '_', $this->helper->public_get_venue_term_slug( '' ) );
		$this->assertSame( '_venue-with-numbers-123', $this->helper->public_get_venue_term_slug( 'venue-with-numbers-123' ) );
	}

	/**
	 * Tests that get_default_timezone() returns the stubbed UTC value.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function test_get_default_timezone(): void {
		$this->assertSame( 'UTC', $this->helper->public_get_default_timezone() );
	}
}
