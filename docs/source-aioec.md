# All-in-One Event Calendar — Source Details

Adapter class: `AIOEC_Adapter`

### Adapter Completeness

| Step | Description | Status |
|:---:|---|:---:|
| 1 | Source documentation (`source-aioec.md`) | ✅ |
| 2 | Blueprint import script (`aioec-import.php`) | ✅ |
| 3 | WXR fixture from real export (`aioec.xml`) | ❌ Empty placeholder |
| 4 | Adapter unit tests | ❌ Not yet |
| 5 | WXR import integration tests | ❌ Not yet |

**Progress: 2/5** — Adapter class and docs exist; needs fixture, unit tests, and import tests.

---

## Post Type Mapping

| Source | Target |
|---|---|
| `ai1ec_event` | `gatherpress_event` |

## Date Format

AIOEC stores nearly all event data (datetimes, venue, recurrence) in a **custom database table** (`ai1ec_events`), not in post meta. Standard WXR exports contain only the post title and content.

## Venue Handling

AIOEC does not use a standard venue post type or taxonomy. Venue data is stored in the custom `ai1ec_events` table and is **not available** in WXR exports.

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `events_categories` | `gatherpress_topic` |
| `events_tags` | `post_tag` |

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | |
| Featured image | ✅ | |
| Start/end datetimes | 🚫 | Stored in custom table, not in post meta |
| Timezone | 🚫 | Stored in custom table |
| Venue name | 🚫 | Stored in custom table |
| Venue address/details | 🚫 | Stored in custom table |
| Venue coordinates | 🚫 | Stored in custom table |
| Venue–event link | 🚫 | Stored in custom table |
| Event categories | ✅ | |
| Event tags | ✅ | |
| Organizer | 🚫 | Stored in custom table |
| Recurrence rules | 🚫 | Stored in custom table |
| RSVP / Tickets | 🚫 | Not convertible via WXR |

## Import Sequence

1. Export and import the `ai1ec_event` posts.
2. Manually add datetimes and venue data in GatherPress after import.
3. Flush permalinks.

## Playground Blueprint

[![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-aioec.json)

## Known Limitations

- **Most event data is inaccessible via WXR export.** AIOEC uses a custom database table (`ai1ec_events`) for nearly all event metadata. The adapter primarily handles post type rewriting to preserve event titles and content.
- Datetime conversion requires manual mapping or direct database access beyond what WXR export provides.
- This is the most limited adapter — consider it a starting point for manual migration rather than a fully automated solution.
