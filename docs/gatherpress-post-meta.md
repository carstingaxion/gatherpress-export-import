# GatherPress Registered Post Meta

This document lists the registered post meta keys per GatherPress post type. These are the meta keys that GatherPress officially registers via `register_post_meta()` or `register_meta()`, and represent the **semantically importable** data targets for migration.

> **Note:** This list is validated by the `GatherPressPostMetaTest` unit test. If GatherPress adds, removes, or renames registered meta keys in a future version, the test suite will fail — alerting maintainers to update this document and the migration adapters accordingly.

---

## `gatherpress_event`

GatherPress events store their primary data (datetimes, timezone) in a custom database table (`gp_event_extended`) via the `Event::save_datetimes()` method, **not** as registered post meta. The event post type has the following registered meta keys:

| Meta Key | Type | Description |
|---|---|---|
| `gatherpress_datetime` | `string` | Combined datetime JSON (legacy/internal) |
| `gatherpress_datetime_start` | `string` | Start datetime in site-local time (`Y-m-d H:i:s`) |
| `gatherpress_datetime_start_gmt` | `string` | Start datetime in GMT (`Y-m-d H:i:s`) |
| `gatherpress_datetime_end` | `string` | End datetime in site-local time (`Y-m-d H:i:s`) |
| `gatherpress_datetime_end_gmt` | `string` | End datetime in GMT (`Y-m-d H:i:s`) |
| `gatherpress_timezone` | `string` | PHP timezone string (e.g., `America/New_York`) |
| `gatherpress_max_guest_limit` | `integer` | Maximum number of guests per RSVP |
| `gatherpress_enable_anonymous_rsvp` | `boolean` | Whether anonymous RSVPs are allowed |
| `gatherpress_online_event_link` | `string` | URL for online/virtual event access |
| `gatherpress_max_attendance_limit` | `integer` | Maximum total attendance for the event |

### Data also stored in custom table

Datetime data is additionally stored in the `gp_event_extended` table and managed via `Event::save_datetimes()` / `Event::get_datetime()`. The registered post meta keys above mirror this data for REST API and block editor access.

### Relationships (taxonomy-based, not post meta)

- **Venue link** — via `_gatherpress_venue` shadow taxonomy term assignment

---

## `gatherpress_venue`

| Meta Key | Type | Description |
|---|---|---|
| `gatherpress_venue_information` | `string` | JSON-encoded venue details |

### `gatherpress_venue_information` JSON structure

```json
{
    "fullAddress": "WUK, holzplatz 7a, Halle (Saale)",
    "phoneNumber": "+49-123-456789",
    "website": "https://example.com",
    "latitude": "51.4772617",
    "longitude": "11.958262399601"
}
```

All fields are strings. Empty values are stored as empty strings (`""`), not `null`.

---

## Import Mapping Summary

| Source Data | GatherPress Target | Storage | Currently Mapped |
|---|---|---|:---:|
| Start/end datetimes | `gp_event_extended` table + `gatherpress_datetime_*` meta | `Event::save_datetimes()` | ✅ All adapters |
| Timezone | `gp_event_extended` table + `gatherpress_timezone` meta | `Event::save_datetimes()` | ✅ All adapters |
| Online event URL | `gatherpress_online_event_link` post meta | `update_post_meta()` | ✅ TEC (`_EventURL`) |
| RSVP settings | `gatherpress_enable_*` / `gatherpress_max_*` post meta | `register_post_meta()` | 🚫 No source equivalent in WXR |
| Venue address/phone/website/coords | `gatherpress_venue_information` post meta (JSON) | `register_post_meta()` | ✅ TEC, EM |
| Venue–event link | `_gatherpress_venue` shadow taxonomy | `wp_set_object_terms()` | ✅ All adapters |
| Event categories | `gatherpress_topic` taxonomy | `wp_set_object_terms()` | ✅ All adapters |

---

## What is NOT importable

The following data types have no registered target in GatherPress:

- 🚫 Event cost / ticket pricing
- 🚫 Organizer details (no organizer entity)
- 🚫 Recurrence rules (each occurrence is a separate post)
- 🚫 RSVP attendee lists
- 🚫 Event tags (`post_tag` is not registered for `gatherpress_event`)
- 🚫 Venue coordinates from source plugins that store lat/lng separately (the `gatherpress_venue_information` JSON supports `latitude`/`longitude` but most source plugins don't include coordinates in WXR exports)

---

## Changelog

- **0.2.0** — Updated event meta: GatherPress now registers 10 meta keys for `gatherpress_event` (added `gatherpress_datetime`, `gatherpress_datetime_start`, `gatherpress_datetime_start_gmt`, `gatherpress_datetime_end`, `gatherpress_datetime_end_gmt`, `gatherpress_timezone`; removed `gatherpress_enable_initial_decline` from the expected set as it may have been renamed or restructured).
