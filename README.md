# GatherPress Export Import

**Contributors:** carstenbach & WordPress Telex  
**Tags:** data-liberation, import, migration, events  
**Tested up to:** 6.9  
**Stable tag:** 0.4.0  
**Requires Plugins:**  gatherpress  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

[![Playground Demo Link][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/main/.wordpress-org/blueprints/blueprint.json) [![Build, test & measure](https://github.com/carstingaxion/gatherpress-export-import/actions/workflows/build-test-measure.yml/badge.svg?branch=main)](https://github.com/carstingaxion/gatherpress-export-import/actions/workflows/build-test-measure.yml)

Intercepts WordPress XML imports from third-party event plugins and transforms them into GatherPress data automatically.

---

## Description

This plugin hooks into the standard WordPress Importer to transparently convert event data from third-party event plugins into GatherPress events and venues.

**No custom WXR files needed.** Simply export from your source event plugin using WordPress's built-in Tools > Export, then import the file normally via Tools > Import. The plugin automatically:

1. **Rewrites post types** — third-party event and venue post types become `gatherpress_event` and `gatherpress_venue`.
2. **Converts date/time data** — source plugin meta keys are intercepted and converted into GatherPress datetime format, then saved to the `gp_event_extended` table via `Event::save_datetimes()`.
3. **Links venues** — venue references are resolved using the WordPress Importer's old-to-new ID mapping and linked via GatherPress's `_gatherpress_venue` shadow taxonomy.

---

## Supported Source Plugins

| Source Plugin | Import | Manually Tested | PHPUnit Tested |
|---|:---:|:---:|:---:|
| [**The Events Calendar**](docs/source-tec.md) (StellarWP) | ✅ | ✅ | ✅ |
| [**Events Manager**](docs/source-events-manager.md) | ⚠️ | ⚠️ | ⚠️ |
| [**Modern Events Calendar**](docs/source-mec.md) (Webnus) | ⚠️ | ❌ | ❌ |
| [**All-in-One Event Calendar**](docs/source-aioec.md) | ⚠️ | ❌ | ❌ |
| [**EventON**](docs/source-eventon.md) | ⚠️ | ❌ | ❌ |
| [**Event Organiser**](docs/source-event-organiser.md) (Stephen Harris) | ⚠️ | ✅ | ✅ |

**Legend:** ✅ Fully supported/tested — ⚠️ Partial (some data unavailable via WXR) — 🚫 Not supported by GatherPress — ❌ Not yet

---

## Installation

1. Upload the plugin files to `/wp-content/plugins/gatherpress-export-import`.
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

## Demo Playground Blueprints

Test the migration workflow by launching a WordPress Playground instance pre-loaded with demo data for each supported source plugin. Each blueprint creates sample events, venues, and plugin-specific taxonomy terms.

### Import Target

| Environment | Description | Link |
|---|---|---|
| **GatherPress Migration** | Ready to receive imports | [![Launch][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint.json) |

### Source Plugin Demos

Each lands on **Tools > Export** so you can export demo data and test the migration.

| Source Plugin | Link |
|---|---|
| **The Events Calendar** (StellarWP) | [![Launch][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-tec.json) |
| **Events Manager** | [![Launch][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-events-manager.json) |
| **Modern Events Calendar** (Webnus) | [![Launch][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-mec.json) |
| **EventON** | [![Launch][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-eventon.json) |
| **All-in-One Event Calendar** | [![Launch][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-aioec.json) |
| **Event Organiser** (Stephen Harris) | [![Launch][playground_badge]](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-event-organiser.json) |

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
- **Taxonomy-based venues (two-pass import)** — Plugins that store venues as taxonomy terms (e.g., Event Organiser's `event-venue`, MEC's `mec_location`, EventON's `event_location`) require a two-pass import: the first import creates `gatherpress_venue` posts from venue terms and skips events; the second import creates events and links them to venues. This logic is implemented as a shared trait (`Taxonomy_Venue_Handler`) and interface (`Taxonomy_Venue_Adapter`) that any adapter can reuse. Simply import the same WXR file twice. See the [Import Guide](docs/import-guide.md) for a detailed explanation of the two-pass strategy and how to add support for additional taxonomy-venue plugins.

---

## Extending for Custom Plugins

Add support for additional event plugins by creating a class that implements the `Source_Adapter` interface and registering it:

```php
$migration = \GatherPressExportImport\Migration::get_instance();
$migration->register_adapter( new My_Custom_Adapter() );
```

For a complete walkthrough of building and testing a new adapter, see the [Add a New Adapter Guide](docs/add-adapter-guide.md).

All available filters are documented, see the [Hooks documentation](docs/developer/hooks/Hooks.md) for usage details.

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

All notable changes to this project will be documented in the [CHANGELOG.md](CHANGELOG.md).

---

## License

This project is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).


[playground_badge]: https://img.shields.io/badge/WordPress_Playground-blue?logo=wordpress&logoColor=%23fff&labelColor=%233858e9&color=%233858e9 "Start in WordPress Playground"
