<?php
/**
 * Unit tests for the ICS Importer parser logic.
 *
 * Tests ICS parsing, datetime handling, timezone extraction, and
 * edge cases using a reflection-based approach to access the private
 * parser methods on the ICS_Importer singleton.
 *
 * @package GatherPressExportImport\Tests\Unit
 * @since   0.3.0
 */

use GatherPressExportImport\ICS_Importer;

/**
 * Class ICSImporterParserTest.
 *
 * @since 0.3.0
 * @group ics-importer
 */
class ICSImporterParserTest extends \WP_UnitTestCase {

	/**
	 * The ICS Importer instance.
	 *
	 * @since 0.3.0
	 *
	 * @var ICS_Importer
	 */
	private ICS_Importer $importer;

	/**
	 * Sets up the test fixture.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->importer = ICS_Importer::get_instance();
	}

	/**
	 * Invokes a private or protected method on the importer via reflection.
	 *
	 * @since 0.3.0
	 *
	 * @param string $method_name The method name.
	 * @param array  $args        Arguments to pass.
	 * @return mixed The return value.
	 */
	private function invoke_private( string $method_name, array $args = [] ) {
		$reflection = new \ReflectionMethod( ICS_Importer::class, $method_name );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $this->importer, $args );
	}

	/**
	 * Gets the ICS fixture file content.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	private function get_fixture_content(): string {
		$path = dirname( __DIR__, 2 ) . '/fixtures/ics/EO-export.ics';
		return file_get_contents( $path );
	}

	// -----------------------------------------------------------------
	// parse_ics() tests
	// -----------------------------------------------------------------

	/**
	 * Tests that the fixture ICS file is parsed into 3 events.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_fixture_returns_three_events(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertCount( 3, $events, 'The EO fixture should contain exactly 3 VEVENT components.' );
	}

	/**
	 * Tests that SUMMARY is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_summary(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$summaries = array_column( $events, 'summary' );
		$this->assertContains( 'The History of Open Source Software', $summaries );
		$this->assertContains( 'Friday Night Live Jazz Session', $summaries );
		$this->assertContains( 'WordPress Translation Sprint', $summaries );
	}

	/**
	 * Tests that DESCRIPTION is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_description(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertStringContainsString(
			'fascinating lecture',
			$events[0]['description']
		);
	}

	/**
	 * Tests that X-ALT-DESC (HTML description) is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_html_description(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertStringContainsString( '<p>', $events[0]['html_desc'] );
		$this->assertStringContainsString( 'fascinating lecture', $events[0]['html_desc'] );
	}

	/**
	 * Tests that DTSTART with trailing Z is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_dtstart_utc(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertSame( '20250828T183000Z', $events[0]['dtstart'] );
	}

	/**
	 * Tests that DTEND with trailing Z is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_dtend_utc(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertSame( '20250828T203000Z', $events[0]['dtend'] );
	}

	/**
	 * Tests that LOCATION is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_location(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertSame( 'University Lecture Theatre', $events[0]['location'] );
		$this->assertSame( 'The Jazz Cellar', $events[1]['location'] );
		$this->assertSame( 'Community Hackerspace', $events[2]['location'] );
	}

	/**
	 * Tests that GEO (latitude;longitude) is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_geo(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertSame( '51.522600;-0.130800', $events[0]['geo'] );
		$this->assertSame( '51.513800;-0.131800', $events[1]['geo'] );
	}

	/**
	 * Tests that URL is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_url(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertStringContainsString( 'the-history-of-open-source-software', $events[0]['url'] );
	}

	/**
	 * Tests that CATEGORIES is parsed correctly.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_extracts_categories(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		$this->assertSame( 'Lecture', $events[0]['categories'] );
		$this->assertSame( 'Performance', $events[1]['categories'] );
		$this->assertSame( 'Sprint', $events[2]['categories'] );
	}

	/**
	 * Tests parsing of empty ICS content.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_empty_content_returns_empty_array(): void {
		$events = $this->invoke_private( 'parse_ics', array( '' ) );
		$this->assertSame( array(), $events );
	}

	/**
	 * Tests parsing of ICS with no VEVENT components.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_no_events_returns_empty_array(): void {
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertSame( array(), $events );
	}

	/**
	 * Tests that VEVENT without SUMMARY is skipped.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_skips_event_without_summary(): void {
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nDTSTART:20250915T090000Z\nEND:VEVENT\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertCount( 0, $events );
	}

	/**
	 * Tests that VEVENT without DTSTART is skipped.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_skips_event_without_dtstart(): void {
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:No Start Date\nEND:VEVENT\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertCount( 0, $events );
	}

	/**
	 * Tests parsing with CRLF line endings.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_handles_crlf_line_endings(): void {
		$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:CRLF Test\r\nDTSTART:20250915T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertCount( 1, $events );
		$this->assertSame( 'CRLF Test', $events[0]['summary'] );
	}

	/**
	 * Tests parsing with line folding (RFC 5545 continuations).
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_handles_line_folding(): void {
		$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:A Very Long Event\r\n  Title That Wraps\r\nDTSTART:20250915T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertCount( 1, $events );
		$this->assertSame( 'A Very Long Event Title That Wraps', $events[0]['summary'] );
	}

	/**
	 * Tests that multiple CATEGORIES lines are accumulated.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_accumulates_multiple_categories(): void {
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:Multi Cat\nDTSTART:20250915T090000Z\nCATEGORIES:Lecture\nCATEGORIES:Workshop\nEND:VEVENT\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertCount( 1, $events );
		$this->assertSame( 'Lecture,Workshop', $events[0]['categories'] );
	}

	/**
	 * Tests ICS text unescaping (newlines, commas, semicolons, backslashes).
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_unescapes_text_values(): void {
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:Test\\, with comma\nDESCRIPTION:Line one\\nLine two\\;end\nDTSTART:20250915T090000Z\nEND:VEVENT\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertSame( 'Test, with comma', $events[0]['summary'] );
		$this->assertStringContainsString( "Line one\nLine two;end", $events[0]['description'] );
	}

	// -----------------------------------------------------------------
	// parse_ics_datetime() tests
	// -----------------------------------------------------------------

	/**
	 * Tests parsing UTC datetime format (20250828T183000Z).
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_datetime_utc(): void {
		$result = $this->invoke_private( 'parse_ics_datetime', array( '20250828T183000Z', 'UTC' ) );
		$this->assertSame( '2025-08-28 18:30:00', $result );
	}

	/**
	 * Tests parsing local datetime format (20250905T200000).
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_datetime_local(): void {
		$result = $this->invoke_private( 'parse_ics_datetime', array( '20250905T200000', 'America/New_York' ) );
		$this->assertSame( '2025-09-05 20:00:00', $result );
	}

	/**
	 * Tests parsing all-day date format (20250915).
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_datetime_all_day(): void {
		$result = $this->invoke_private( 'parse_ics_datetime', array( '20250915', 'UTC' ) );
		$this->assertSame( '2025-09-15 00:00:00', $result );
	}

	/**
	 * Tests parsing empty datetime returns empty string.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_datetime_empty(): void {
		$result = $this->invoke_private( 'parse_ics_datetime', array( '', 'UTC' ) );
		$this->assertSame( '', $result );
	}

	// -----------------------------------------------------------------
	// extract_timezone() tests
	// -----------------------------------------------------------------

	/**
	 * Tests timezone extraction for UTC (Z suffix).
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_extract_timezone_utc(): void {
		$event_data = array(
			'dtstart'        => '20250828T183000Z',
			'dtstart_params' => '',
		);
		$result = $this->invoke_private( 'extract_timezone', array( $event_data ) );
		$this->assertSame( 'UTC', $result );
	}

	/**
	 * Tests timezone extraction with TZID parameter.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_extract_timezone_tzid(): void {
		$event_data = array(
			'dtstart'        => '20250828T183000',
			'dtstart_params' => 'TZID=America/New_York',
		);
		$result = $this->invoke_private( 'extract_timezone', array( $event_data ) );
		$this->assertSame( 'America/New_York', $result );
	}

	/**
	 * Tests timezone extraction with quoted TZID parameter.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_extract_timezone_quoted_tzid(): void {
		$event_data = array(
			'dtstart'        => '20250828T183000',
			'dtstart_params' => 'TZID="Europe/Berlin"',
		);
		$result = $this->invoke_private( 'extract_timezone', array( $event_data ) );
		$this->assertSame( 'Europe/Berlin', $result );
	}

	/**
	 * Tests timezone falls back to site default when no TZID and no Z.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_extract_timezone_falls_back_to_site_default(): void {
		$event_data = array(
			'dtstart'        => '20250828T183000',
			'dtstart_params' => '',
		);
		$result = $this->invoke_private( 'extract_timezone', array( $event_data ) );
		$this->assertSame( wp_timezone_string(), $result );
	}

	/**
	 * Tests that all 3 fixture events have UTC timezone extracted.
	 *
	 * The EO fixture uses trailing Z on DTSTART values.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_fixture_events_have_utc_timezone(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		foreach ( $events as $event ) {
			$tz = $this->invoke_private( 'extract_timezone', array( $event ) );
			$this->assertSame( 'UTC', $tz, 'EO fixture events should be detected as UTC.' );
		}
	}

	// -----------------------------------------------------------------
	// Edge case tests
	// -----------------------------------------------------------------

	/**
	 * Tests parsing event with DTSTART but no DTEND.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_event_without_dtend(): void {
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:No End Time\nDTSTART:20250915T090000Z\nEND:VEVENT\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertCount( 1, $events );
		$this->assertEmpty( $events[0]['dtend'] );
	}

	/**
	 * Tests parsing event without LOCATION has empty location.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_parse_ics_event_without_location(): void {
		$ics = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nSUMMARY:Online Only\nDTSTART:20250915T090000Z\nURL:https://example.com/meet\nEND:VEVENT\nEND:VCALENDAR";
		$events = $this->invoke_private( 'parse_ics', array( $ics ) );
		$this->assertCount( 1, $events );
		$this->assertEmpty( $events[0]['location'] );
		$this->assertSame( 'https://example.com/meet', $events[0]['url'] );
	}

	/**
	 * Tests that URL is NOT saved as online event link when LOCATION is present.
	 *
	 * This validates the parsing level — the event data has both URL and LOCATION.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function test_fixture_events_have_both_url_and_location(): void {
		$content = $this->get_fixture_content();
		$events  = $this->invoke_private( 'parse_ics', array( $content ) );

		foreach ( $events as $event ) {
			$this->assertNotEmpty( $event['location'], 'All fixture events should have a LOCATION.' );
			$this->assertNotEmpty( $event['url'], 'All fixture events should have a URL.' );
		}
	}
}