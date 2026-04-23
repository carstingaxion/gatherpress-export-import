# Events Manager — Source Details

Adapter class: `Events_Manager_Adapter`

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

> **Note:** Events Manager uses `_location_id` on event posts for the venue reference, which requires ID mapping resolution during import.

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
| Venue–event link | ⚠️ | Uses `_location_id` which requires ID mapping |
| Event categories | ✅ | |
| Event tags | ✅ | |
| Organizer | ⚠️ | Partially available but not mapped |
| Recurrence rules | ❌ | Not available via WXR |
| RSVP / Tickets | ❌ | Not convertible via WXR |

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
