# Events Manager — Source Details

Adapter class: `Events_Manager_Adapter`

### Adapter Completeness

| Step | Description | Status |
|:---:|---|:---:|
| 1 | Source documentation (`source-events-manager.md`) | ✅ |
| 2 | Blueprint import script (`events-manager-import.php`) | ✅ |
| 3 | WXR fixture from real export (`events-manager.xml`) | ⚠️ Empty placeholder |
| 4 | Adapter unit tests (`EventsManagerAdapterTest.php`) | ✅ |
| 5 | WXR import integration tests (`WXRImportEMTest.php`) | ✅ |

**Progress: 4/5** — Fixture needs real export data.

---

## Post Type Mapping

| Source | Target |
|---|---|
| `event` | `gatherpress_event` |
| `location` | `gatherpress_venue` |

## Date Format

`Y-m-d H:i:s` stored in `_event_start` / `_event_end` with timezone in `_event_timezone`.

## Venue Handling

Events Manager stores venues as a dedicated custom post type (`location`). During import, location posts are rewritten to `gatherpress_venue`.

**Venue details** (`_location_address`, `_location_town`, `_location_state`, `_location_postcode`, `_location_country`) are automatically assembled into GatherPress's `gatherpress_venue_information` JSON format during import via the shared `Venue_Detail_Handler` trait.

Events reference their venue via `_location_id` post meta, which contains the original `location` post ID. During import, this ID is resolved via the WordPress Importer's old-to-new ID mapping and linked via `link_venue()`.

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `event-categories` | `gatherpress_topic` |
| `event-tags` | `post_tag` |

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | |
| Featured image | ✅ | |
| Start/end datetimes | ✅ | |
| Timezone | ✅ | Via `_event_timezone` meta |
| Venue name | ✅ | |
| Venue address/details | ✅ | Assembled into `gatherpress_venue_information` JSON |
| Venue coordinates | ⚠️ | May require additional mapping |
| Venue–event link | ✅ | Via `_location_id` + ID mapping |
| Event categories | ✅ | |
| Event tags | ✅ | |
| Organizer | ⚠️ | Partially available but not mapped |
| Recurrence rules | 🚫 | Not available via WXR |
| RSVP / Tickets | 🚫 | Not convertible via WXR |

## Import Sequence

1. Export and import **locations first** (`location`).
2. Export and import **events** (`event`).
3. Flush permalinks.

## Playground Blueprint

[![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-events-manager.json)

## Known Limitations

- Shares the `event` post type slug with Event Organiser. Import data from only one source at a time.
- The adapter differentiates from Event Organiser by detecting `_event_start` in the meta keys.
- Venue phone and website are not stored as standard location meta and will be empty after import.
