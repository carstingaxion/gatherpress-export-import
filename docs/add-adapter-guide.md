# Add a New Adapter

This guide walks through the steps for adding support for a new third-party event plugin. Each adapter encapsulates the knowledge of how the source plugin stores its data and how to convert it into GatherPress format.

---

## Prerequisites

Before you begin, you'll need:

- A working `wp-env` development environment (see [Developer README](developer/README.md))
- The source plugin installed (either locally or in a Playground blueprint)
- Familiarity with how the source plugin stores events, venues, and taxonomies

---

## Step 1: Create the Source Documentation

Create a `docs/source-<plugin-slug>.md` file that documents the source plugin's data model. This serves as both a reference for development and documentation for end users.

**File:** `docs/source-<plugin-slug>.md`

### Template

```markdown
# <Plugin Name> — Source Details

Adapter class: `<Plugin_Name>_Adapter`

---

## Post Type Mapping

| Source | Target |
|---|---|
| `<source_event_cpt>` | `gatherpress_event` |
| `<source_venue_cpt>` | `gatherpress_venue` |

## Date Format

Describe how the source plugin stores datetimes:
- Which meta keys? (e.g., `_EventStartDate`, `_event_start`)
- What format? (e.g., `Y-m-d H:i:s`, Unix timestamp, separate date/time fields)
- Where is the timezone stored? (meta key, site default, etc.)

## Venue Handling

Describe how venues are stored:
- **CPT-based:** Which post type? Which meta keys hold address details?
- **Taxonomy-based:** Which taxonomy slug? (Requires two-pass import)
- **Custom table:** Not available via WXR — note the limitation

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `<source_category_tax>` | `gatherpress_topic` |
| `<source_tag_tax>` | `post_tag` |

## Meta Keys in Real WXR Exports

List ALL meta keys that appear in a real WXR export, not just the ones
used for conversion. Any key not intercepted will leak into `wp_postmeta`.

| Meta Key | Purpose | Used by Adapter |
|---|---|:---:|
| `_SourceStartDate` | Start datetime | ✅ Datetime conversion |
| `_SourceInternalKey` | Internal tracking | 🚫 Stashed & discarded |

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | Standard post fields |
| Start/end datetimes | ✅ / 🚫 | Via meta / custom table |
| Venue name | ✅ | |
| Venue address/details | ✅ / 🚫 | Via post meta / term meta |
| Venue–event link | ✅ | Via meta key / taxonomy |
| Event categories | ✅ | |

## Import Sequence

Describe the recommended import order:
1. For CPT venues: Export/import venues first, then events
2. For taxonomy venues: Import the same WXR file twice (two-pass)

## Known Limitations

- List any data that cannot be imported
- Note shared post type slugs with other plugins
```

### Research checklist

To fill in the template, investigate:

1. **Post types:** Look in the plugin's main file or a `register_post_type()` call
2. **Meta keys:** Create test events/venues in the admin UI, then inspect `wp_postmeta` in the database or export a WXR file
3. **Taxonomies:** Check `register_taxonomy()` calls or the Terms admin screen
4. **Date storage:** Check the plugin's event creation code or inspect meta values in the database
5. **Venue linking:** How does an event reference its venue? (meta key with post ID, taxonomy term, custom table field)

---

## Step 2: Create the Blueprint Import Script

Create a `.wordpress-org/blueprints/<plugin-slug>-import.php` file that programmatically creates realistic demo data using the source plugin's own API.

**File:** `.wordpress-org/blueprints/<plugin-slug>-import.php`

### Why use the plugin's API?

Many event plugins maintain internal state beyond `wp_postmeta` — custom database tables, internal caches, computed fields. Using `wp_insert_post()` alone may create posts that look correct in the database but don't function properly in the plugin's admin UI, views, or REST API.

Always prefer the plugin's own API classes when available, falling back to `wp_insert_post()` with all required meta keys only when the API is unavailable.

### Template

```php
<?php
/**
 * <Plugin Name> demo data import script for WordPress Playground.
 *
 * Creates sample events, venues, categories, and tags using the
 * <Plugin Name> plugin's own API when available, falling back to
 * direct wp_insert_post() with all required meta keys.
 *
 * @see <link to plugin developer docs>
 * @see <link to relevant API class>
 *
 * @package GatherPressExportImport
 */

require_once __DIR__ . '/../wordpress/wp-load.php';

// ---------------------------------------------------------------
// 1. Verify the plugin is available
// ---------------------------------------------------------------
// Check for the plugin's main class, API function, or post type.
if ( ! class_exists( 'Source_Plugin_Class' ) ) {
    error_log( 'GPEI: <Plugin Name> is not active.' );
    // Consider a wp_insert_post() fallback here.
}

// ---------------------------------------------------------------
// 2. Create taxonomy terms (categories, tags, etc.)
// ---------------------------------------------------------------
// Use wp_insert_term() for standard taxonomies.
if ( ! term_exists( 'Category Name', 'source_category_tax' ) ) {
    wp_insert_term( 'Category Name', 'source_category_tax', array(
        'slug' => 'category-name',
    ) );
}

// ---------------------------------------------------------------
// 3. Create venues
// ---------------------------------------------------------------
// Use the plugin's venue API if available.
// Include realistic address data for testing venue detail conversion.

// ---------------------------------------------------------------
// 4. Create events
// ---------------------------------------------------------------
// Use the plugin's event API if available.
// Include:
// - Varied datetimes (past, future, different timezones)
// - Venue references
// - Category and tag assignments
// - Event descriptions with real content

// ---------------------------------------------------------------
// 5. Flush rewrite rules
// ---------------------------------------------------------------
flush_rewrite_rules();

error_log( 'GPEI: <Plugin Name> demo data import complete.' );
```

### Demo data guidelines

Create at least:
- **3 venues** with varying address completeness (full address, partial, minimal)
- **3 events** with different datetime patterns, venue assignments, and taxonomy terms
- **2–3 category terms** and **2–3 tag terms**

This ensures the adapter and tests cover realistic edge cases.

### Update the blueprint JSON

Create or update `.wordpress-org/blueprints/blueprint-<plugin-slug>.json`:

```json
{
    "$schema": "https://playground.wordpress.net/blueprint-schema.json",
    "meta": {
        "title": "<Plugin Name> — Demo Export",
        "description": "Demo site with <Plugin Name> data ready to export."
    },
    "preferredVersions": {
        "php": "8.0",
        "wp": "latest"
    },
    "features": {
        "networking": true
    },
    "plugins": [
        "<plugin-slug>"
    ],
    "steps": [
        {
            "step": "login",
            "username": "admin",
            "password": "password"
        },
        {
            "step": "setSiteOptions",
            "options": {
                "blogname": "<Plugin Name> Demo for GatherPress Migration"
            }
        },
        {
            "step": "writeFile",
            "path": "/tmp/<plugin-slug>-import.php",
            "data": {
                "resource": "url",
                "url": "https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/main/.wordpress-org/blueprints/<plugin-slug>-import.php"
            }
        },
        {
            "step": "runPHP",
            "code": "<?php require '/tmp/<plugin-slug>-import.php';"
        }
    ],
    "landingPage": "/wp-admin/export.php"
}
```

### Verify the blueprint

1. Launch the blueprint in WordPress Playground
2. Confirm events, venues, and terms appear correctly in the source plugin's admin UI
3. Navigate to **Tools > Export** and verify you can export the relevant post types

---

## Step 3: Export a WXR File From the Blueprint

With the blueprint running in Playground:

1. Go to **Tools > Export**
2. Select the relevant post types (events, venues, or "All content")
3. Download the `.xml` file
4. Inspect the file to identify:
   - All `<wp:postmeta>` keys present on events and venues
   - Taxonomy terms and their `domain` slugs
   - How venue references are stored (meta key with post ID, taxonomy term)
5. Save this file as `tests/fixtures/wxr/<plugin-slug>.xml`

### What to look for in the WXR

```xml
<!-- Event post meta — identify ALL keys, not just the obvious ones -->
<wp:postmeta>
    <wp:meta_key>_source_start_date</wp:meta_key>
    <wp:meta_value>2025-09-15 09:00:00</wp:meta_value>
</wp:postmeta>

<!-- Taxonomy terms — note the domain attribute -->
<category domain="source_event_cat" nicename="conference">Conference</category>

<!-- Venue reference — how events link to venues -->
<wp:postmeta>
    <wp:meta_key>_source_venue_id</wp:meta_key>
    <wp:meta_value>42</wp:meta_value>
</wp:postmeta>
```

Update your `docs/source-<plugin-slug>.md` with any additional meta keys or data patterns you discover.

---

## Step 4: Create the Adapter Class and Unit Tests

### 4a. Create the adapter class

**File:** `includes/classes/class-<plugin-slug>-adapter.php`

Choose the right interfaces and traits based on how the source plugin stores data:

| Venue storage | Interfaces | Traits |
|---|---|---|
| **CPT-based** (e.g., TEC, EM) | `Source_Adapter`, `Hookable_Adapter` | `Datetime_Helper`, `Venue_Detail_Handler` |
| **Taxonomy-based** (e.g., EO, MEC, EventON) | `Source_Adapter`, `Hookable_Adapter`, `Taxonomy_Venue_Adapter` | `Datetime_Helper`, `Taxonomy_Venue_Handler` |
| **No venue support** (e.g., AIOEC) | `Source_Adapter` | `Datetime_Helper` |

#### Template for CPT-based venues with venue details

```php
<?php
namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Source_Plugin_Adapter' ) ) {
    class Source_Plugin_Adapter implements Hookable_Adapter, Source_Adapter {

        use Datetime_Helper;
        use Venue_Detail_Handler;

        public function get_name(): string {
            return 'Source Plugin Name';
        }

        public function get_event_post_type_map(): array {
            return array(
                'source_event' => 'gatherpress_event',
            );
        }

        public function get_venue_post_type_map(): array {
            return array(
                'source_venue' => 'gatherpress_venue',
            );
        }

        protected function get_venue_detail_meta_map(): array {
            return array(
                '_source_address' => 'address',
                '_source_city'    => 'city',
                '_source_state'   => 'state',
                '_source_zip'     => 'zip',
                '_source_country' => 'country',
                '_source_phone'   => 'phone',
                '_source_website' => 'website',
            );
        }

        public function get_stash_meta_keys(): array {
            return array_merge(
                array(
                    '_source_start_date',
                    '_source_end_date',
                    '_source_timezone',
                    '_source_venue_id',
                ),
                $this->get_venue_detail_meta_keys()
            );
        }

        public function get_pseudopostmetas(): array {
            $callback    = array( $this, 'noop_callback' );
            $pseudometas = array(
                '_source_start_date' => array(
                    'post_type'       => 'gatherpress_event',
                    'import_callback' => $callback,
                ),
                '_source_end_date'   => array(
                    'post_type'       => 'gatherpress_event',
                    'import_callback' => $callback,
                ),
                '_source_timezone'   => array(
                    'post_type'       => 'gatherpress_event',
                    'import_callback' => $callback,
                ),
                '_source_venue_id'   => array(
                    'post_type'       => 'gatherpress_event',
                    'import_callback' => $callback,
                ),
            );

            foreach ( $this->get_venue_detail_meta_keys() as $key ) {
                $pseudometas[ $key ] = array(
                    'post_type'       => 'gatherpress_venue',
                    'import_callback' => $callback,
                );
            }

            return $pseudometas;
        }

        public function can_handle( array $stash ): bool {
            // Use a meta key UNIQUE to this plugin.
            return isset( $stash['_source_start_date'] );
        }

        public function convert_datetimes( int $post_id, array $stash ): void {
            $start    = $stash['_source_start_date'] ?? '';
            $end      = $stash['_source_end_date'] ?? '';
            $timezone = $stash['_source_timezone'] ?? $this->get_default_timezone();

            if ( empty( $start ) ) {
                return;
            }
            if ( empty( $end ) ) {
                $end = $start;
            }

            $this->save_gatherpress_datetimes( $post_id, $start, $end, $timezone );
        }

        public function get_venue_meta_key(): ?string {
            return '_source_venue_id';
        }

        public function get_taxonomy_map(): array {
            return array(
                'source_event_cat' => 'gatherpress_topic',
                'source_event_tag' => 'post_tag',
            );
        }

        public function setup_import_hooks(): void {
            $this->setup_venue_detail_hooks();
        }
    }
}
```

#### Template for taxonomy-based venues

```php
<?php
namespace GatherPressExportImport;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( __NAMESPACE__ . '\Source_Plugin_Adapter' ) ) {
    class Source_Plugin_Adapter implements Hookable_Adapter, Source_Adapter, Taxonomy_Venue_Adapter {

        use Datetime_Helper;
        use Taxonomy_Venue_Handler;

        // ... same as above, but:

        public function get_venue_post_type_map(): array {
            return array(); // No venue CPT
        }

        public function get_venue_taxonomy_slug(): string {
            return 'source_venue_tax'; // The taxonomy slug used for venues
        }

        public function get_venue_meta_key(): ?string {
            return null; // No meta key for venue linking
        }

        public function get_taxonomy_map(): array {
            return array(
                'source_event_cat' => 'gatherpress_topic',
                // Note: venue taxonomy is NOT here — handled by Taxonomy_Venue_Handler
            );
        }

        public function setup_import_hooks(): void {
            $this->setup_taxonomy_venue_hooks();
        }
    }
}
```

### 4b. Register the adapter

Add the adapter to the main plugin file:

```php
// In telex-gatherpress-migration.php, add:
require_once __DIR__ . '/includes/classes/class-<plugin-slug>-adapter.php';
```

The adapter is then registered in `Migration::register_default_adapters()`:

```php
$this->register_adapter( new Source_Plugin_Adapter() );
```

### 4c. Create unit tests

**File:** `tests/php/unit/<PluginName>AdapterTest.php`

Unit tests verify the adapter's configuration methods in isolation — no WordPress database or GatherPress needed.

```php
<?php
use GatherPressExportImport\Source_Plugin_Adapter;
use GatherPressExportImport\Source_Adapter;
use GatherPressExportImport\Hookable_Adapter;

class SourcePluginAdapterTest extends \WP_UnitTestCase {

    private Source_Plugin_Adapter $adapter;

    protected function setUp(): void {
        parent::setUp();
        $this->adapter = new Source_Plugin_Adapter();
    }

    public function test_get_name(): void {
        $this->assertSame( 'Source Plugin Name', $this->adapter->get_name() );
    }

    public function test_implements_interfaces(): void {
        $this->assertInstanceOf( Source_Adapter::class, $this->adapter );
        $this->assertInstanceOf( Hookable_Adapter::class, $this->adapter );
    }

    public function test_get_event_post_type_map(): void {
        $map = $this->adapter->get_event_post_type_map();
        $this->assertArrayHasKey( 'source_event', $map );
        $this->assertSame( 'gatherpress_event', $map['source_event'] );
    }

    public function test_get_venue_post_type_map(): void {
        $map = $this->adapter->get_venue_post_type_map();
        // For CPT venues:
        $this->assertArrayHasKey( 'source_venue', $map );
        // For taxonomy venues:
        // $this->assertEmpty( $map );
    }

    public function test_get_stash_meta_keys(): void {
        $keys = $this->adapter->get_stash_meta_keys();
        $this->assertContains( '_source_start_date', $keys );
        $this->assertContains( '_source_end_date', $keys );
        // ... assert all expected keys
    }

    public function test_get_stash_meta_keys_has_no_duplicates(): void {
        $keys = $this->adapter->get_stash_meta_keys();
        $this->assertSame( count( $keys ), count( array_unique( $keys ) ) );
    }

    public function test_get_pseudopostmetas(): void {
        $pseudometas = $this->adapter->get_pseudopostmetas();
        $this->assertArrayHasKey( '_source_start_date', $pseudometas );
        $this->assertSame( 'gatherpress_event', $pseudometas['_source_start_date']['post_type'] );
        $this->assertIsCallable( $pseudometas['_source_start_date']['import_callback'] );
    }

    public function test_can_handle_with_source_meta(): void {
        $stash = array( '_source_start_date' => '2025-09-15 09:00:00' );
        $this->assertTrue( $this->adapter->can_handle( $stash ) );
    }

    public function test_can_handle_returns_false_for_other_plugins(): void {
        // Test against ALL other adapters' meta keys
        $this->assertFalse( $this->adapter->can_handle( array( '_EventStartDate' => '2025-09-15' ) ) );
        $this->assertFalse( $this->adapter->can_handle( array( '_event_start' => '2025-09-15' ) ) );
        $this->assertFalse( $this->adapter->can_handle( array( 'evcal_srow' => '1725000000' ) ) );
        $this->assertFalse( $this->adapter->can_handle( array() ) );
    }

    public function test_get_venue_meta_key(): void {
        // For CPT venues:
        $this->assertSame( '_source_venue_id', $this->adapter->get_venue_meta_key() );
        // For taxonomy venues:
        // $this->assertNull( $this->adapter->get_venue_meta_key() );
    }

    public function test_get_taxonomy_map(): void {
        $map = $this->adapter->get_taxonomy_map();
        $this->assertArrayHasKey( 'source_event_cat', $map );
        $this->assertSame( 'gatherpress_topic', $map['source_event_cat'] );
    }

    public function test_noop_callback_available(): void {
        $this->adapter->noop_callback();
        $this->assertTrue( true );
    }
}
```

### What to test in unit tests

| Test | Purpose |
|---|---|
| `get_name()` | Correct human-readable name |
| Interface implementation | Implements `Source_Adapter`, `Hookable_Adapter`, optionally `Taxonomy_Venue_Adapter` |
| Event post type map | Correct source → target mapping |
| Venue post type map | Correct mapping (or empty for taxonomy venues) |
| Stash meta keys | All datetime, venue link, venue detail, and additional keys present |
| No duplicate stash keys | `array_unique` check |
| Pseudopostmetas | All registered with correct `post_type` and callable `import_callback` |
| `can_handle()` positive | Returns `true` for the adapter's own meta keys |
| `can_handle()` negative | Returns `false` for every OTHER adapter's distinctive meta key |
| Venue meta key | Correct key (or `null` for taxonomy venues) |
| Taxonomy map | Correct source → target taxonomy mappings |
| Venue taxonomy slug | (For taxonomy-based venues) Correct slug |

---

## Step 5: Add the WXR Fixture and Import Tests

### 5a. Place the WXR fixture

Copy the WXR file exported in Step 3 to:

```
tests/fixtures/wxr/<plugin-slug>.xml
```

If you don't have a real export yet, create an empty placeholder:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wfw="http://wellformedweb.org/CommentAPI/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/"
>
<channel>
    <title><Plugin Name> Demo</title>
    <link>http://example.org</link>
    <description>WXR fixture for <Plugin Name> migration testing.</description>
    <language>en-US</language>
    <wp:wxr_version>1.2</wp:wxr_version>
    <wp:base_site_url>http://example.org</wp:base_site_url>
    <wp:base_blog_url>http://example.org</wp:base_blog_url>
</channel>
</rss>
```

### 5b. Create the WXR import integration tests

**File:** `tests/php/integration/WXRImport<PluginName>Test.php`

These tests run the actual WordPress Importer against the fixture file and verify the full import pipeline.

```php
<?php
namespace GatherPressExportImport\Tests\Integration;

use GatherPressExportImport\Tests\WXRImportHelper;

class WXRImportSourcePluginTest extends TestCase {

    use WXRImportHelper;

    private string $wxr_file;
    private bool $fixture_has_data;

    protected function setUp(): void {
        parent::setUp();
        $this->wxr_file = $this->get_wxr_fixture_path( '<plugin-slug>.xml' );
        $contents = file_get_contents( $this->wxr_file );
        $this->fixture_has_data = ( false !== $contents && false !== strpos( $contents, '<item>' ) );
    }

    private function skip_if_empty_fixture(): void {
        if ( ! $this->fixture_has_data ) {
            $this->markTestSkipped( 'Fixture has no importable data.' );
        }
    }

    // -----------------------------------------------------------------
    // Post type rewriting
    // -----------------------------------------------------------------

    public function test_source_venues_imported_as_gatherpress_venue(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $venues = get_posts( array(
            'post_type'      => 'gatherpress_venue',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );
        $this->assertGreaterThan( 0, count( $venues ) );
    }

    public function test_source_events_imported_as_gatherpress_event(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $events = get_posts( array(
            'post_type'      => 'gatherpress_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );
        $this->assertGreaterThan( 0, count( $events ) );
    }

    public function test_no_source_post_types_remain(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $source_events = get_posts( array(
            'post_type'      => 'source_event',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ) );
        $this->assertCount( 0, $source_events );
    }

    // -----------------------------------------------------------------
    // Datetime conversion
    // -----------------------------------------------------------------

    public function test_events_have_datetime_data(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $events = get_posts( array(
            'post_type'      => 'gatherpress_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        foreach ( $events as $event_post ) {
            $event    = new \GatherPress\Core\Event( $event_post->ID );
            $datetime = $event->get_datetime();
            $this->assertNotEmpty( $datetime['datetime_start'],
                "Event '{$event_post->post_title}' should have datetime data." );
        }
    }

    // -----------------------------------------------------------------
    // Venue linking
    // -----------------------------------------------------------------

    public function test_events_linked_to_venues(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
            $this->markTestSkipped( '_gatherpress_venue taxonomy not registered.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $events = get_posts( array(
            'post_type'      => 'gatherpress_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        $any_linked = false;
        foreach ( $events as $event_post ) {
            $terms = wp_get_object_terms( $event_post->ID, '_gatherpress_venue' );
            if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                $any_linked = true;
                break;
            }
        }
        $this->assertTrue( $any_linked, 'At least one event should be linked to a venue.' );
    }

    // -----------------------------------------------------------------
    // Venue details (for adapters using Venue_Detail_Handler)
    // -----------------------------------------------------------------

    public function test_venue_details_saved_as_venue_information(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $venues = get_posts( array(
            'post_type'      => 'gatherpress_venue',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ) );

        if ( empty( $venues ) ) {
            $this->fail( 'No venue posts created.' );
        }

        $venue_info = get_post_meta( $venues[0]->ID, 'gatherpress_venue_information', true );
        $this->assertNotEmpty( $venue_info );

        $decoded = json_decode( $venue_info, true );
        $this->assertIsArray( $decoded );
        $this->assertArrayHasKey( 'fullAddress', $decoded );
    }

    // -----------------------------------------------------------------
    // Meta key leakage prevention
    // -----------------------------------------------------------------

    public function test_source_meta_keys_not_in_postmeta(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $events = get_posts( array(
            'post_type'      => 'gatherpress_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        $source_keys = array(
            '_source_start_date',
            '_source_end_date',
            '_source_timezone',
            '_source_venue_id',
        );

        foreach ( $events as $event_post ) {
            foreach ( $source_keys as $key ) {
                $this->assertEmpty(
                    get_post_meta( $event_post->ID, $key, true ),
                    "Source key '{$key}' should not leak into postmeta."
                );
            }
        }
    }

    // -----------------------------------------------------------------
    // Taxonomy rewriting
    // -----------------------------------------------------------------

    public function test_taxonomy_terms_rewritten(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        if ( ! taxonomy_exists( 'gatherpress_topic' ) ) {
            $this->markTestSkipped( 'gatherpress_topic not registered.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $topics = get_terms( array(
            'taxonomy'   => 'gatherpress_topic',
            'hide_empty' => false,
        ) );
        $this->assertNotEmpty( $topics, 'At least one gatherpress_topic term should exist.' );
    }

    // -----------------------------------------------------------------
    // Content preservation
    // -----------------------------------------------------------------

    public function test_event_content_preserved(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $events = get_posts( array(
            'post_type'      => 'gatherpress_event',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ) );

        $any_content = false;
        foreach ( $events as $event_post ) {
            if ( ! empty( trim( $event_post->post_content ) ) ) {
                $any_content = true;
                break;
            }
        }
        $this->assertTrue( $any_content, 'At least one event should have content.' );
    }

    // -----------------------------------------------------------------
    // Transient cleanup
    // -----------------------------------------------------------------

    public function test_import_cleans_up_transients(): void {
        if ( ! $this->is_gatherpress_active() ) {
            $this->markTestSkipped( 'GatherPress is not active.' );
        }
        $this->skip_if_empty_fixture();
        $this->import_wxr( $this->wxr_file );

        $this->assertFalse( get_transient( 'gpei_pending_event_ids' ) );
        $this->assertFalse( get_transient( 'gpei_pending_venue_ids' ) );
    }
}
```

### Additional tests for taxonomy-based venue adapters

If the adapter uses the two-pass strategy, add these tests:

```php
public function test_pass1_creates_venue_posts(): void {
    // First import creates venues
    $this->import_wxr( $this->wxr_file );
    $venues = get_posts( array( 'post_type' => 'gatherpress_venue', 'posts_per_page' => -1 ) );
    $this->assertGreaterThan( 0, count( $venues ) );
}

public function test_pass1_skips_events(): void {
    $this->import_wxr( $this->wxr_file );
    $events = get_posts( array( 'post_type' => 'gatherpress_event', 'posts_per_page' => -1 ) );
    $this->assertCount( 0, $events );
}

public function test_two_pass_creates_events_linked_to_venues(): void {
    // Pass 1: venues
    $this->import_wxr( $this->wxr_file );
    // Pass 2: events
    $this->import_wxr( $this->wxr_file );

    $events = get_posts( array( 'post_type' => 'gatherpress_event', 'posts_per_page' => -1 ) );
    $this->assertGreaterThan( 0, count( $events ) );
}
```

---

## Final Checklist

After completing all five steps, verify:

- [ ] `docs/source-<plugin-slug>.md` — Comprehensive documentation
- [ ] `.wordpress-org/blueprints/<plugin-slug>-import.php` — Working demo data script
- [ ] `.wordpress-org/blueprints/blueprint-<plugin-slug>.json` — Playground blueprint
- [ ] `tests/fixtures/wxr/<plugin-slug>.xml` — WXR fixture (real export or placeholder)
- [ ] `includes/classes/class-<plugin-slug>-adapter.php` — Adapter class
- [ ] Adapter registered in `telex-gatherpress-migration.php` (require) and `Migration` (register)
- [ ] `tests/php/unit/<PluginName>AdapterTest.php` — Unit tests passing
- [ ] `tests/php/integration/WXRImport<PluginName>Test.php` — Integration tests passing
- [ ] `README.md` — Updated "Supported Source Plugins" table
- [ ] `class-importer.php` — Updated supported plugins table in the admin UI

### Run the test suite

```bash
# All tests
npm test

# Just the new adapter's tests
npm run test:unit -- --filter=SourcePluginAdapterTest
npm run test:integration -- --filter=WXRImportSourcePluginTest
```

---

## Reference: Existing Adapters

Study these for implementation patterns:

| Adapter | Venue Type | Key Pattern |
|---|---|---|
| `TEC_Adapter` | CPT + Venue_Detail_Handler | Full venue detail conversion |
| `Events_Manager_Adapter` | CPT + Venue_Detail_Handler | Similar to TEC |
| `Event_Organiser_Adapter` | Taxonomy + Taxonomy_Venue_Handler | Two-pass import |
| `MEC_Adapter` | Taxonomy + Taxonomy_Venue_Handler | 12h time components |
| `EventON_Adapter` | Taxonomy + Taxonomy_Venue_Handler | Unix timestamps |
| `AIOEC_Adapter` | None (custom table) | Minimal — post type rewrite only |
