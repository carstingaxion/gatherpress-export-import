# Event Organiser (Stephen Harris) — Source Details

Adapter class: `Event_Organiser_Adapter`

For a detailed walkthrough of the two-pass import architecture and troubleshooting, see the [Import Guide](import-guide.md#plugins-with-taxonomy-based-venues-two-pass-import).

---

## Post Type Mapping

| Source | Target |
|---|---|
| `event` | `gatherpress_event` |

## Date Format

`Y-m-d H:i:s` stored in `_eventorganiser_schedule_start_datetime` / `_eventorganiser_schedule_end_datetime`. Datetimes are in site-local time; the site's configured timezone is used during conversion.

Additional fallback keys: `_eventorganiser_schedule_start_finish`, `_eventorganiser_schedule_last_start`, `_eventorganiser_schedule_last_finish`.

## Venue Handling

Event Organiser stores venues as taxonomy terms (`event-venue`), not as a custom post type. This adapter uses the **two-pass import strategy** via the `Taxonomy_Venue_Handler` trait:

- **Pass 1:** Creates `gatherpress_venue` posts from `event-venue` taxonomy terms. Events are skipped.
- **Pass 2:** Imports events and links them to venues via the `_gatherpress_venue` shadow taxonomy.

Import the same WXR file twice.

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `event-category` | `gatherpress_topic` |
| `event-tag` | `post_tag` |

> `event-venue` is NOT in the taxonomy map — it is handled separately by the two-pass strategy.

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | |
| Featured image | ✅ | |
| Start/end datetimes | ✅ | |
| Timezone | ❌ | Stored in site-local time; site timezone used during conversion |
| Venue name | ✅ | From taxonomy term names via two-pass import |
| Venue address/details | ❌ | Stored as taxonomy term meta; not included in WXR exports |
| Venue coordinates | ❌ | Stored as taxonomy term meta |
| Venue–event link | ✅ | Via two-pass taxonomy import |
| Event categories | ✅ | |
| Event tags | ✅ | |
| Organizer | ❌ | Event Organiser does not have an organizer entity |
| Recurrence rules | ❌ | Not available via WXR; each occurrence must be a separate event |
| RSVP / Tickets | ❌ | Not convertible via WXR |

## Import Sequence

1. Import the WXR file (**Pass 1** — creates venues, skips events).
2. Import the **same WXR file again** (**Pass 2** — creates events, links venues).
3. Flush permalinks.

## Technical Details

For a detailed explanation of the two-pass import architecture — including hook registration, event skipping, deferred venue linking, pass detection logic, and troubleshooting — see the [Import Guide: Taxonomy-based venues](import-guide.md#taxonomy-venue-handler-internals).

## Playground Blueprint

[![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-event-organiser.json)

## Known Limitations

- Shares the `event` post type slug with Events Manager. Import data from only one source at a time.
- Venue address, phone, website, and coordinates are stored as taxonomy term meta. WordPress core WXR exports do **not** include term meta, so this data must be re-entered manually after import.
- Timezone is not stored per-event; datetimes are in site-local time.
- Recurring events: Each occurrence must be its own event in GatherPress. Recurrence rules are not converted.
