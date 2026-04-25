# Import Guide

This document explains the general architecture and import flow of the GatherPress Event Migration plugin. For adapter-specific details, see the individual source plugin documentation files in `docs/source-*.md`.

---

## Overview

The plugin intercepts the standard WordPress XML (WXR) import process and transforms third-party event plugin data into GatherPress format on the fly. No custom export files are needed — use your source plugin's normal WordPress export.

The migration is orchestrated by the `Migration` singleton class, which registers hooks into the WordPress Importer at strategic points. Each supported source plugin has a dedicated **adapter** class that encapsulates the knowledge of how that plugin stores its data.

---

## Import Flow

```
Source WXR File
     │
     ▼
┌─────────────────────────────────────────┐
│  wp_import_post_data_raw (priority 5)   │
│  Rewrites post_type to GatherPress CPTs │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│  add_post_metadata (priority 5)         │
│  Stashes third-party meta in transient  │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│  import_end                             │
│  Processes all stashed meta:            │
│  - Adapter::convert_datetimes()         │
│  - Adapter::link_venue()                │
└─────────────────────────────────────────┘
```

---

## Post Type Rewriting

The main migration class hooks into `wp_import_post_data_raw` at priority 5. For each imported post, it checks the `post_type` against two merged maps built from all registered adapters:

- **Event post type map** — e.g., `tribe_events` → `gatherpress_event`, `event` → `gatherpress_event`
- **Venue post type map** — e.g., `tribe_venue` → `gatherpress_venue`, `location` → `gatherpress_venue`

The original source post type is preserved in a `_gpei_source_type` key on the post data array for adapter differentiation.

These maps are filterable via `gpei_event_post_type_map` and `gpei_venue_post_type_map`. See the [Hooks documentation](developer/hooks/Hooks.md) for usage details.

---

## Meta Stashing

Third-party plugins store event data (dates, times, timezones, venue references) in post meta. The migration plugin intercepts these meta keys via the `add_post_metadata` filter at priority 5 and stores them in a per-post transient instead of writing them to `wp_postmeta`.

Each adapter declares which meta keys should be stashed via `get_stash_meta_keys()`. The main migration class merges all keys from all adapters.

At `import_end`, the stashed meta is processed for each event:

1. The appropriate adapter is identified via `can_handle()` — each adapter inspects the stash for its unique meta keys.
2. `convert_datetimes()` is called to transform the source date/time format into GatherPress format and save it via `Event::save_datetimes()`.
3. If the adapter defines a `get_venue_meta_key()`, the old venue ID is resolved to the new ID using the WordPress Importer's `processed_posts` map, and `link_venue()` is called.

---

## Taxonomy Rewriting

Source plugins often use custom taxonomies (e.g., `tribe_events_cat`, `event-category`, `mec_category`). The migration plugin rewrites these to GatherPress equivalents during import.

Each adapter declares a taxonomy map via `get_taxonomy_map()`. The main migration class merges all maps and hooks into:

- **`wp_import_post_terms` (priority 5)** — rewrites the `domain` field in per-post term assignments.
- **`pre_insert_term` (priority 5)** — intercepts top-level term creation from `<wp:term>` entries in the WXR file, creates the term in the target taxonomy, and blocks creation in the source taxonomy.

The taxonomy map is filterable via `gpei_taxonomy_map`. See the inline `@example` annotations in `class-migration.php` for usage details.

---

## Venue Linking

GatherPress uses a shadow taxonomy (`_gatherpress_venue`) to link events to venues. When a `gatherpress_venue` post is created, GatherPress automatically generates a hidden taxonomy term with the slug `_<venue-post-slug>`. Events reference venues by being assigned this taxonomy term.

### Plugins with venue CPTs (e.g., TEC, Events Manager)

For plugins that store venues as a custom post type (e.g., `tribe_venue`, `location`), the import flow is:

1. Venue posts are imported first and rewritten to `gatherpress_venue`.
2. GatherPress creates shadow taxonomy terms for each venue.
3. Events are imported next. The old venue ID (from a meta key like `_EventVenueID`) is resolved to the new venue ID via the WordPress Importer's `processed_posts` map.
4. The adapter's `link_venue()` method assigns the corresponding shadow taxonomy term to the event.

**Import order matters:** venues must be imported before events. If your WXR file contains both, ensure venues appear first in the XML.

### Venue Detail Conversion

Plugins that store venue details (address, city, state, ZIP, country, phone, website) as individual post meta keys on their venue CPT can use the shared `Venue_Detail_Handler` trait to automatically convert these into GatherPress's `gatherpress_venue_information` JSON format.

The trait works by:

1. **Stashing** venue detail meta keys during import (via the `add_post_metadata` filter at priority 4).
2. **Processing** all stashed venue meta at `import_end` — mapping each source key to a component type (`address`, `city`, `state`, `zip`, `country`, `phone`, `website`, `latitude`, `longitude`), assembling a full address string, and saving the JSON via `save_venue_information()`.

Adapters using the trait must implement `get_venue_detail_meta_map()` to declare the mapping. For example, TEC maps `_VenueAddress` → `address`, `_VenueCity` → `city`, etc. Events Manager maps `_location_address` → `address`, `_location_town` → `city`, etc.

To add venue detail support to a new adapter:

```php
class My_Adapter implements Source_Adapter, Hookable_Adapter {
    use Datetime_Helper;
    use Venue_Detail_Handler;

    protected function get_venue_detail_meta_map(): array {
        return array(
            '_my_venue_address' => 'address',
            '_my_venue_city'    => 'city',
            '_my_venue_phone'   => 'phone',
        );
    }

    public function setup_import_hooks(): void {
        $this->setup_venue_detail_hooks();
    }

    // ... rest of Source_Adapter methods ...
}
```

### Plugins with taxonomy-based venues (two-pass import)

Plugins that store venues as taxonomy terms rather than posts require a **two-pass import**. This is handled by the shared `Taxonomy_Venue_Handler` trait and `Taxonomy_Venue_Adapter` interface.

Three adapters currently use this strategy:

| Adapter | Venue Taxonomy | Event CPT |
|---|---|---|
| **Event Organiser** | `event-venue` | `event` |
| **Modern Events Calendar** | `mec_location` | `mec-events` |
| **EventON** | `event_location` | `ajde_events` |

#### Why two passes?

Because the WordPress Importer processes posts before taxonomy terms in the WXR file, and because GatherPress needs a real `gatherpress_venue` post to exist before its shadow term is available, the import must happen in two passes over the **same WXR file**.

#### Pass 1 — Venue creation

1. Upload the WXR file via **Tools > Import > GatherPress Event Migration**.
2. The plugin detects venue taxonomy terms attached to event posts (via the `wp_import_post_terms` filter) and in top-level `<wp:term>` entries (via the `pre_insert_term` filter).
3. For each unique venue term, a new `gatherpress_venue` post is created with the term's name and slug.
4. GatherPress automatically creates a shadow taxonomy term in `_gatherpress_venue` for each new venue post (slug: `_<post-slug>`).
5. A temporary post meta key `_gpei_source_venue_term_slug` is stored on each created venue post for fallback lookups.
6. **Events are silently skipped** — they are redirected to a temporary non-public post type (`_gpei_skip`) so the WordPress Importer does not report errors. These throwaway posts are automatically deleted at `import_end`.

#### Pass 2 — Event import

1. Upload the **same WXR file** again.
2. The plugin detects that `gatherpress_venue` posts already exist for the venue slugs → switches to event import mode.
3. Events are imported normally with post type rewriting, datetime conversion, etc.
4. Venue linking is **deferred** until `import_end` because the WordPress Importer processes per-post taxonomy terms after calling `wp_insert_post()`. The plugin collects event–venue slug mappings during term processing and resolves them all at the end.
5. For each event, the plugin looks up the matching `gatherpress_venue` post by slug (or by the `_gpei_source_venue_term_slug` meta as a fallback), then assigns the shadow taxonomy term to the event.
6. After a successful venue link, the temporary `_gpei_source_venue_term_slug` meta is cleaned up.

#### Pass detection logic

The plugin determines which pass it is in by checking the **first venue term encountered** during import:

- If a `gatherpress_venue` post with a matching slug **already exists** → Pass 2 (event import mode)
- If no matching venue post exists → Pass 1 (venue creation mode)

This detection runs once per import and applies to the entire run. Do not mix venue creation and event import in a single pass.

#### Adding two-pass support to a new adapter

```php
class My_New_Adapter implements Source_Adapter, Hookable_Adapter, Taxonomy_Venue_Adapter {

    use Datetime_Helper;
    use Taxonomy_Venue_Handler;

    public function get_venue_taxonomy_slug(): string {
        return 'my-venue-taxonomy';
    }

    public function get_skippable_event_post_types(): array {
        return array( 'my_event' );
    }

    public function setup_import_hooks(): void {
        $this->setup_taxonomy_venue_hooks();
    }

    // ... rest of the Source_Adapter methods ...
}
```

The adapter only needs to declare its venue taxonomy slug and skippable post types — all two-pass logic is handled automatically by the trait.

---

## Taxonomy Venue Handler Internals

The `Taxonomy_Venue_Handler` trait encapsulates all two-pass import mechanics. This section documents the hook registration, event skipping, and deferred venue linking strategies shared by every adapter that uses taxonomy-based venues (Event Organiser, MEC, EventON).

### Hook Registration

When an adapter calls `setup_taxonomy_venue_hooks()`, the trait registers the following WordPress hooks:

| Hook | Priority | Callback | Purpose |
|---|---|---|---|
| `wp_import_post_data_raw` | 2 | `tvh_capture_current_post_data()` | Records the current post title for context before any other processing |
| `wp_import_post_data_raw` | 3 | `tvh_maybe_flag_events_on_venue_pass()` | Redirects events to the skip post type during Pass 1 |
| `pre_insert_term` | 3 | `tvh_intercept_venue_term_creation()` | Intercepts top-level venue term creation from `<wp:term>` entries |
| `wp_import_post_terms` | 4 | `tvh_filter_venue_terms()` | Intercepts per-post venue terms; creates venues (Pass 1) or records slugs for deferred linking (Pass 2) |
| `save_post_gatherpress_event` | 1 | `tvh_track_saved_post_id()` | Records the last saved event post ID so terms can be associated |
| `import_end` | 10 | `tvh_process_deferred_venue_links()` | Links events to venues and cleans up skip posts |

All priorities are chosen to run **before** the main migration class's hooks (which operate at priority 5 and above), ensuring adapter-specific logic takes precedence.

### Event Skipping Mechanism

During Pass 1, events must not be imported because the venue shadow taxonomy terms are not yet ready. The trait handles this by:

1. **Hooking into `wp_import_post_data_raw` at priority 3** — before the main migration class rewrites the post type at priority 5.
2. **Changing the `post_type`** of the adapter's source event posts (e.g., `event`, `mec-events`, `ajde_events`) to `_gpei_skip`, a registered but non-public post type.
3. Because `post_type_exists( '_gpei_skip' )` returns `true`, the WordPress Importer does not report "Invalid post type" errors.
4. **At `import_end`**, all posts of type `_gpei_skip` are permanently deleted via `wp_delete_post( $id, true )`, and their entries are removed from the WordPress Importer's `processed_posts` map so a subsequent pass can re-import them.

### Deferred Venue Linking

Venue linking cannot happen during `save_post_gatherpress_event` because the WordPress Importer processes per-post taxonomy term assignments _after_ calling `wp_insert_post()`. The trait solves this with a deferred linking strategy:

1. `save_post_gatherpress_event` fires → the trait records the post ID, but venue terms have not been processed yet.
2. `wp_import_post_terms` fires → the trait's `tvh_filter_venue_terms()` intercepts venue taxonomy terms, records the event-to-venue-slug mappings in a deferred queue, and removes venue terms from the assignment list.
3. **At `import_end`** → `tvh_process_deferred_venue_links()` iterates through all collected event–venue mappings and performs the actual linking by looking up the `gatherpress_venue` post by slug (or by the `_gpei_source_venue_term_slug` meta as a fallback) and assigning the shadow taxonomy term.

### Pass Detection

The trait determines which pass it is in by checking the **first venue term encountered** during import:

- If a `gatherpress_venue` post with a matching slug **already exists** → Pass 2 (event import mode).
- If no matching venue post exists → Pass 1 (venue creation mode).

An early detection path also checks for the presence of `_gpei_source_venue_term_slug` meta on any `gatherpress_venue` post, which indicates Pass 1 has previously completed.

This detection runs once per import and applies to the entire run. Do not mix venue creation and event import in a single pass.

---

## The `_gpei_source_venue_term_slug` Meta Key

### Purpose

This is a **temporary post meta** stored on `gatherpress_venue` posts created during Pass 1 of a two-pass import. It records the original venue taxonomy term slug that was the source for that venue post.

### Why it exists

During Pass 2, the plugin needs to find which `gatherpress_venue` post corresponds to a given venue term slug. The primary lookup is by `post_name` (slug). However, WordPress may modify the slug during `wp_insert_post()` to avoid collisions (e.g., appending `-2`). The `_gpei_source_venue_term_slug` meta serves as a **fallback lookup mechanism**.

### Lifecycle

| Phase | Action |
|---|---|
| **Pass 1** (venue creation) | Meta is **created** via `update_post_meta()` on the new venue post |
| **Pass 2** (event import) | Meta is **read** during venue lookup as a fallback |
| **After successful link** | Meta is **deleted** from the venue post — it is no longer needed |

If the meta persists after import, it indicates that the venue was never successfully linked to any event, which may warrant manual review.

---

## Data Mapping Summary

| Source Data | GatherPress Target | Method |
|---|---|---|
| Event post type | `gatherpress_event` | Post type rewrite via `wp_import_post_data_raw` |
| Venue post type | `gatherpress_venue` | Post type rewrite via `wp_import_post_data_raw` |
| Start/end datetimes | `gp_event_extended` table | Meta stash → adapter `convert_datetimes()` → `Event::save_datetimes()` |
| Timezone | `gp_event_extended` table | Included in datetime conversion; falls back to site timezone |
| Venue reference | `_gatherpress_venue` shadow taxonomy | ID mapping → `link_venue()` → `wp_set_object_terms()` |
| Event categories | `gatherpress_topic` | Taxonomy rewrite via `wp_import_post_terms` and `pre_insert_term` |
| Event tags | `post_tag` | Taxonomy rewrite (or automatic if already `post_tag`) |
| Title, content, featured image | Standard post fields | Automatic (WordPress Importer) |

---

## Pseudopostmeta Integration

The plugin integrates with GatherPress's pseudopostmeta system via the `gatherpress_pseudopostmetas` filter. Each adapter registers its meta keys as pseudopostmeta entries with no-op callbacks. The actual processing is handled by the stash mechanism described above — the pseudopostmeta registration ensures GatherPress recognises these keys during its own import/export flow.

---

## Recommended Import Sequence

1. **Export venues first** — On your source site, go to Tools > Export and export only the venue post type.
2. **Import venues** — Upload the venues WXR file via the WordPress Importer.
3. **Export events** — Back on the source site, export the event post type.
4. **Import events** — Upload the events WXR file. Post types, datetimes, and venue links are converted automatically.
5. **Flush permalinks** — Visit Settings > Permalinks and click Save. Deactivate the source event plugin first if still active.

For plugins that use taxonomy-based venues (e.g., Event Organiser, MEC, EventON), import the same WXR file twice instead of splitting venues and events. See the adapter-specific `docs/source-*.md` files for details.

---

## Troubleshooting Two-Pass Imports

### Events were skipped on both passes

The pass detection checks the first venue term. If the first venue slug coincidentally matches an existing `gatherpress_venue` post from a different source, the plugin may incorrectly enter Pass 2 on the first import. Delete any conflicting venue posts and re-import.

### Venues created but not linked to events

Common causes:

- **GatherPress did not create the shadow taxonomy term.** Verify that GatherPress is active and the `_gatherpress_venue` taxonomy is registered. Try editing and saving the venue post in the admin to trigger shadow term creation.
- **The venue post slug was modified by WordPress.** The `_gpei_source_venue_term_slug` fallback should handle this. If it does not, check whether the meta key exists on the venue post.
- **The shadow term slug does not follow the expected convention.** The plugin expects `_<post-slug>`. If GatherPress's convention changes in a future version, the lookup may fail.

### `_gpei_source_venue_term_slug` meta still present after import

This meta is automatically cleaned up after successful venue linking. If it persists, the venue was never linked to an event during Pass 2. Possible reasons:

- Pass 2 was never run (only one import was performed).
- The event that references this venue was not included in the WXR file.
- The linking failed due to a missing shadow taxonomy term.

You can safely delete the meta manually, or re-run Pass 2 by importing the same WXR file again.

### "Invalid post type" errors during Pass 1

This should not happen with the current implementation. The `_gpei_skip` post type is registered before the import begins. If you see this error, ensure the plugin is active and that no other code is interfering with post type registration during the import.

---

## Important Caveats

- **Shared post type slugs** — Events Manager and Event Organiser both use `event`. The plugin distinguishes them by inspecting meta keys via each adapter's `can_handle()` method. Import data from only one source plugin at a time.
- **Recurring events** — GatherPress treats each occurrence as a separate event. Recurrence rules are not converted.
- **Duplicate prevention** — Importing the same file twice may create duplicates. Always import into a clean environment or verify existing data first.
- **Shortcodes in content** — Source plugin shortcodes will appear as raw text. Review imported event content and clean up as needed.
- **Timezone mismatches** — Some plugins store local time, others UTC. The converter uses the source timezone when available, falling back to your site timezone.

---

## Extending for Custom Plugins

To add support for an additional event plugin, create a class implementing the `Source_Adapter` interface and register it:

```php
$migration = Migration::get_instance();
$migration->register_adapter( new My_Custom_Adapter() );
```

If the adapter needs to register its own import hooks, also implement `Hookable_Adapter`. If the source plugin stores venues as taxonomy terms, implement `Taxonomy_Venue_Adapter` and use the `Taxonomy_Venue_Handler` trait.

See the adapter source files and `docs/source-*.md` files for implementation examples.
