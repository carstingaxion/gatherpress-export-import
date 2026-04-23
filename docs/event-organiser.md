# Event Organiser — Import Guide

This document explains how the GatherPress Event Migration plugin handles data from **Event Organiser** by Stephen Harris.

For general import architecture, data mapping, and the overall import flow, see the [Import Guide](import-guide.md).

---

## Why Two Passes?

Event Organiser stores venues as **taxonomy terms** (`event-venue`) rather than as a dedicated custom post type. GatherPress uses `gatherpress_venue` **posts** which are automatically "shadowed" into a hidden `_gatherpress_venue` taxonomy. Events reference venues by being assigned one of these shadow taxonomy terms.

Because the WordPress Importer processes posts before taxonomy terms in the WXR file, and because GatherPress needs a real `gatherpress_venue` post to exist before its shadow term is available, the import must happen in two passes over the **same WXR file**.

---

## Import Sequence

### Pass 1 — Venue Creation

1. Upload the WXR file exported from Event Organiser via **Tools > Import > GatherPress Event Migration**.
2. The plugin detects `event-venue` taxonomy terms attached to event posts (via the `wp_import_post_terms` filter) and in top-level `<wp:term>` entries (via the `pre_insert_term` filter).
3. For each unique `event-venue` term, a new `gatherpress_venue` post is created with:
   - `post_title` = the venue term name (e.g., "University Lecture Theatre")
   - `post_name` = the venue term slug (e.g., `university-lecture-theatre`)
4. GatherPress automatically creates a shadow taxonomy term in `_gatherpress_venue` for each new venue post. The shadow term slug follows the convention `_<post-slug>` (e.g., `_university-lecture-theatre`).
5. A temporary post meta key `_gpei_source_venue_term_slug` is stored on each created venue post, recording the original `event-venue` term slug for fallback lookups.
6. **Events are silently skipped** in this pass — they are redirected to a temporary non-public post type (`_gpei_skip`) so the WordPress Importer does not report errors. These throwaway posts are automatically deleted at `import_end`.

### Pass 2 — Event Import

1. Upload the **same WXR file** again.
2. The plugin detects that `gatherpress_venue` posts already exist for the venue slugs → switches to **event import mode**.
3. Events are imported normally:
   - `post_type` is rewritten from `event` to `gatherpress_event`
   - Schedule meta keys (`_eventorganiser_schedule_start_datetime`, `_eventorganiser_schedule_end_datetime`, etc.) are intercepted, stashed in a transient, and converted into GatherPress datetime format via `Event::save_datetimes()`
4. Venue linking is **deferred** until `import_end` because the WordPress Importer processes per-post taxonomy terms _after_ calling `wp_insert_post()`. The plugin collects event–venue slug mappings during term processing and resolves them all at the end.
5. For each event, the plugin looks up the matching `gatherpress_venue` post by slug (or by the `_gpei_source_venue_term_slug` meta as a fallback), generates the expected shadow term slug using the `_<post-slug>` convention, and assigns it to the event via `wp_set_object_terms()`.
6. After a successful venue link, the temporary `_gpei_source_venue_term_slug` meta is cleaned up from the venue post.

---

## The `_gpei_source_venue_term_slug` Meta Key

### Purpose

This is a **temporary post meta** stored on `gatherpress_venue` posts created during Pass 1. It records the original `event-venue` taxonomy term slug that was the source for that venue post.

### Why It Exists

During Pass 2, the plugin needs to find which `gatherpress_venue` post corresponds to a given `event-venue` term slug. The primary lookup is by `post_name` (slug). However, WordPress may modify the slug during `wp_insert_post()` to avoid collisions (e.g., appending `-2`). The `_gpei_source_venue_term_slug` meta serves as a **fallback lookup mechanism**: if the primary slug-based query returns no results, the plugin queries by this meta key.

### Lifecycle

| Phase | Action |
|---|---|
| **Pass 1** (venue creation) | Meta is **created** via `update_post_meta()` on the new venue post |
| **Pass 2** (event import) | Meta is **read** during venue lookup as a fallback |
| **After successful link** | Meta is **deleted** from the venue post — it is no longer needed |

If the meta persists after import, it indicates that the venue was never successfully linked to any event, which may warrant manual review.

---

## Pass Detection Logic

The plugin determines which pass it is in by checking the **first `event-venue` term encountered** during import:

- If a `gatherpress_venue` post with a matching slug **already exists** → **Pass 2** (event import mode)
- If no matching venue post exists → **Pass 1** (venue creation mode)

This detection runs once per import and applies to the entire run. Do not mix venue creation and event import in a single pass.

**Before detection runs:** If events are processed before any `event-venue` term is encountered (which is common, since the WordPress Importer processes posts before terms), the plugin defaults to Pass 1 behaviour — events are redirected to the skip post type as a precaution.

---

## Event Skipping Mechanism

During Pass 1, events must not be imported because the venue shadow taxonomy terms are not yet ready. The plugin handles this by:

1. **Hooking into `wp_import_post_data_raw` at priority 3** — before the main migration class rewrites the post type at priority 5.
2. **Changing the `post_type`** of Event Organiser events (originally `event`) to `_gpei_skip`, a registered but non-public post type.
3. Because `post_type_exists( '_gpei_skip' )` returns `true`, the WordPress Importer does not report "Invalid post type" errors.
4. **At `import_end`**, all posts of type `_gpei_skip` are permanently deleted via `wp_delete_post( $id, true )`.

This ensures events are silently skipped without polluting the database or generating error messages in the import log.

---

## Deferred Venue Linking

Venue linking cannot happen during `save_post_gatherpress_event` because the WordPress Importer processes per-post taxonomy term assignments _after_ calling `wp_insert_post()`. This means:

1. `save_post_gatherpress_event` fires → but `event-venue` terms have not been processed yet.
2. `wp_import_post_terms` fires → the plugin's `filter_event_venue_terms()` intercepts `event-venue` terms, records the slugs, and removes them from the assignment list.
3. **At `import_end`** → `process_deferred_venue_links()` iterates through all collected event–venue mappings and performs the actual linking.

The linking process:
1. Find the `gatherpress_venue` post by slug (or by `_gpei_source_venue_term_slug` meta as fallback).
2. Generate the expected shadow term slug: `_<venue-post-slug>` (matching GatherPress's own `get_venue_term_slug()` convention).
3. Look up the shadow term in the `_gatherpress_venue` taxonomy.
4. Assign the term to the event via `wp_set_object_terms()`.

---

## Taxonomy Mapping

| Event Organiser Taxonomy | GatherPress / WordPress Target | Handling |
|---|---|---|
| `event-category` | `gatherpress_topic` | Automatic rewrite via taxonomy map |
| `event-tag` | `post_tag` | Automatic rewrite via taxonomy map |
| `event-venue` | `gatherpress_venue` posts + `_gatherpress_venue` shadow taxonomy | Two-pass strategy (this document) |

Note: `event-venue` is deliberately **excluded** from the adapter's `get_taxonomy_map()` because it requires special two-pass handling rather than simple taxonomy rewriting.

---

## Troubleshooting

### Events were skipped on both passes

The pass detection checks the first `event-venue` term. If the first venue slug coincidentally matches an existing `gatherpress_venue` post from a different source, the plugin may incorrectly enter Pass 2 on the first import. Delete any conflicting venue posts and re-import.

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

## Hook Registration Summary

The Event Organiser adapter registers the following hooks via `setup_import_hooks()` (delegated to the shared `Taxonomy_Venue_Handler` trait):

| Hook | Priority | Callback | Purpose |
|---|---|---|---|
| `wp_import_post_data_raw` | 2 | `tvh_capture_current_post_data()` | Records the current post title for context |
| `wp_import_post_data_raw` | 3 | `tvh_maybe_flag_events_on_venue_pass()` | Redirects events to skip post type during Pass 1 |
| `pre_insert_term` | 3 | `tvh_intercept_venue_term_creation()` | Intercepts top-level `event-venue` term creation |
| `wp_import_post_terms` | 4 | `tvh_filter_venue_terms()` | Intercepts per-post `event-venue` terms, creates venues (Pass 1) or records slugs (Pass 2) |
| `save_post_gatherpress_event` | 1 | `tvh_track_saved_post_id()` | Records the last saved event post ID for term association |
| `import_end` | 10 | `tvh_process_deferred_venue_links()` | Links events to venues and cleans up skip posts |

All priorities are chosen to run **before** the main migration class's hooks (which operate at priority 5 and above), ensuring adapter-specific logic takes precedence.

---

## Two-Pass Architecture (Reusable)

The two-pass import logic is not specific to Event Organiser — it lives in shared components that any adapter can reuse. Three adapters currently use this strategy:

| Adapter | Venue Taxonomy | Event CPT |
|---|---|---|
| **Event Organiser** | `event-venue` | `event` |
| **Modern Events Calendar** | `mec_location` | `mec-events` |
| **EventON** | `event_location` | `ajde_events` |

### `Taxonomy_Venue_Adapter` (interface)

Defines the contract for adapters that store venues as taxonomy terms:

| Method | Purpose |
|---|---|
| `get_venue_taxonomy_slug(): string` | Returns the source venue taxonomy slug (e.g., `event-venue`) |
| `get_skippable_event_post_types(): string[]` | Returns the source event post type slugs to skip during Pass 1 |

### `Taxonomy_Venue_Handler` (trait)

Provides the full two-pass implementation:

- Skip post type registration and cleanup
- Pass detection (early and standard)
- Event skipping during Pass 1
- Venue post creation from taxonomy terms
- Deferred venue linking at `import_end`
- All WordPress hook registration via `setup_taxonomy_venue_hooks()`

### Adding two-pass support to a new adapter

To add two-pass taxonomy venue support to another adapter:

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
