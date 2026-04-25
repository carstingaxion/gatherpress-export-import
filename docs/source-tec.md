# The Events Calendar (StellarWP) — Source Details

Adapter class: `TEC_Adapter`

---

## Post Type Mapping

| Source | Target |
|---|---|
| `tribe_events` | `gatherpress_event` |
| `tribe_venue` | `gatherpress_venue` |

## Date Format

`Y-m-d H:i:s` stored in `_EventStartDate` / `_EventEndDate` as local time, with timezone in `_EventTimezone` (PHP timezone string, e.g., `America/Los_Angeles`).

Real TEC WXR exports also include UTC variants (`_EventStartDateUTC`, `_EventEndDateUTC`) and a pre-calculated `_EventDuration` in seconds, but the adapter only uses the local datetime and timezone fields for conversion — the rest are intercepted and discarded.

## Meta Keys in Real WXR Exports

A real TEC WXR export includes significantly more meta keys than just the primary datetime fields. The adapter stashes **all** of these to prevent them from polluting `wp_postmeta` on converted GatherPress posts.

### Event meta keys

| Meta Key | Purpose | Used by Adapter |
|---|---|:---:|
| `_EventStartDate` | Local start datetime | ✅ Datetime conversion |
| `_EventEndDate` | Local end datetime | ✅ Datetime conversion |
| `_EventTimezone` | PHP timezone string | ✅ Datetime conversion |
| `_EventVenueID` | Venue post ID reference | ✅ Venue linking |
| `_EventStartDateUTC` | UTC start datetime | 🚫 Stashed & discarded |
| `_EventEndDateUTC` | UTC end datetime | 🚫 Stashed & discarded |
| `_EventDuration` | Duration in seconds | 🚫 Stashed & discarded |
| `_EventTimezoneAbbr` | Timezone abbreviation (e.g., "PDT") | 🚫 Stashed & discarded |
| `_EventCost` | Event cost string | 🚫 Stashed & discarded |
| `_EventCurrencySymbol` | Currency symbol (e.g., "$") | 🚫 Stashed & discarded |
| `_EventCurrencyCode` | Currency code (e.g., "USD") | 🚫 Stashed & discarded |
| `_EventCurrencyPosition` | Symbol position ("prefix"/"suffix") | 🚫 Stashed & discarded |
| `_EventURL` | External event URL | 🚫 Stashed & discarded |
| `_EventOrganizerID` | Organizer post ID | 🚫 Stashed & discarded |
| `_EventAllDay` | All-day flag | 🚫 Stashed & discarded |
| `_EventHideFromUpcoming` | Hide from upcoming list | 🚫 Stashed & discarded |
| `_EventOrigin` | Data origin tracker | 🚫 Stashed & discarded |
| `_EventShowMap` | Show map toggle | 🚫 Stashed & discarded |
| `_EventShowMapLink` | Show map link toggle | 🚫 Stashed & discarded |

### Venue meta keys

| Meta Key | Purpose | Used by Adapter |
|---|---|:---:|
| `_VenueAddress` | Street address | ✅ → `fullAddress` |
| `_VenueCity` | City name | ✅ → `fullAddress` |
| `_VenueState` | State (US-centric) | ✅ → `fullAddress` |
| `_VenueStateProvince` | State/Province (international) | ✅ → `fullAddress` |
| `_VenueZip` | Postal/ZIP code | ✅ → `fullAddress` |
| `_VenueCountry` | Country name | ✅ → `fullAddress` |
| `_VenuePhone` | Phone number | ✅ → `phoneNumber` |
| `_VenueURL` | Website URL | ✅ → `website` |
| `_VenueOrigin` | Data origin tracker | 🚫 Stashed & discarded |
| `_VenueShowMap` | Show map toggle | 🚫 Stashed & discarded |
| `_VenueShowMapLink` | Show map link toggle | 🚫 Stashed & discarded |

> **Note:** TEC uses both `_VenueState` and `_VenueStateProvince` depending on the version and configuration. Both are mapped to the `state` component; whichever is non-empty will be used in the full address.

## Venue Handling

TEC stores venues as a dedicated custom post type (`tribe_venue`). During import:

1. Venue posts are rewritten to `gatherpress_venue`.
2. Venue detail meta keys (`_VenueAddress`, `_VenueCity`, etc.) are intercepted by the `Venue_Detail_Handler` trait, assembled into a `fullAddress` string, and saved as `gatherpress_venue_information` JSON on the converted venue post.
3. Events reference venues via `_EventVenueID` post meta, which is resolved using the WordPress Importer's old-to-new ID mapping.
4. The adapter's `link_venue()` method assigns the corresponding `_gatherpress_venue` shadow taxonomy term to the event.

## Taxonomy Mapping

| Source Taxonomy | Target Taxonomy |
|---|---|
| `tribe_events_cat` | `gatherpress_topic` |
| `post_tag` | `post_tag` (no change) |

## WXR Export Compatibility

| Data Type | Available | Notes |
|---|:---:|---|
| Event title & content | ✅ | Standard post fields |
| Featured image | ✅ | Standard attachment handling |
| Start/end datetimes | ✅ | Via `_EventStartDate` / `_EventEndDate` |
| Timezone | ✅ | Via `_EventTimezone` meta |
| Venue name | ✅ | `tribe_venue` post title |
| Venue address/details | ✅ | Assembled into `gatherpress_venue_information` JSON |
| Venue phone | ✅ | Via `_VenuePhone` → `phoneNumber` |
| Venue website | ✅ | Via `_VenueURL` → `website` |
| Venue coordinates | ⚠️ | Not present in standard TEC meta; requires TEC Pro or manual entry |
| Venue–event link | ✅ | Via `_EventVenueID` + WordPress Importer ID mapping |
| Event categories | ✅ | `tribe_events_cat` → `gatherpress_topic` |
| Event tags | ✅ | Uses standard `post_tag` (no conversion needed) |
| Organizer | 🚫 | `tribe_organizer` posts are in the WXR but not mapped (GatherPress has no organizer entity) |
| Event cost | 🚫 | Available in WXR but discarded (no GatherPress equivalent) |
| Recurrence rules | 🚫 | Not available in standard WXR; each occurrence must be exported individually |
| RSVP / Tickets | 🚫 | Not convertible via WXR |

## Import Sequence

1. Export and import **venues first** (`tribe_venue`).
2. Export and import **events** (`tribe_events`).
3. Flush permalinks.

Alternatively, if your WXR file contains both post types and venues appear before events in the XML, a single import pass may work — but separate exports are more reliable.

## Demo Data

The Playground blueprint creates 3 venues and 4 events with full taxonomy term assignments:

| Venues | Events |
|---|---|
| Downtown Convention Center (Portland, OR) | Annual WordPress Summit 2025 |
| Riverside Community Hall (Austin, TX) | Block Editor Deep Dive Workshop |
| Innovation Hub Berlin (Berlin, Germany) | Community Contributor Day |
| | Evening Networking Mixer |

**Categories:** Conference, Workshop, Meetup
**Tags:** tech, networking, community

## Playground Blueprint

[![Launch](https://img.shields.io/badge/Launch-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/refs/heads/main/.wordpress-org/blueprints/blueprint-tec.json)

## Known Limitations

- **Organizer data** (`tribe_organizer`) is present in WXR exports but cannot be mapped to GatherPress (no organizer entity). The `_EventOrganizerID` meta is stashed and discarded.
- **Recurring events** must be exported as individual occurrences. TEC Pro's recurrence rules are not converted.
- **Venue coordinates** are not part of TEC's standard venue meta. They may be available via TEC Pro's `_VenueLat` / `_VenueLng` keys, which are not currently mapped.
- **Event cost/currency** data (`_EventCost`, `_EventCurrencySymbol`, `_EventCurrencyCode`) is available in the WXR but has no GatherPress equivalent and is discarded.
- **Map display toggles** (`_EventShowMap`, `_EventShowMapLink`, `_VenueShowMap`, `_VenueShowMapLink`) are TEC-specific UI settings that have no GatherPress equivalent.
