# GatherPress Export Import

**Intercepts WordPress XML imports from third-party event plugins and transforms them into GatherPress data automatically.**

---

## Overview

This plugin hooks into the standard WordPress Importer to transparently convert event data from third-party event plugins into GatherPress events and venues.

**No custom WXR files needed.** Simply export from your source event plugin using WordPress's built-in Tools > Export, then import the file normally via Tools > Import. The plugin automatically:

1. **Rewrites post types** — third-party event and venue post types become `gatherpress_event` and `gatherpress_venue`.
2. **Converts date/time data** — source plugin meta keys are intercepted and converted into GatherPress datetime format, then saved to the `gp_event_extended` table via `Event::save_datetimes()`.
3. **Links venues** — venue references are resolved using the WordPress Importer's old-to-new ID mapping and linked via GatherPress's `_gatherpress_venue` shadow taxonomy.

---

## Supported Source Plugins

| Source Plugin | Event CPT | Venue Handling | Date Format |
|---|---|---|---|
| **The Events Calendar** (StellarWP) | `tribe_events` | `tribe_venue` → `gatherpress_venue` | `Y-m-d H:i:s` + timezone string |
| **Events Manager** | `event` | `location` → `gatherpress_venue` | `Y-m-d H:i:s` + timezone string |
| **Modern Events Calendar** (Webnus) | `mec-events` | `mec_location` taxonomy | `Y-m-d` + separate h/m/ampm fields |
| **All-in-One Event Calendar** | `ai1ec_event` | N/A (custom table) | Custom table (manual mapping needed) |
| **EventON** | `ajde_events` | `event_location` taxonomy | Unix timestamps (`evcal_srow` / `evcal_erow`) |
| **Event Organiser** (Stephen Harris) | `event` | `event-venue` taxonomy | `Y-m-d H:i:s` (`_eventorganiser_schedule_*`) |


### WP Core Export Compatibility Matrix

The table below shows which event data is available through standard WordPress XML (WXR) exports per source plugin. The **GP** column indicates whether GatherPress supports the feature natively.

| Data Type | GP | TEC | Events Manager | MEC | AIOEC | EventON | Event Organiser |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Event title & content** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Featured image** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Start/end datetimes** | ✅ | ✅ | ✅ | ✅ | ❌ ² | ✅ | ✅ |
| **Timezone** | ✅ | ✅ | ✅ | ❌ ³ | ❌ ² | ❌ ³ | ❌ ⁴ |
| **Venue name** | ✅ | ✅ | ✅ | ✅ ⁵ | ❌ ² | ✅ ⁵ | ✅ ⁵ |
| **Venue address/details** | ✅ | ✅ | ✅ | ❌ ⁶ | ❌ ² | ❌ ⁶ | ❌ ⁶ |
| **Venue coordinates** | ✅ | ⚠️ ⁷ | ⚠️ ⁷ | ❌ ⁶ | ❌ ² | ❌ ⁶ | ❌ ⁶ |
| **Venue–event link** | ✅ | ✅ | ⚠️ ⁸ | ✅ ⁵ | ❌ ² | ✅ ⁵ | ✅ ⁵ |
| **Event categories** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Event tags** | ❌ ⁹ | ✅ | ✅ | ⚠️ ¹⁰ | ✅ | ❌ | ✅ |
| **Organizer** | ❌ ¹¹ | ✅ | ⚠️ | ⚠️ | ❌ ² | ⚠️ | ❌ |
| **Recurrence rules** | ❌ ¹² | ❌ | ❌ | ❌ | ❌ ² | ❌ | ❌ |
| **RSVP / Tickets** | ✅ ¹³ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

**Legend:** ✅ Fully available — ⚠️ Partially available or requires extra handling — ❌ Not available via core export

**Footnotes:**

¹ Uses the two-pass import strategy: first import creates `gatherpress_venue` posts from taxonomy terms, second import creates events and links them to venues.

² AIOEC stores nearly all event data (datetimes, venue, recurrence) in a custom database table (`ai1ec_events`). Standard WXR exports contain only the post title and content.

³ Timezone is not stored in a dedicated meta field; the site timezone is used as a fallback during conversion.

⁴ Event Organiser stores datetimes in site-local time; the site timezone is used during conversion.

⁵ Venue names are available as taxonomy term names in the WXR export and are converted to `gatherpress_venue` posts via the two-pass import strategy.

⁶ Venue address, phone, website, and coordinates are stored as taxonomy term meta. WordPress core WXR exports do **not** include term meta, so this data must be re-entered manually or exported separately.

⁷ Coordinates are partially available via venue post meta but may require additional mapping depending on source plugin version.

⁸ Events Manager uses `_location_id` which requires ID mapping resolution during import.

⁹ GatherPress does not register `post_tag` for the `gatherpress_event` post type. Tags from source plugins are imported but will not appear in the GatherPress event editor unless `post_tag` support is added separately.

¹⁰ MEC uses `mec_label` as a tag-like taxonomy, which is mapped to `post_tag` during import.

¹¹ GatherPress does not currently have a dedicated organizer entity. Organizer data from source plugins cannot be mapped to a GatherPress equivalent and is not imported.

¹² GatherPress treats each event occurrence as a separate post. There is no recurrence rule system; each occurrence must be its own event.

¹³ GatherPress has a built-in RSVP system, but no source plugin's RSVP or ticket data is convertible via WXR exports. RSVP data must be managed natively in GatherPress after migration.

> [!TIP]
> You can add support for additional plugins using the `gpei_event_post_type_map`, `gpei_venue_post_type_map`, `gpei_taxonomy_map`, and `gatherpress_pseudopostmetas` filters. See the [Hooks documentation](docs/developer/hooks/Hooks.md) for usage details.

---

## Installation

1. Upload the plugin files to `/wp-content/plugins/gatherpress-export-import`, or install through the WordPress plugins screen.
2. Activate the plugin through **Plugins** in the WordPress admin.
3. Ensure **GatherPress** and the **WordPress Importer** plugin are also active.
4. Export your event data from the source plugin via **Tools > Export**.
5. Import the WXR file via **Tools > Import > GatherPress Event Migration**. The plugin handles the rest.

---

## How It Works

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
│  Adapter::convert_datetimes()           │
│  Converts to GatherPress format         │
│  Saves via Event::save_datetimes()      │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│  Adapter::link_venue()                  │
│  Resolves old→new venue ID mapping      │
└─────────────────────────────────────────┘
```

---

## Data Mapping

| Source Data | GatherPress Target | Handling |
|---|---|---|
| Event title and content | `gatherpress_event` post | Automatic (post type rewrite) |
| Start/end date, time, timezone | `gp_event_extended` table | Pseudopostmeta composite callback |
| Venue / location | `gatherpress_venue` post + `_gatherpress_venue` taxonomy | Post type rewrite + ID mapping + shadow taxonomy term assignment |
| Categories / tags | `gatherpress_topic` taxonomy | May need manual re-assignment |
| Featured image | Post thumbnail | Automatic (standard WXR) |

---

## Demo Playground Blueprints

Test the migration workflow by launching a WordPress Playground instance pre-loaded with demo data for each supported source plugin. Each blueprint creates sample events, venues, and plugin-specific taxonomy terms.

### Import Target

| Environment | Description | Link |
|---|---|---|
| **GatherPress Migration** | Ready to receive imports | [![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint.json) |

### Source Plugin Demos

Each lands on **Tools > Export** so you can export demo data and test the migration.

| Source Plugin | Link |
|---|---|
| **The Events Calendar** (StellarWP) | [![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-tec.json) |
| **Events Manager** | [![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-events-manager.json) |
| **Modern Events Calendar** (Webnus) | [![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-mec.json) |
| **EventON** | [![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-eventon.json) |
| **All-in-One Event Calendar** | [![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-aioec.json) |
| **Event Organiser** (Stephen Harris) | [![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-event-organiser.json) |

---

## Important Notes

> [!WARNING]
> **Venues must be imported BEFORE events.** GatherPress uses a shadow taxonomy (`_gatherpress_venue`) to link events to venues. When a `gatherpress_venue` post is created, GatherPress automatically generates a corresponding hidden taxonomy term. Events are then linked to venues by assigning this taxonomy term. If venues haven't been imported yet, the shadow taxonomy terms won't exist and venue linking will fail.

### Recommended Import Sequence

1. **Export venues first** — On your source site, go to Tools > Export and export only the venue post type.
2. **Import venues** — Upload the venues WXR file via the WordPress Importer.
3. **Export events** — Back on the source site, export the event post type.
4. **Import events** — Upload the events WXR file. Post types, datetimes, and venue links are converted automatically.
5. **Flush permalinks** — Visit Settings > Permalinks and click Save. Deactivate the source event plugin first if still active.

### Known Limitations

- **Timezone mismatches** — Some plugins store local time, others UTC. The converter uses the source timezone when available, falling back to your site timezone.
- **Recurring events** — GatherPress treats each occurrence as a separate event. Recurrence rules are not converted; each exported occurrence imports individually.
- **Venue deduplication** — Importing the same file twice may create duplicates. Always import into a clean environment or verify existing data.
- **Shortcodes in content** — Source plugin shortcodes will appear as raw text. Review imported event content and clean up as needed.
- **Shared post type slugs** — Events Manager and Event Organiser both use `event`. The plugin distinguishes them by meta keys. Import data from only one source at a time.
- **Taxonomy-based venues (two-pass import)** — Plugins that store venues as taxonomy terms (e.g., Event Organiser's `event-venue`, MEC's `mec_location`, EventON's `event_location`) require a two-pass import: the first import creates `gatherpress_venue` posts from venue terms and skips events; the second import creates events and links them to venues. This logic is implemented as a shared trait (`Taxonomy_Venue_Handler`) and interface (`Taxonomy_Venue_Adapter`) that any adapter can reuse. Simply import the same WXR file twice. See [`docs/event-organiser.md`](docs/event-organiser.md) for a detailed explanation of the two-pass strategy and how to add support for additional taxonomy-venue plugins.

---

## Extending for Custom Plugins

Add support for additional event plugins by creating a class that implements the `Source_Adapter` interface and registering it:

```php
$migration = \GatherPressExportImport\Migration::get_instance();
$migration->register_adapter( new My_Custom_Adapter() );
```

All available filters (`gpei_event_post_type_map`, `gpei_venue_post_type_map`, `gpei_taxonomy_map`, `gatherpress_pseudopostmetas`) are documented with `@example` annotations directly in the source code. Filter reference docs are auto-generated from those docblocks.

---

## FAQ

**Does this replace the source plugin's export?**
No. You use the normal WordPress XML export from your source site. This plugin intercepts the import process and transforms the data on the fly.

**Do I need to deactivate the source event plugin first?**
It is recommended to deactivate the source plugin before flushing permalinks after import, to avoid slug conflicts. The import itself works whether or not the source plugin is active.

**What about recurring events?**
GatherPress treats each event occurrence as a separate post. If your source plugin exports each occurrence individually, they will each become a separate GatherPress event.

---

## Changelog

### 0.1.0

- Initial release with import interception for six major event plugins.
- Custom importer screen with prerequisite checks and step-by-step instructions.
- Playground blueprints for all supported source plugins with demo data.

---

## License

This project is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
