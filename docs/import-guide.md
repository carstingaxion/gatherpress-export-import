# Import Guide

This document explains the general architecture and import flow of the GatherPress Event Migration plugin. For adapter-specific details, see the individual adapter documentation (e.g., [`event-organiser.md`](event-organiser.md)).

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

These maps are filterable:

```php
add_filter( 'gpei_event_post_type_map', function ( $map ) {
    $map['my_custom_event'] = 'gatherpress_event';
    return $map;
} );

add_filter( 'gpei_venue_post_type_map', function ( $map ) {
    $map['my_custom_venue'] = 'gatherpress_venue';
    return $map;
} );
```

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

The taxonomy map is filterable:

```php
add_filter( 'gpei_taxonomy_map', function ( $map ) {
    $map['my_custom_category'] = 'gatherpress_topic';
    return $map;
} );
```

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

### Plugins with taxonomy-based venues (e.g., Event Organiser)

For plugins that store venues as taxonomy terms rather than posts, a **two-pass import** is required. This is handled by the shared `Taxonomy_Venue_Handler` trait and `Taxonomy_Venue_Adapter` interface. See the [Event Organiser documentation](event-organiser.md) for a detailed walkthrough.

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

For plugins that use taxonomy-based venues (e.g., Event Organiser), import the same WXR file twice instead of splitting venues and events. See the adapter-specific documentation for details.

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

See the adapter source files and [`event-organiser.md`](event-organiser.md) for implementation examples.
