# Developer Documentation

This guide covers setting up a local development environment, running tests, and understanding the project architecture.

---

## Prerequisites

- **Docker** — required by `wp-env`
- **Node.js** (v18+) and **npm**
- **Composer** (v2+) — for PHP dependencies (can also run inside the container)
- **PHP** (8.0+) — for local Composer operations

---

## Getting Started

### 1. Clone the repository

```bash
git clone https://github.com/carstingaxion/gatherpress-export-import.git
cd gatherpress-export-import
```

### 2. Install Node dependencies

```bash
npm install
```

### 3. Start the wp-env environment

```bash
npm run wp-env:start
```

This spins up a WordPress instance with:
- The migration plugin (this repo) activated
- **GatherPress** plugin installed and activated
- **WordPress Importer** plugin installed and activated

The site is available at `http://localhost:8888` (default wp-env port).

### 4. Install Composer dependencies

You can install Composer dependencies **inside** the wp-env container to ensure the correct PHP version is used:

```bash
npm run composer:install
```

Or, if you have Composer installed locally:

```bash
composer install
```

---

## Running Tests

### Test Architecture

The test suite is split into two suites — **unit** and **integration** — defined in a single `phpunit.xml.dist` and sharing one bootstrap file (`tests/php/bootstrap.php`), following the same pattern used by GatherPress References:

| Suite | Location | What it tests |
|---|---|---|
| **Unit** | `tests/php/unit/` | Adapter configs, trait methods, data detection — lightweight tests |
| **Integration** | `tests/php/integration/` | Full WordPress environment: post creation, meta stashing, datetime conversion, venue linking |

The bootstrap file:
- Loads the Composer autoloader
- Locates the WordPress test suite (supports `WP_TESTS_DIR` env var, wp-env default `/wordpress-phpunit`, and local fallback)
- Loads GatherPress, WordPress Importer, and this plugin via `muplugins_loaded`
- Boots the WordPress test environment

### Running tests via wp-env (recommended)

Tests run inside the wp-env Docker container, which has the correct PHP version and the WordPress test library pre-configured:

```bash
# Run all tests (unit + integration)
npm test

# Run only unit tests
npm run test:unit

# Run only integration tests
npm run test:integration
```

You can also run tests directly inside the container:

```bash
npx wp-env run tests-cli --env-cwd='wp-content/plugins/gatherpress-export-import' \
  bash -c 'WP_TESTS_DIR=/wordpress-phpunit vendor/bin/phpunit'
```

### Running tests locally

If you have PHP and Composer installed locally:

```bash
# Unit tests (still require WordPress test lib via bootstrap)
composer test:unit

# Integration tests
composer test:integration

# All tests
composer test
```

If running outside wp-env, set the `WP_TESTS_DIR` environment variable:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
composer test
```

### Running a specific test

```bash
# Via wp-env
npm run test:unit -- --filter=EOAdapterTest
npm run test:integration -- --filter=EOAdapterIntegrationTest

# Via Composer
composer test:unit -- --filter=DatetimeHelperTraitTest
```

### Running a specific test group

Integration tests use PHPUnit `@group` annotations:

```bash
npm run test:integration -- --group=eo-adapter
npm run test:integration -- --group=migration
```

---

## Test Coverage

### Unit Tests

| Test File | Class Under Test | What's Tested |
|---|---|---|
| `EOAdapterTest.php` | `Event_Organiser_Adapter` | Name, post type maps, meta keys, pseudopostmetas, `can_handle()`, taxonomy map, venue taxonomy slug, skippable types, interface implementation |
| `TECAdapterTest.php` | `TEC_Adapter` | Name, post type maps, stash meta keys (event + venue detail), pseudopostmetas, `can_handle()`, venue meta key, taxonomy map, interface implementation |
| `EventsManagerAdapterTest.php` | `Events_Manager_Adapter` | Name, post type maps, stash meta keys (event + venue detail), pseudopostmetas, `can_handle()`, venue meta key, taxonomy map, interface implementation |
| `DatetimeHelperTraitTest.php` | `Datetime_Helper` trait | Venue term slug generation (`_`-prefix convention), default timezone |
| `TaxonomyVenueHandlerTraitTest.php` | `Taxonomy_Venue_Handler` trait | Default venue pass, post data capture, event flagging, term filtering (single, multiple, empty), venue term creation interception (single, successive), skip post type registration, hook idempotency |
| `VenueDetailHandlerTraitTest.php` | `Venue_Detail_Handler` trait | Full address building (all parts, partial, empty, single, whitespace), meta key extraction, venue info saving (success, empty fields, non-venue post, nonexistent post), meta stashing (intercept, pass-through, accumulation, pending ID tracking), hook idempotency |
| `MigrationClassTest.php` | `Migration` | Singleton pattern, adapter registration, merged type maps, taxonomy maps, stash meta keys, post type rewriting, taxonomy rewriting |

### Integration Tests

| Test File | Scope | What's Tested |
|---|---|---|
| `EOAdapterIntegrationTest.php` | EO adapter + GatherPress | Adapter registration, post type rewriting, taxonomy rewriting, meta stashing, datetime conversion, venue shadow term creation, venue linking via `link_venue()`, skip post type registration |
| `VenueDetailHandlerIntegrationTest.php` | Venue detail handler + TEC/EM adapters | TEC venue detail stash-and-process, EM venue detail stash-and-process, partial venue details, multiple venues in single import, non-venue-detail meta pass-through, venue detail meta ignored on event posts, transient cleanup, meta key blocking from postmeta |
| `GatherPressCompatibilityTest.php` | GatherPress API | `Event` class existence, `save_datetimes()` method, `get_datetime()` method, shadow taxonomy, post type registration, parameter format |
| `MigrationIntegrationTest.php` | Main migration class | All event/venue type rewrites (data provider), standard type passthrough, meta stashing for events, pseudopostmeta registration, pending event tracking |
| `WXRImportEOTest.php` | EO adapter end-to-end WXR import | Full two-pass strategy: Pass 1 venue creation, event skipping, skip post cleanup, source meta tracking; Pass 2 event creation, venue linking, meta cleanup |
| `WXRImportTECTest.php` | TEC adapter end-to-end WXR import | Venue/event post type rewrites, datetime conversion, venue linking via ID mapping, taxonomy term rewriting, venue detail meta conversion, partial venue details, meta key blocking |

### WXR Fixture Files

Real WXR export files are stored in `tests/fixtures/wxr/` and used by the WXR import integration tests:

| File | Source Plugin | Contents |
|---|---|---|
| `event-organiser.xml` | Event Organiser | 3 events, 3 venue terms, category and tag terms |
| `tec.xml` | The Events Calendar | 2 venues, 2 events with venue references, category terms |

The `WXRImportHelper` trait (`tests/php/traits/WXRImportHelper.php`) provides reusable methods for programmatically running the WordPress Importer against these fixtures within test methods.

---

## Project Architecture

```
├── plugin.php   # Main plugin file (boot)
├── includes/
│   ├── interfaces/
│   │   ├── interface-source-adapter.php
│   │   ├── interface-hookable-adapter.php
│   │   └── interface-taxonomy-venue-adapter.php
│   ├── traits/
│   │   ├── trait-datetime-helper.php
│   │   ├── trait-taxonomy-venue-handler.php
│   │   └── trait-venue-detail-handler.php
│   └── classes/
│       ├── class-tec-adapter.php
│       ├── class-events-manager-adapter.php
│       ├── class-mec-adapter.php
│       ├── class-eventon-adapter.php
│       ├── class-aioec-adapter.php
│       ├── class-event-organiser-adapter.php
│       ├── class-migration.php
│       └── class-importer.php
├── assets/css/importer.css
├── .wp-env.json                       # wp-env configuration
├── composer.json                      # PHP dependencies
├── package.json                       # npm scripts
├── phpunit.xml.dist                   # PHPUnit config (unit + integration suites)
├── tests/
│   ├── fixtures/
│   │   └── wxr/
│   │       ├── event-organiser.xml    # EO WXR fixture (3 events, 3 venues)
│   │       └── tec.xml                # TEC WXR fixture (2 venues, 2 events)
│   └── php/
│       ├── bootstrap.php              # Unified bootstrap (WP test suite loader)
│       ├── traits/
│       │   └── WXRImportHelper.php    # Reusable WXR import trait for tests
│       ├── unit/
│       │   ├── EOAdapterTest.php
│       │   ├── DatetimeHelperTraitTest.php
│       │   ├── TaxonomyVenueHandlerTraitTest.php
│       │   └── MigrationClassTest.php
│       └── integration/
│           ├── TestCase.php           # Base test case with helpers
│           ├── EOAdapterIntegrationTest.php
│           ├── MigrationIntegrationTest.php
│           ├── WXRImportEOTest.php    # EO two-pass WXR import tests
│           └── WXRImportTECTest.php   # TEC WXR import tests
├── docs/
│   ├── developer/
│   │   └── README.md                 # This file
│   ├── source-*.md                   # Info about each Source plugin
│   └── import-guide.md               # Import guide (architecture, two-pass strategy, troubleshooting)
└── .wordpress-org/
    └── blueprints/                    # Playground blueprints
```

### Adding Tests for a New Adapter

When adding tests for another adapter (e.g., TEC):

1. **Unit test**: Create `tests/php/unit/TECAdapterTest.php` following the pattern in `EOAdapterTest.php`
2. **Integration test**: Create `tests/php/integration/TECAdapterIntegrationTest.php` following the pattern in `EOAdapterIntegrationTest.php`
3. The adapter's class file is already loaded by the unit bootstrap (all adapters are loaded there)

### Gotchas

- **Singleton pattern**: The `Migration` singleton persists across tests in the same process. Tests that modify the instance state may affect later tests. Use `setUp()` / `tearDown()` to manage state.
- **GatherPress dependency**: Integration tests that test datetime conversion or venue linking will be skipped if GatherPress is not active. The `is_gatherpress_active()` helper in the base test case checks for this.
- **wp-env ports**: The default wp-env port is `8888` for the main site and `8889` for the test site. If these ports are in use, wp-env will try alternative ports.
- **Transient cleanup**: Tests that set transients should clean them up in `tearDown()` or at the end of the test to avoid leaking state.

---

## Useful wp-env Commands

```bash
# Start the environment
npm run wp-env:start

# Stop the environment (preserves data)
npm run wp-env:stop

# Destroy the environment (removes all data)
npm run wp-env:destroy

# Run a WP-CLI command
npx wp-env run cli wp option get blogname

# Open a shell in the test container
npx wp-env run tests bash

# View PHP error log
npx wp-env run tests cat /tmp/wordpress/wp-content/debug.log
```
