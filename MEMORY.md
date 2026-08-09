# MEMORY.md — Long-Term Memory

## Project

Nepal Smart Travel & Local Intelligence Platform — Laravel backend (`backend/`) + Flutter app (`mobile_app/`) + admin panel. Working directory: `C:\Users\ACER\Desktop\nepalsmarttravelandlocalintelligenceplatform`.

## Milestones

### 2026-08-04 — Logic audit complete: all 73 issues fixed
Ran a full logic audit (backend/admin BE-*, Flutter FL-*, integration IN-*) → `LOGIC_AUDIT_FIX_PLAN.md`. All 73 items are `[x]` (phases 1–4). The final session completed items 34–36. Full per-item changelog in `memory/2026-08-04.md`.

### 2026-08-08 — Business Partner Portal + Reward Offers + Tourist Web done
Full stack: partner registration→admin verification→offer creation→admin approval→user claim in Flutter; tourist Blade site (home/places/routes/offers) behind app-install gate. Verified end-to-end via two smoke suites (API + full web flow) — both PASS, cleanup done, admin password hash restored. Details + gotchas in `memory/2026-08-08.md` (4th task section).

### 2026-08-09 — Monetization rework: sponsors+store out, XP offers + ad campaigns in
Backend: sponsors/store API + legacy tables dropped; `reward_offers.price_xp`; bookings apply offer codes (`applied_at/consumed_at/booking_id` on redemptions, no XP refund on release); ad campaigns with `targeting` JSON (adContext home/report/nearby + districts/categories), `ad_clicks` table, impression dedupe (settings-driven), public `/ads/active` + track endpoints. Flutter: StoreScreen = Rewards (XP-priced offers) + My Codes + Bookings tabs; booking form sends `offer_code` w/ clamped discount; context-aware ads w/ impression+click tracking; sponsors/store UI deleted. 0 analyze errors. Details + gotchas in `memory/2026-08-09.md`.

## Key architectural facts (learned the hard way — don't re-discover)

- **Alert ownership relation**: `User::alerts()` = `hasMany(Alert::class, 'created_by')` — joins must use `alerts.created_by`, NOT `alerts.user_id` (SQL error; BE-06).
- **Admin auth pattern**: sibling controllers use a private `requireAdmin(Request $request)` = role check (`isAdmin() || isModerator()` → else 403) + optional route-name permission check (`Permission::where('route_name', $routeName)` non-empty → must `contains(fn($p) => $user->hasPermission($p->name))` → else 403). Copy from `AchievementController.php:20-34`. AiAgent/AiAgentTask controllers previously had NO auth at all (now fixed).
- **`$user` clobber trap**: `ReportController` feed had `$user = $request->user()` overwriting the sanctum fallback → own pending reports invisible (BE-21). Always `$request->user() ?? Auth::guard('sanctum')->user()`.
- **Email OTP dev-bridge**: backend register/resend return `'otp' => $otp` in JSON; `AuthProvider._lastOtp` pre-fills the 6-box verification UI. Mirrors the pre-existing `reset_token` bridge. (FL-09/IN-08.)
- **Emergency feed**: only `status = approved` reports may surface as alerts (BE-23); `is_emergency` filter on `ReportController::index` = priority in high/critical, driven by dedicated `ReportProvider` emergency list with own pagination (FL-20).
- **OSM places**: auto-created on first review with `is_active => false` (BE-18 — was true, bypassing moderation); `show()` returns `data: null` for unreviewed `osm_*` ids (IN-09); place id comparisons must use `.toString()` (admin int ids vs `osm_` string ids; FL-18).
- **verification_tick enum**: backend DB is none/blue/green/gold; API boundary maps none→gray; Flutter defaults are 'gray'. Never send 'none' — it renders as "NONE TICK" (IN-13).
- **Free subscription**: `User::created()` hook auto-creates the free subscription → the two AssignFree* commands were permanent no-ops; they're retired (backup in `%TEMP%\opencode\retired-commands`). Don't reintroduce subscription-assignment commands.
- **Anti-fake-report design (intentional)**: camera gallery disabled by design; EXIF GPS validation; only in-app camera captures accepted. Camera flow keeps `is_verified` semantics.
- **Report visibility contract**: feed = approved only; `show()` = approved or owner or staff (BE-22); `reviews()` returns `[]` for unreviewed OSM places.
- **Place duplicate prevention (2026-08-08)**: `PlaceController::store()` rejects 409 on existing osm_id OR same normalized name within ~150m; `osmStatus()` is global (no created_by filter) so admin-imported OSM places show `approved`; `nearbyCombined()` cross-source dedups OSM by DB osm_id / name+100m; `AdminController::createPlace()` blocks dupes and accepts osm_id/source; `places:dedupe` command merges legacy duplicates (moves place_images, place_reviews, model_translations — note columns are `translatable_type`/`translatable_id`, NOT `model_type`; reviews move can hit unique(user_id,place_id) → warn+skip). OSM place ids: app sends `osm_node/123`, DB stores `node/123` (strip `osm_` prefix).
- **connectivity_plus ^6.x**: `checkConnectivity()` returns `List<ConnectivityResult>` — use `result.isNotEmpty`, not `!= ConnectivityResult.none`.
- **Admin blades: NEVER wrap a table in a `<form>` containing per-row forms** — nested forms are invalid HTML, browsers drop the inner ones and row buttons submit the outer form silently (places.blade.php bug, 2026-08-08). Pattern: close bulkForm before the table, give checkboxes `form="bulkForm"`, set action in JS before `form.submit()`.
- **`moderation_queues.content_type` enum**: originally report/review/comment; 'place' added by migration 2026_08_08_130000 (was missing → `rejectPlace` ALWAYS 500'd with "Data truncated for column 'content_type'"); `submitted_by` is now nullable. When adding a new content type to moderation, update the enum migration first.
- **Place corrections flow (2026-08-08)**: users report wrong location/name/closed/etc via `POST /api/v1/places/corrections` → `place_corrections` table → admin reviews at `/admin/places/corrections` (Apply updates the place's name/coords; Reject adds note). Route order: static paths (`/places/corrections`) MUST be registered before `/places/{id}` in web.php.
- **Flutter model gotchas**: place model class is `Place` (file `lib/core/models/place.dart`) — NOT `PlaceModel`; app ids are `'admin_5'` / `'osm_node/123'` → strip non-digits for API place_id, send raw id as osm_id for OSM. `LocationService` uses factory ctor `LocationService()`, not `.instance`.
- **nearbyCombined merge (2026-08-08)**: now merges admin+OSM and `usort` by `distance_km` asc (was featured-admin-first + 50% OSM reservation = "OSM on top, DB below" bias). User's Nearby list is now purely distance-sorted.
- **Business partner portal + reward offers + tourist web (2026-08-08, all smoke-tested PASS)**: travel_partners gains `user_id` FK + `verification_status` (pending/verified/rejected) + `rejected_reason`, type now free-form varchar(255). Business role (is_system) + permissions `manage_offers` (menu_group monetization, order 8, route admin.offers), `verify_businesses`, `manage_curated_routes` (menu_group main, order 9, route admin.routes) — Permission model has `menu_group` column, admin nav groups = main/monetization/store/access.
- **Offer redemption FK gotcha**: `offer_redemptions` FK column is `offer_id` (migration) — `RewardOffer::redemptions()` must pass explicit FK: `hasMany(OfferRedemption::class, 'offer_id')`. Default (`reward_offer_id`) 500s with "Unknown column".
- **`curated_routes.waypoints`** is a json ARRAY of ints — `CuratedRoute::waypointPlaces()` returns a plain PHP array, so blades must use `count($places) > 0` NOT `$places->isNotEmpty()` (500).
- **Admin blades gotcha #2**: stray duplicate `@endsection` in travel_partners.blade.php (line ~213) 500'd the whole page ("Cannot end a section without first starting one"). Count @section/@endsection per file when editing blades.
- **User `password` cast = 'hashed'** (Laravel 13 default?) — do NOT `Hash::make()` before create, just assign the raw password.
- **Tourist web gate**: entire public Blade site is behind `x-app-gate` component — blur overlay + QR (https://api.qrserver.com/v1/create-qr-code/) + store links; unlock = cookie `nst_app=1` OR `?app=1` in URL. Store URLs in env: `APP_STORE_URL`/`PLAY_STORE_URL`.
- **Web route order**: static `/places/{id}` BEFORE `/{type}` catch-all; `web.category` only hotels|restaurants|attractions|cafes|activities; `web.place` id regex `[0-9]+|[0-9a-fA-F\-]{36}`; `web.route` = `routes/{route:slug}`. welcome.blade.php is gone (replaced by web.home).
- **Offer API**: `/v1/offers/my` must be registered before `/v1/offers/{id}`; claim codes = `RWD-` + Str::random(6) uppercase; all API JSON responses carry a pre-existing UTF-8 BOM — strip bytes in smoke scripts, Flutter is unaffected.
- **HTTP smoke-testing Laravel sessions (PHP stream wrapper)**: `file_get_contents` auto-follows redirects and LOSES the Set-Cookie from intermediate 302s → set `'max_redirects' => 0` in the stream context and follow manually; after login always re-GET the page to extract a fresh CSRF token (session regenerates on login).
- **Testing with unknown admin password**: temp-hash swap trick — save original password hash to temp file, set known temp password, run web smoke, restore original hash.

## Process notes (do these)

- Verification standard: `php -l` per changed PHP file; `flutter analyze` from `mobile_app/` must be 0 errors (354 pre-existing warnings/infos are the baseline); update `LOGIC_AUDIT_FIX_PLAN.md` checkbox + `memory/YYYY-MM-DD.md` per item.
- `php artisan test` passes (2 stock Pest examples only — no real test suite exists yet).
- PowerShell 5.1 env: `rg` unavailable (use Select-String/grep tool). PowerShell `.Replace()` + `Set-Content -Encoding UTF8` inserts a UTF-8 BOM into PHP files → strip bytes 0-2 (EF BB BF) after, or use the edit tool instead. Single-quoted PS strings can't contain `'`.
- Destructive ops: move to `C:\Users\ACER\AppData\Local\Temp\opencode\` backup instead of deleting.
- Per AGENTS.md: never commit unless explicitly asked; memory files = continuity.

## Open threads / ideas

- No real backend test suite — writing Pest feature tests for the fixed endpoints (auth OTP, report moderation visibility, ad impression dedupe, alert feed, offer claim/dup/limit, partner verify flow) would lock in the fixes.
- Admin-side UI polish for AI agent pages not in scope of the audit.
- Not yet live-tested (2026-08-08): Flutter guest→login save flow, 409 resubmit UI, admin quick import with osm_id, `places:dedupe` edge cases (reviews w/ unique conflicts).
- Offers/routes content is thin (2 demo curated routes; offers created via smoke get cleaned up) — seed real offers + routes via the admin UI before launch. Flutter offers-card images: consider offline placeholder handling.
- **Partner pays vs earns (2026-08-09)**: ads = PREPAID budgets via eSewa/Khalti (sandbox, config/payments.php + env keys); admin earns ad spend (CPM/CPC from game_settings); campaign auto-pauses when spent_amount >= budget (AdCampaign::calculateSpend, API AdController applySpend). offers = partner earns value_npr minus offer_commission_percent (default 10; field on reward_offers + snapshots commission_percent/admin_commission/partner_earnings on offer_redemptions at claim; earnings counted when status=used). ad_payments table records gateway transactions; admin approve() blocks unpaid partner campaigns; admin refund() marks payments refunded + campaign rejected + logs ad-campaign.refunded.
- **Blade UTF-8 trap (2026-08-09, painful)**: Windows PowerShell Get-Content/Set-Content default (ANSI) round-trips CORRUPT multibyte UTF-8 (emoji, middle-dot, em-dash  mojibake like A??'A,A). Always use [System.IO.File]::ReadAllLines/WriteAllLines with an explicit System.Text.UTF8Encoding(False), or bare ASCII in all blade text. Recheck touched blades with a non-ASCII scan after editing.
- **Partner dashboard stats keys changed**: ads paid/spent (ads_total/ads_active removed), offer_earned/offer_commission/offer_used. Admin ad-campaigns stats: revenue/unpaid (earned removed). Don't resurrect earned() on AdCampaign - replaced by payments() hasMany.
- **Reward offer expiry = system lock (2026-08-09 DONE)**: expired offers auto-pause with paused_by='system' via RewardOffer::expireEnded() (on Api/Admin/Partner OfferController::index + claim, plus `offers:expire` every minute). System-locked = no edit/resume/approve anywhere ("the offer has ended and is locked by the system."); Flutter filters `isExpired` client-side too. Timezone is critical for this: APP_TIMEZONE=Asia/Kathmandu + DB_TIMEZONE=+05:45 (config/app.php + config/database.php) - UTC vs Nepal mismatch was the original "offer not showing" bug.
- **Khalti keys STILL never received (2026-08-09)**: eSewa signature verified (field=value format, official vector); Khalti keys pending from user - ask again. e2e gateway tests via ngrok on LAN IP pointing at APP_URL http://localhost:8000.
