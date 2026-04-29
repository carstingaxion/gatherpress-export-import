# EventON — Source Details

Adapter class: `EventON_Adapter`

### Adapter Completeness

| Step | Description | Status |
|:---:|---|:---:|
| 1 | Source documentation (`source-eventon.md`) | ✅ |
| 2 | Blueprint import script (`eventon-import.php`) | ❌ |
| 3 | WXR fixture from real export (`eventon.xml`) | ❌ |
| 4 | Adapter unit tests | ❌ |
| 5 | WXR import integration tests | ❌ |

**Progress: 2/5** — Adapter class and docs exist; needs fixture, unit tests, and import tests.

---

## Post Type Mapping

| Source | Target |
|---|---|
| `ajde_events` | `gatherpress_event` |

## Date Format

Unix timestamps stored in `evcal_srow` (start) and `evcal_erow` (end). The adapter converts these to `Y-m-d H:i:s` format using the site's configured timezone.

## Venue Handling

EventON stores venues as taxonomy terms (`event_location`), not as a custom post type. This adapter uses the **two-pass import strategy** via the `Taxonomy_Venue_Handler` trait:

- **Pass 1:** Creates `gatherpress_venue` posts from `event_location` taxonomy terms. Events are skipped.
- **Pass 2:** Imports events and links them to venues via the `_gatherpress_venue` shadow taxonomy.

Import the same WXR file twice.

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `event_type` | `gatherpress_topic` |

> `event_location` is NOT in the taxonomy map — it is handled separately by the two-pass strategy.

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | |
| Featured image | ✅ | |
| Start/end datetimes | ✅ | Via Unix timestamps in `evcal_srow` / `evcal_erow` |
| Timezone | 🚫 | Not stored per-event; site timezone used as fallback |
| Venue name | ✅ | From taxonomy term names via two-pass import |
| Venue address/details | 🚫 | Stored as taxonomy term meta; not included in WXR exports |
| Venue coordinates | 🚫 | Stored as taxonomy term meta |
| Venue–event link | ✅ | Via two-pass taxonomy import |
| Event categories | ✅ | Via `event_type` taxonomy |
| Event tags | 🚫 | EventON does not use a tag taxonomy |
| Organizer | ⚠️ | `event_organizer` taxonomy available but not mapped |
| Recurrence rules | 🚫 | Not available via WXR |
| RSVP / Tickets | 🚫 | Not convertible via WXR |

## Import Sequence

1. Import the WXR file (**Pass 1** — creates venues, skips events).
2. Import the **same WXR file again** (**Pass 2** — creates events, links venues).
3. Flush permalinks.

## Playground Blueprint

[![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-eventon.json)

## Known Limitations

- Venue address, phone, website, and coordinates are stored as taxonomy term meta. WordPress core WXR exports do **not** include term meta, so this data must be re-entered manually after import.
- Timezone is not stored per-event; the site's configured timezone is used during conversion.
- EventON organizer data (`event_organizer` taxonomy) is not mapped to GatherPress.
- EventON does not have an event tags taxonomy; no tags will be imported.
