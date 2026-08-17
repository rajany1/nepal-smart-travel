# 03 — Agent System: Rules-Based Architecture

> Status: Implemented (2026-08-16)
> Scope: Decommission LLM from 6 of 7 AI agents. The chat assistant (CustomerSupport) keeps AI.

## 1. Goal

All background agents run **deterministically with zero AI/LLM calls** so results are:
predictable, testable, instant, and free of API cost. No external provider is
required for any agent path.

## 2. AI Decommission Map

| Agent type | Handler | Before | After | Solution doc |
|---|---|---|---|---|
| `review_moderator` | ReviewModeratorHandler | LLM verdict | Rules engine + scoring | §3 |
| `translation` | TranslationHandler | LLM | Glossary + transliteration | §4 |
| `travel_consultant` | TravelConsultantHandler | LLM | ItineraryBuilder (rules) | §5 |
| `hotel_manager` | HotelManagerHandler | LLM | HotelAnalyticsService (rules) | §6 |
| `content_writer` | ContentWriterHandler | LLM | PlaceDescriptionBuilder (templates) | §7 |
| `marketing` | MarketingHandler | LLM | MarketingTemplateEngine (templates) | §8 |
| `customer_support` | CustomerSupportHandler | LLM | **KEEPS AI** (user decision) | — |
| `report_analysis` | ReportAnalysisService | LLM | **UNCHANGED** (out of scope) | — |

Nothing in the task pipeline changes: `AgentOrchestrator` still resolves handlers
by `agent_type` and writes results to `ai_agent_tasks.output_data`. Only the
inside of each handler changed.

## 3. Review Moderation (rules engine)

**Files**
- `app/Services/Rules/RuleContext.php` — immutable value bag passed to every rule
- `app/Services/Rules/BaseRule.php` — contract: `applies()`, `execute()`
- `app/Services/Rules/RuleEngine.php` — runs applicable rules, sums weighted points
- `app/Services/Rules/ReviewScoringService.php` — orchestration + verdicts
- `app/Services/Rules/Review/*.php` — the 7 rules

**Rules** (points → weighted score 0–100)

| Rule | Fires on | Weight |
|---|---|---|
| EmptyTextRule | empty title + empty description | 100 |
| LinkRule | URL in text | 20 |
| BadWordRule | `ContentSafetyService::scan()` hits (severity multiplies) | 10–100 |
| CapsLockRule | >60% uppercase, length ≥ 20 | 15 |
| RepeatedCharacterRule | 4+ consecutive same chars | 15 |
| DuplicateTextRule | same text elsewhere in last 7 days | 30 |
| UserTrustRule | user has moderation strikes | 25 |

**Verdicts** (score 0–100)
- `score >= 70` → `rejected` (`moderation_status = 'rejected'`)
- `score >= 40` → `review` (status stays `null`, `moderated_at` set so it is not
  rescanned; visible until a moderator decides)
- otherwise → `approved`

For rejected/approved reviews the same batch loop updates `moderated_at` and
`moderated_by = 1`. Batch size 20, ordered by `created_at`.

**Why these thresholds:** 70 is reached by one hard violation (link/bad word
strong) or several soft ones; 40 by one medium violation — the point where human
eyes add value. Both constants live in `ReviewScoringService`.

## 4. Translation (glossary, no LLM)

**Files**
- `app/Services/GlossaryTranslator.php`
- `app/Models/TranslationGlossary.php`
- migration `2026_08_16_000001_create_translation_glossary_table.php`
- `database/seeders/TranslationGlossarySeeder.php` (~180 curated terms:
  travel, tourism, alerts, report words, all districts + famous places)
- `app/Services/Ai/Handlers/TranslationHandler.php` (rewritten)

**Pipeline**
1. Glossary lookup — exact-case-insensitive match on `term`; every hit is
   replaced with the curated Nepali word (context-aware first). The glossary is
   the source of truth for correctness.
2. Best-effort transliteration — any remaining Latin words with known mappings
   become Devanagari syllables (documented as best-effort, not translation).
3. Already-Devanagari text stays untouched.

**Sources translated (6):** place description, place name, review description,
report title, report description, alert description. Everything is stored in
`model_translations` (`locale = 'ne'`, `source = 'rules'`), never overwriting an
existing translation of the same field.

**Honest limitation:** a glossary cannot fully translate arbitrary prose. The
design accepts this deliberately — it guarantees **no wrong Nepali**, only
"Nepali for known terms + original English elsewhere". Admin/curators grow the
glossary over time. Full translation remains possible only via the retained LLM
infrastructure if ever re-enabled.

## 5. Travel Consultant (itineraries)

**Files:** `app/Services/ItineraryBuilder.php`, `TravelConsultantHandler.php`

- `topDistricts()` — districts with most located places (top 5)
- `buildForDistrict($district)` — top 8 places by rating → chunk into days of 3
  stops (max 3 days)
- Each day's stops are ordered by **greedy nearest-neighbour** (haversine) so
  the route is geographically sensible
- Day theme = most frequent category among the day's stops
- Actions: `itinerary` (single district, `district` input) and `auto` (top 5
  districts)

## 6. Hotel Manager (performance reports)

**Files:** `app/Services/HotelAnalyticsService.php`, `HotelManagerHandler.php`

Metrics computed from DB (no ML):
- reviews in last 30d, negative (rating ≤ 2) in last 90d
- rating trend = 30d avg − 90d avg
- district average among hotel-type places in same district

Insights are **threshold rules**:
- rating ≥ 4.0 → strength; no negatives in 90d → strength
- rating < 3.5 → improve; trend < −0.2 → improve; below district avg → improve
- `guest_sentiment` = improving / declining / positive / negative / mixed
- `needs_attention` = any negative reviews OR rating < 3.5 OR trend < −0.2

## 7. Content Writer (place descriptions)

**Files:** `app/Services/PlaceDescriptionBuilder.php`, `ContentWriterHandler.php`

Nepali descriptions assembled from real place data (name, district, address,
rating) using per-category templates (hotel/restaurant/cafe/attraction/temple/
nature/market/default, keyword-matched). Always 2–3 factual sentences; results
are stored in `model_translations` (`source = 'rules'`). Handles `write`
(single place) and `auto` (first 10 places with missing descriptions).

## 8. Marketing (campaign copy)

**Files:** `app/Services/MarketingTemplateEngine.php`, `MarketingHandler.php`

- `weeklyDigest()` — Nepali digest post from featured/top places + hashtags
- `placePromo()` — English one-liner per place
- `singleCampaign()` — Nepali + English copy, audience, suggested channels
- deterministic `rotationKey(placeId)` (crc32 mod) for stable rotation

## 9. Guarantees & Testing

- **Determinism:** same input → same output, always. No randomness, no model.
- **Zero external calls:** none of the 6 handlers touches HTTP/API providers.
- **Testing:** `php -l` clean on all files; each handler exercised end-to-end
  through `AgentOrchestrator::executeTask()` against real data
  (translation → 9 items, travel_consultant → 5 itineraries, hotel_manager →
  10 reports, content_writer → 5 descriptions, marketing → 6 posts,
  review_moderator → clean review approved).

## 10. What Was Kept

- `CustomerSupportHandler` + `BaseHandler::ai()` — the chat assistant stays LLM.
- `ReportAnalysisService` + `AiFallbackRouter`/providers — report AI untouched.
- `FraudDetectionHandler`, `RoutePlannerHandler`, `ManagerAiHandler` — already
  rules-based before this work.
- `AgentOrchestrator` task pipeline — unchanged.
