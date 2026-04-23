# The Events Calendar (StellarWP) — Source Details

Adapter class: `TEC_Adapter`

---

## Post Type Mapping

| Source | Target |
|---|---|
| `tribe_events` | `gatherpress_event` |
| `tribe_venue` | `gatherpress_venue` |

## Date Format

`Y-m-d H:i:s` stored in `_EventStartDate` / `_EventEndDate` with timezone in `_EventTimezone`.

## Venue Handling

TEC stores venues as a dedicated custom post type (`tribe_venue`). During import, venue posts are rewritten to `gatherpress_venue`. Events reference venues via `_EventVenueID` post meta, which is resolved using the WordPress Importer's old-to-new ID mapping.

**Venue details** (`_VenueAddress`, `_VenueCity`, `_VenueState`, `_VenueZip`, `_VenueCountry`, `_VenuePhone`, `_VenueURL`) are automatically assembled into GatherPress's `gatherpress_venue_information` JSON format during import via the shared `Venue_Detail_Handler` trait.

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `tribe_events_cat` | `gatherpress_topic` |
| `post_tag` | `post_tag` (no change) |

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | |
| Featured image | ✅ | |
| Start/end datetimes | ✅ | |
| Timezone | ✅ | Via `_EventTimezone` meta |
| Venue name | ✅ | |
| Venue address/details | ✅ | Assembled into `gatherpress_venue_information` JSON |
| Venue coordinates | ⚠️ | May require additional mapping depending on TEC version |
| Venue–event link | ✅ | Via `_EventVenueID` + ID mapping |
| Event categories | ✅ | |
| Event tags | ✅ | Uses standard `post_tag` |
| Organizer | ✅ | Available in WXR but not mapped (GatherPress has no organizer entity) |
| Recurrence rules | ❌ | Not available; each occurrence must be exported individually |
| RSVP / Tickets | ❌ | Not convertible via WXR |

## Import Sequence

1. Export and import **venues first** (`tribe_venue`).
2. Export and import **events** (`tribe_events`).
3. Flush permalinks.

## Playground Blueprint

[![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-tec.json)

## Known Limitations

- Organizer data (`tribe_organizer`) is present in WXR exports but cannot be mapped to GatherPress (no organizer entity).
- Recurring events must be exported as individual occurrences.
- Venue coordinates may not be present in all TEC versions.
