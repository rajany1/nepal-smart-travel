# Oripori Vision — Place-Centric Intelligence Platform

> Facebook tells you what people are posting.
> Oripori tells you what is happening around you.

---

## Core Identity

**Facebook = People-centric** → "What did my friends post?"
**Oripori = Place-centric** → "What's happening around me right now?"

Location is not an attribute of content. **Location IS the product.**

---

## Architecture

```
                 ORIPORI
                    │
          ┌─────────┴─────────┐
          │                   │
       AROUND ME             MAP
          │                   │
   ┌──────┼──────┐      ┌─────┼─────┐
   │      │      │      │     │     │
 Events Alerts Services Places Reports
   │      │      │      │     │     │
   └──────┴──────┴──────┘─────┴─────┘
                    │
              COMMUNITY
                    │
              VERIFICATION
                    │
               LOCAL DATA
```

**Hierarchy:** Location → Information → Community → Social

---

## Implementation Phases

### Phase 1: Around Me Screen (Core UX)
- Unified location-aware intelligence feed
- Sections: Emergency (red), Events (green), Services (blue), Local Updates (yellow)
- Distance + time + confirmation count on each item
- Replaces Explore tab as default

### Phase 2: Structured Reports (Data Format)
- Reports become structured data: type, status, location_name, expires_at
- Community confirmation: "I can confirm" button
- Confidence score: confirmations × 15 + authenticity_score
- report_confirmations table

### Phase 3: Community Verification
- Nearby users can confirm reports
- Confidence badge: "87% community confidence"
- Location-based confirmation (must be nearby)

### Phase 4: Time-Based Content States
- LIVE (< 1 hour), TODAY, RECENT (this week), EXPIRED
- Auto-expire based on category
- Filter tabs: All | Live | Today | Recent

### Phase 5: Smart Map Layers
- Category-based toggles on map
- Emergency, Events, Services, Updates, Road Issues
- Heatmap mode for density

### Phase 6: "What's happening near me?" Summary
- One-tap radius summary
- Category counts within 1km, 5km

### Phase 7: Local Business Profiles
- Open/Closed status, today's offer, live events
- Business dashboard for business-role users

---

## API Endpoints (New)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/around-me` | Aggregated location feed |
| POST | `/api/v1/reports/{id}/confirm` | Community confirmation |
| GET | `/api/v1/reports/{id}/confirmers` | Who confirmed |
| PUT | `/api/v1/places/{id}/status` | Business open/close |
| GET | `/api/v1/map/layers` | Layered map data |

---

## Database Changes (New)

| Table | Purpose |
|-------|---------|
| `report_confirmations` | Community "I can confirm" records |
| (alter `reports`) | Add: is_active, expires_at, confirmed_by_count, location_name, report_subcategory |

---

## Status

- [x] Phase 1: Around Me Screen
- [x] Phase 2: Structured Reports
- [x] Phase 3: Community Verification
- [x] Phase 4: Time-Based States
- [x] Phase 5: Smart Map Layers
- [x] Phase 6: Nearby Summary
- [x] Phase 7: Business Profiles
