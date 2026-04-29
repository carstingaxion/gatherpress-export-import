# Modern Events Calendar (Webnus) — Source Details

Adapter class: `MEC_Adapter`

### Adapter Completeness

| Step | Description | Status |
|:---:|---|:---:|
| 1 | Source documentation (`source-mec.md`) | ✅ |
| 2 | Blueprint import script (`mec-import.php`) | ⚠️ |
| 3 | WXR fixture from real export (`mec.xml`) | ❌ |
| 4 | Adapter unit tests | ❌ |
| 5 | WXR import integration tests | ❌ |

**Progress: 2/5** — Adapter class and docs exist; needs fixture, unit tests, and import tests.

---

## Post Type Mapping

| Source | Target |
|---|---|
| `mec-events` | `gatherpress_event` |

## Date Format

Date is stored as `Y-m-d` in `mec_start_date` / `mec_end_date`. Time components are stored separately:
- `mec_start_time_hour`, `mec_start_time_minutes`, `mec_start_time_ampm`
- `mec_end_time_hour`, `mec_end_time_minutes`, `mec_end_time_ampm`

The adapter combines these into `Y-m-d H:i:s` format for GatherPress.

## Venue Handling

MEC stores venues as taxonomy terms (`mec_location`), not as a custom post type. This adapter uses the **two-pass import strategy** via the `Taxonomy_Venue_Handler` trait:

- **Pass 1:** Creates `gatherpress_venue` posts from `mec_location` taxonomy terms. Events are skipped.
- **Pass 2:** Imports events and links them to venues via the `_gatherpress_venue` shadow taxonomy.

Import the same WXR file twice.

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `mec_category` | `gatherpress_topic` |
| `mec_label` | `post_tag` |

> `mec_location` is NOT in the taxonomy map — it is handled separately by the two-pass strategy.

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | |
| Featured image | ✅ | |
| Start/end datetimes | ✅ | |
| Timezone | 🚫 | Not stored in meta; site timezone used as fallback |
| Venue name | ✅ | From taxonomy term names via two-pass import |
| Venue address/details | 🚫 | Stored as taxonomy term meta; not included in WXR exports |
| Venue coordinates | 🚫 | Stored as taxonomy term meta |
| Venue–event link | ✅ | Via two-pass taxonomy import |
| Event categories | ✅ | |
| Event tags | ⚠️ | MEC uses `mec_label` mapped to `post_tag` |
| Organizer | ⚠️ | `mec_organizer` taxonomy available but not mapped |
| Recurrence rules | 🚫 | Not available via WXR |
| RSVP / Tickets | 🚫 | Not convertible via WXR |

## Import Sequence

1. Import the WXR file (**Pass 1** — creates venues, skips events).
2. Import the **same WXR file again** (**Pass 2** — creates events, links venues).
3. Flush permalinks.

## Playground Blueprint

[![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-mec.json)

## MEC Internal API

MEC provides an internal API for event creation that populates custom tables and all required meta in one call. This is used by the blueprint import script when MEC is active:

```php
$mec  = MEC::getInstance();
$main = $mec->getMain();
$data = array(
    'post' => array(
        'post_title'  => 'My Event',
        'post_status' => 'publish',
    ),
    'meta' => array(
        'mec_start_date'         => '2026-05-01',
        'mec_end_date'           => '2026-05-01',
        'mec_start_time_hour'    => '10',
        'mec_start_time_minutes' => '00',
        'mec_end_time_hour'      => '12',
        'mec_end_time_minutes'   => '00',
    ),
    'terms' => array(
        'mec_location' => array( $term_id ),
    ),
);
$event_id = $main->save_event( $data );
```

This is **not used by the adapter** (which handles WXR import via meta stashing), but is documented for reference when creating demo data or programmatic integrations.

## Known Limitations

- Venue address, phone, website, and coordinates are stored as taxonomy term meta. WordPress core WXR exports do **not** include term meta, so this data must be re-entered manually after import.
- Timezone is not stored per-event; the site's configured timezone is used during conversion.
- MEC organizer data (`mec_organizer` taxonomy) is not mapped to GatherPress.
