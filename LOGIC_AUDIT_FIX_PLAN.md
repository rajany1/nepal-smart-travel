# Logic Audit Report & Fix Plan â€” Nepal Smart Travel & Local Intelligence Platform

- **Date:** 2026-08-04
- **Scope:** Backend/Admin site (Laravel `backend/`), Flutter app (`mobile_app/lib/`), and the integration contract between them
- **Method:** Full source audit of routes, controllers, services, jobs, models, migrations (backend) and providers, features, core services, models (Flutter), cross-checked against each other
- **Note:** `admin-panel/` is an empty stub; the admin site is the Laravel backend (`app/Http/Controllers/Admin/*` + `routes/web.php`)

## Issue ID conventions

| Prefix | Area |
|--------|------|
| `BE-`  | Backend / Admin site (Laravel) |
| `FL-`  | Flutter app |
| `IN-`  | Combined / integration contract |

Severity: **HIGH** (feature-breaking / data loss / security), MEDIUM (wrong behavior), LOW (cosmetic / edge case / dead code).

---

## Summary

| Area | HIGH | MEDIUM | LOW | Total |
|------|------|--------|-----|-------|
| Backend / Admin (BE) | 7 | 15 | 5 | 27 |
| Flutter (FL) | 6 | 14 | 12 | 32 |
| Integration (IN) | 4 | 5 | 5 | 14 |
| **Total (deduplicated)** | **17** | **34** | **22** | **73** |

---

# Part 1 â€” Backend / Admin Site (Laravel)

## HIGH

### BE-01 â€” AI report-processing command calls a non-existent method
- **Location:** `backend/app/Console/Commands/ProcessPendingReports.php:16`
- **Problem:** The command calls `$orchestrator->runPendingReports()`. `AgentOrchestrator` (`backend/app/Services/Ai/AgentOrchestrator.php`) defines only `runPendingTasks`, `runAutoWork`, `executeTask`, `runOrchestrate`, `resolveHandler`.
- **Why wrong:** Running `php artisan ai:process-reports` throws `Call to undefined method` â€” the command is dead; no scheduled AI report processing ever happens.
- **Fix:** Rename the call to `runPendingTasks()` (verify the intended method), or implement `runPendingReports()` to filter `Report` rows needing AI analysis.

### BE-02 â€” Translation job writes to a non-existent column
- **Location:** `backend/app/Jobs/TranslateContent.php:64`
- **Problem:** Writes `'translated_value'` but the `model_translations` table (migration `2026_05_27_113900_create_model_translations_table.php`) has columns `field`, `value`, `source`.
- **Why wrong:** Every job execution that reaches the write throws `QueryException: Unknown column 'translated_value'`. Machine translations are never persisted.
- **Fix:** Change the insert key to `'value'`.

### BE-03 â€” Translation rows use full class name, readers use short name
- **Location:** `backend/app/Jobs/TranslateContent.php:47,60`
- **Problem:** Job stores `translatable_type` as `\App\Models\Place::class`, but every reader queries `'place'`, `'report'`, `'alert'`, `'place_review'`:
  - `TranslationService::attachToPlaces` (`backend/app/Services/TranslationService.php:32`)
  - `attachToModel` (`TranslationService.php:59`)
  - `attachToItems` (`TranslationService.php:84`)
  - `PlaceController::translations` (`backend/app/Http/Controllers/PlaceController.php:830`)
  - `TranslationHandler::create` (`backend/app/Services/Ai/Handlers/TranslationHandler.php:64`)
- **Why wrong:** Even after BE-02, rows written by the job (dispatched at `ReportController.php:373-374`, `PlaceController.php:83`, `PlaceController.php:741`) never attach to any API response.
- **Fix:** Store the short name (`$model->getTranslationKey()` or map classâ†’short name) in the job.

### BE-04 â€” Emergency push notifications never sent (named-argument Error)
- **Location:** `backend/app/Services/PushNotificationService.php:23-45`; callers at `ReportController.php:407`, `ReportController.php:482`, `AdminController.php:591`, `AlertController.php:189-198`
- **Problem:** `notifyNearbyUsers()` signature is `($title, $body, $latitude, $longitude, $radiusKm)` but all callers pass `message:` as a named argument. PHP 8 throws `Error: Unknown named parameter $message`.
- **Why wrong:** At the first three call sites the Error is swallowed by empty `catch` blocks â†’ **emergency/critical pushes are never sent at all**. At `AlertController.php:189-198` there is no try/catch â†’ HTTP 500 *after* the alert row is created and XP awarded â†’ clients retry â†’ duplicate alerts and double XP.
- **Fix:**
  1. Rename parameter `$body` â†’ `$message` (or change callers).
  2. Wrap the `AlertController` call in try/catch (or call after a DB transaction commit).
  3. Log errors in the empty catch blocks instead of swallowing.

### BE-05 â€” `notifyNearbyUsers` ignores lat/lng/radius â€” pushes to everyone
- **Location:** `backend/app/Services/PushNotificationService.php:35-37`
- **Problem:** Fetches *every* subscribed `PushToken` with no geo filter (the `push_tokens` table has no location columns).
- **Why wrong:** "Nearby" emergency alerts go to all users globally instead of within the requested radius.
- **Fix:** Either add `latitude`/`longitude`/`last_known_location` to `push_tokens` and filter, or document/remove the radius params. Consider sending via topics per district.

### BE-06 â€” Leaderboard crashes for `category=alerts`
- **Location:** `backend/app/Http/Controllers/LeaderboardController.php:77`
- **Problem:** Joins on `alerts.user_id`, but the `alerts` table uses `created_by`.
- **Why wrong:** Any authenticated request with `category=alerts` throws `SQLSTATE[42S22] Unknown column 'alerts.user_id'` â€” leaderboard 500s.
- **Fix:** Change join/filter to `alerts.created_by` (verify relationship in `Alert` model).

### BE-07 â€” API booking fails without `booked_at` (500)
- **Location:** `backend/app/Http/Controllers/Api/ConsumerController.php:70` (validation at :57 allows `'booked_at' => 'nullable|date'`)
- **Problem:** `Booking::create($data)` omits `booked_at` when client doesn't send it; column is NOT NULL without default.
- **Why wrong:** Valid requests without `booked_at` â†’ QueryException â†’ 500. The admin path defaults it (`Admin/TravelPartnerController.php:190`: `'booked_at' => $data['booked_at'] ?? now()`), proving the API path is missing the same default.
- **Fix:** `'booked_at' => $data['booked_at'] ?? now()` before create.

### BE-08 â€” AnalyzeReport auto-approves duplicates/fakes
- **Location:** `backend/app/Jobs/AnalyzeReport.php:36-44`; dispatched at `ReportController.php:372`
- **Problem:** Approval uses only `$result['action']` (default `'approve'`), ignoring `is_duplicate` / `is_legitimate` flags the prompt requires. Sibling `ReportManagerHandler.php:53-55` enforces them correctly.
- **Why wrong:** Fake/duplicate reports become public immediately. Also, AI-approved reports never award XP or bump `approved_reports` (only `AdminController::approveReport:579-584` does), so stats drift.
- **Fix:** Mirror `ReportManagerHandler` logic: require `action === 'approve' && is_legitimate === true && is_duplicate === false`; award XP/recount on approval; mark rejected duplicates accordingly.

## MEDIUM

### BE-09 â€” AI admin controllers have no authorization
- **Location:** `backend/app/Http/Controllers/Admin/AiAgentController.php`, `backend/app/Http/Controllers/Admin/AiAgentTaskController.php` (routed at `web.php:150-156` with only `['auth','status']`)
- **Problem:** Neither controller calls `requireAdmin` (unlike every other admin controller).
- **Why wrong:** Any logged-in regular user can create agents/tasks and trigger `report_manager`/`review_moderator` execution â€” control of the AI moderation pipeline.
- **Fix:** Add the same `requireAdmin` guard + `Permission::where('route_name', ...)` check used by sibling controllers.

### BE-10 â€” Review moderation not enforced; rejected reviews permanent
- **Location:** `backend/app/Http/Controllers/PlaceController.php:787-790` (`reviews`), `:753-754` (`addReview`); `backend/app/Jobs/ModerateReview.php:27`
- **Problem:**
  - `reviews()` returns all reviews with no `moderation_status` filter â€” rejected/flagged reviews are public.
  - `average_rating` / `total_reviews` recomputed over *all* reviews including rejected ones.
  - `ModerateReview.php:27` early-returns when `moderated_at` is set.
  - `addReview` uses `updateOrCreate` â€” a re-submitted rejected review is never re-moderated and stays rejected forever, dragging the rating.
- **Fix:** Filter by `moderation_status`/`moderated_at` in `reviews()` and rating computation; allow re-moderating re-submissions (e.g., clear `moderated_at` on resubmit or check updated_at > moderated_at).

### BE-11 â€” `approveReport` has no status guard; counters drift
- **Location:** `backend/app/Http/Controllers/AdminController.php:566-585` (approve), `:606-621` (reject)
- **Problem:** Approving an already-approved report (incl. AI auto-approved by BE-08) re-awards XP and re-increments `approved_reports`. `rejectReport` never increments `rejected_reports`.
- **Fix:** Guard `if ($report->status === 'approved') return`; increment `rejected_reports` on reject.

### BE-12 â€” `trackImpression` has no state validation / dedupe
- **Location:** `backend/app/Http/Controllers/AdController.php:43-53`
- **Problem:** Any authenticated user can increment `current_impressions` on any campaign indefinitely â€” including inactive, expired, or budget-exhausted campaigns; no dedupe per user/device.
- **Why wrong:** Sponsor budgets burned by repeat/abusive calls.
- **Fix:** Check campaign status, budget, dates; add per-user/per-device dedupe window.

### BE-13 â€” `gender` / `interest` silently dropped
- **Location:** `backend/app/Http/Controllers/ProfileController.php:178`, `backend/app/Http/Controllers/AuthController.php:140`; `backend/app/Models/User.php:124-148` (`$fillable`)
- **Problem:** Both fields are validated and passed to `$user->update($validated)` but are **not** in `$fillable` â†’ silently discarded; responses always return null.
- **Fix:** Add `gender`, `interest` to `User::$fillable` (migration columns already exist).

### BE-14 â€” `isPremium()` always returns true
- **Location:** `backend/app/Models/User.php:84-87`, `:110-121` (created hook), `:71-74` (`subscription()`)
- **Problem:** The `created()` hook auto-creates an **active free-plan** subscription with `ends_at = null`; `isActive()` accepts null `ends_at` â†’ every user is "premium". Meanwhile `ApiSubscriptionController::my()` (`SubscriptionController.php:36-38`, requires `ends_at > now()`) reports `is_premium: false`.
- **Why wrong:** The two endpoints contradict each other; `hasPremiumFeature` gates (if based on `isPremium()`) are meaningless.
- **Fix:** Decide semantics: either treat free-plan as non-premium (exclude free plan id / require paid plan or `ends_at` not null), or align `my()` with the hook behavior. Recommended: `isPremium()` requires subscription to a paid plan with `ends_at` in the future.

### BE-15 â€” Level progress math wrong (shows 100% for everyone above band floor)
- **Location:** `backend/app/Http/Controllers/Admin/AchievementController.php:156`; `backend/app/Services/AchievementService.php:124-132`
- **Problem:** `min($user->total_xp / $nextLevelXp, 1.0)` ignores cumulative XP of previous levels; `getNextLevelXp($level)` returns `xpForLevel($level)` instead of `xpForLevel($level+1)`.
- **Why wrong:** Any user with `total_xp >= nextLevelXp` shows 100% progress; API reports the current band's XP as "needed for next level" (e.g., level 5 shows 50, but level 6 needs 150).
- **Fix:** Compute progress as `(total_xp - cumulativeXp(level)) / (xpForLevel(level+1) - cumulativeXp(level))`; `getNextLevelXp` must return the *next* level's requirement.

### BE-16 â€” Commission math diverges by entry point
- **Location:** `backend/app/Http/Controllers/Api/ConsumerController.php:66-68` vs `backend/app/Http/Controllers/Admin/TravelPartnerController.php:182-184`
- **Problem:** API computes commission on the **pre-discount** amount and `reward_pool_share = commission * 0.5`; Admin computes on **post-discount** amount and `rewardShare = commission * 0.25`.
- **Why wrong:** The same booking yields different commission, platform revenue, and reward pool depending on who created it.
- **Fix:** Unify: pick one base (recommend post-discount) and one reward-share ratio; extract to a shared `BookingService`.

### BE-17 â€” Admin booking applies consumed/invalid shop codes
- **Location:** `backend/app/Http/Controllers/Admin/TravelPartnerController.php:164-176,195`
- **Problem:** No check for `is_used`, `consumed_at`, or existing `booking_id` when attaching a shop code; `else` branch (:173) applies `value_npr` as discount while the API path (`ShopService::applyToBooking:216-221`) gives 0 for the same item type.
- **Why wrong:** Already-consumed codes can be applied again (overwriting `booking_id`), granting a second discount; discount value inconsistent for identical codes.
- **Fix:** Validate code state before applying; unify discount resolution with `ShopService`.

### BE-18 â€” OSM auto-created places bypass approval
- **Location:** `backend/app/Http/Controllers/PlaceController.php:718`
- **Problem:** `addReview` creates the place with `is_active => true` and publishes instantly; user submissions (`store():67`) and admin-created places use `is_active => false` pending approval.
- **Fix:** Create OSM places with `is_active => false` (or a distinct `pending` state) until moderation.

### BE-19 â€” Store purchases always created `'completed'` â€” fulfill/cancel dead
- **Location:** `backend/app/Services/ShopService.php:83` (create), `:92-106` (fulfill), `:108-137` (cancel); `backend/app/Http/Controllers/StoreController.php:32`
- **Problem:** Purchases are unconditionally created with status `'completed'`; `fulfill()` and `cancel()` both require `isPending()` â†’ they can never run, always throw "Only pending purchasesâ€¦". The "Purchase submitted. An admin will process it shortly." branch is dead code (migration default is `pending`).
- **Fix:** Create purchases as `'pending'` (let admin fulfill), or remove the pending gating if fulfill/cancel is obsolete.

### BE-20 â€” AdCampaign admin bypasses route-level permission
- **Location:** `backend/app/Http/Controllers/Admin/AdCampaignController.php:18-22`
- **Problem:** `requireAdmin` omits the `Permission::where('route_name', $routeName)` check that every sibling controller performs (`StoreController:21-33`, `SponsorController:18-31`, `AchievementController:20-34`, `TravelPartnerController:25-33`, `PermissionController:17-â€¦`).
- **Fix:** Add the route-name permission check.

### BE-21 â€” Feed hides own pending reports (`$user` clobbered)
- **Location:** `backend/app/Http/Controllers/ReportController.php:117` (sanctum fallback) vs `:127` (`$user = $request->user()`)
- **Problem:** Line 127 reassigns `$user` from `$request->user()`, which is null on the public bearer-token route, so the `orWhere('user_id', $user->id)` clause (line 132) never fires for authenticated users.
- **Fix:** Do not reassign `$user`; keep the guard fallback (e.g., `$user = $request->user() ?? Auth::guard('sanctum')->user();`).

### BE-22 â€” `ReportController::show` exposes unmoderated reports
- **Location:** `backend/app/Http/Controllers/ReportController.php:241-249`
- **Problem:** No status filter â€” any pending or rejected report (including AI-rejected fakes) is fully public by ID, with reporter name/avatar.
- **Fix:** Only allow access to `approved` reports (or to the owner/admins).

### BE-23 â€” Pending reports surfaced as "emergency" alerts
- **Location:** `backend/app/Http/Controllers/AlertController.php:107`
- **Problem:** `Report::whereIn('status', ['approved', 'pending'])` puts unverified, possibly fake reports into the emergency feed; AI rejection only removes them asynchronously (and BE-08 may approve fakes).
- **Fix:** Only include `approved` reports (or gate emergency surfacing behind moderation).

## LOW

### BE-24 â€” Unguarded `suggested_priority` read
- **Location:** `backend/app/Services/Ai/Handlers/ReportManagerHandler.php:76`
- **Problem:** Reads `$result['suggested_priority']` unconditionally; line 65 guards the update but line 76 does not â†’ PHP warning + `report#5: approve ()` in output when the model omits the field.
- **Fix:** `$result['suggested_priority'] ?? null`.

### BE-25 â€” Admin live map excludes permanent alerts
- **Location:** `backend/app/Http/Controllers/AdminController.php:1204`; same pattern in `CustomerSupportHandler.php:151`
- **Problem:** `Alert::where('expires_at', '>=', now())` drops alerts with `expires_at = NULL`; public endpoints (`AlertController.php:42-45, 85-87`) include them â†’ admin map silently misses every permanent alert. (Also `>=` vs `>` boundary nit.)
- **Fix:** `where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })`.

### BE-26 â€” Off-by-one in suspicious-activity detection
- **Location:** `backend/app/Services/ModeratorService.php:87,99`
- **Problem:** `logSecurity` counts existing audit rows *before* writing the current one â†’ "10+ attempts" threshold is effectively 11; current attempt never counted.
- **Fix:** Count after writing, or compare `count >= 10` before insert with the insert counted.

### BE-27 â€” Duplicate, mutually-conflicting, no-op subscription commands
- **Location:** `backend/app/Console/Commands/AssignFreeSubscription.php` + `AssignFreeSubscriptions.php`
- **Problem:** Two commands (`subscription:assign-free` using `whereDoesntHave('subscription')`, `subscriptions:assign-free` checking active/trialing) with different semantics; since `User::created()` auto-creates an active free subscription, both commands can never match anyone.
- **Fix:** Delete one; if the free-subscription hook is intended, retire the commands.

### BE-28 â€” `review_xp` never awarded
- **Location:** `backend/app/Http/Controllers/PlaceController.php:682-774` (`addReview`); `ProfileController.php:258` (stats)
- **Problem:** `GameSetting` `review_xp` (default 3) is reported in the XP breakdown but no code path awards it â†’ stats breakdown always inflated vs real XP.
- **Fix:** Award `review_xp` once per review (consider first review per place) in `addReview`.

### BE-29 â€” AI moderation approval path skips XP/stats
- **Location:** `backend/app/Jobs/AnalyzeReport.php` vs `AdminController::approveReport:579-584`
- **Problem:** When AI approves a report, no XP is awarded and `approved_reports` is not bumped, unlike manual approval.
- **Fix:** Extract XP/stats award logic to a shared service and call from both paths.

---

# Part 2 â€” Flutter App

## HIGH

### FL-01 â€” Offline-created places silently dropped
- **Location:** `mobile_app/lib/core/services/sync_service.dart:84-86`; `mobile_app/lib/features/places/add_place_screen.dart:147`
- **Problem:** `_processSyncItem` has the upload commented out (`// await _api.createPlace(payload);` line 85) and unconditionally calls `_offlineDb.markSyncCompleted(id)` (line 86).
- **Why wrong:** The item is deleted from the queue with no server call â€” the user is told "Place saved offline. Will sync when online" but the data is permanently lost.
- **Fix:** Restore the upload call before `markSyncCompleted`; only mark complete on success (or retry counter).

### FL-02 â€” Token refresh is dead; any 401 force-logs-out
- **Location:** `mobile_app/lib/core/api/api_client.dart:513-544`; `mobile_app/lib/providers/auth_provider.dart:140-142`; backend `AuthController.php:398-409`
- **Problem:** Backend never issues `refresh_token` (login/register return only `access_token`); `session.getRefreshToken()` is always null â†’ interceptor falls through to `clearSession()`. Even if stored, `/auth/refresh` is behind `auth:sanctum` and `refreshToken()` resolves `$request->user()` â€” it validates the access token, and the client sends the stored token as Bearer to a sanctum-guarded route â†’ 401 anyway.
- **Why wrong:** Any server-side revocation (password reset, admin logout, token expiry) instantly logs the user out with no recovery.
- **Fix:** Options: (a) implement real refresh tokens in Laravel Sanctum (issue `refresh_token` on login/register, separate unauthenticated `/auth/refresh` route that validates the refresh token), or (b) make the client retry-login / surface "session expired" instead of silent logout.

### FL-03 â€” Report search box does nothing
- **Location:** `mobile_app/lib/features/reporting/reports_list_screen.dart:120-122, 251`; `mobile_app/lib/core/api/api_client.dart:282-305` (`getReports`)
- **Problem:** `_onFilterChanged(String query, int? categoryId)` ignores `query`; `ReportProvider` has no search state; `getReports` accepts no search param.
- **Fix:** Add `search`/`query` param to `getReports` + backend `ReportController::index` filter (`title/content LIKE`), or filter client-side over loaded reports.

### FL-04 â€” Map controller bound to invisible satellite layer
- **Location:** `mobile_app/lib/features/places/nearby_map_screen.dart:675-677` (with `:540-568`)
- **Problem:** Standard + satellite `FlutterMap` instances are both permanently mounted (AnimatedOpacity 0 keeps children in tree) and share one `_mapController`. flutter_map allows one attachment: debug asserts; in release the controller binds to the **second** (satellite) map.
- **Why wrong:** Zoom FABs (`:819-837`), my-location (`:909/918`), route `fitCamera` (`:501`), place-tap `move` (`:441`) drive the invisible layer while the visible map doesn't react; both tile layers download continuously.
- **Fix:** Use two controllers, or use `MapOptions` on a single map and swap tile layers, or `Offstage`/conditionally mount only the active map.

### FL-05 â€” "Delete Account" never deletes anything
- **Location:** `mobile_app/lib/features/profile/settings_screen.dart:307-333`; `mobile_app/lib/providers/auth_provider.dart:337-360` (plain logout); `api_client.dart` (no deletion endpoint)
- **Problem:** Confirm dialog only calls `logout()` + `pushReplacementNamed('/login')`. The promise "All your dataâ€¦ will be permanently removed" is never fulfilled.
- **Fix:** Add `DELETE /users/me` (backend: delete or anonymize user + related data, revoke tokens) and call it before logout.

### FL-06 â€” Photo-GPS verification is dead code
- **Location:** `mobile_app/lib/features/reporting/reports_list_screen.dart:1011, 1029, 1033`; `mobile_app/lib/providers/report_provider.dart:224-264` (esp. `:233-234, 254-264`)
- **Problem:** `captureLocationAfterPhoto()` captures GPS right after the photo; `_submitReport` then fetches a *second* location (`:1029`) and calls `_captureLocationService.clear()` (`:1033`) â€” the captured coordinates are never read. `submitReport` declares `captureLatitude/captureLongitude` (doc says they're sent as photo verification) but never serializes them into FormData (`:254-264`).
- **Why wrong:** Backend `gps_verification` check has nothing to verify against.
- **Fix:** Serialize `captureLatitude`/`captureLongitude` into the FormData; use the post-photo capture (falling back to current location); remove the double fetch/clear.

## MEDIUM

### FL-07 â€” Map filter selections discarded
- **Location:** `mobile_app/lib/features/places/nearby_map_screen.dart:930-935`; `mobile_app/lib/features/places/nearby_places_screen.dart:~350`
- **Problem:** `_onFilterTap` passes `onApply: (filters) { ...; _fetchPlacesForViewport(); }` â€” the `filters` argument (category, radius, verified, featured, search) is never read; refetch uses same params.
- **Fix:** Apply the returned filters to the fetch parameters/state.

### FL-08 â€” 60s auto-refresh destroys pagination
- **Location:** `mobile_app/lib/providers/report_provider.dart:335-342, 154-172, 120`
- **Problem:** `startAutoRefresh` calls `fetchReports(... refresh: true)` â†’ replaces `_reports` with page 1 and resets `_currentOffset` (`:172`) â€” items from `fetchMoreReports` vanish on the next poll. The `_isFetching` guard (`:120`) also silently no-ops pull-to-refresh/load-more during a fetch.
- **Fix:** On poll, fetch page 1 and merge/dedupe rather than replace (or pause polling while paging).

### FL-09 â€” Email-verification flow is unreachable
- **Location:** `mobile_app/lib/main.dart:141-143` (route registered); `login_screen.dart:31-38,49-52` (navigates straight to `/profile-completion`/`/home`); `auth_provider.dart:154/186/222` (`_isEmailVerified = user.status == 'active'`)
- **Problem:** No caller for `/email-verification`; `status` is `'active'` by default server-side â†’ `_isEmailVerified` always true â†’ unverified users fully bypass verification.
- **Fix:** (Also see IN-08.) Redirect unverified users to the verification screen; gate on a real `email_verified_at`/verification flag.

### FL-10 â€” RangeError on empty strings
- **Locations:**
  - `mobile_app/lib/features/bookings/my_bookings_screen.dart:407` â€” `(partner?.name ?? '?')[0]` crashes on empty-string name (`??` guards null only)
  - `mobile_app/lib/features/sponsors/sponsors_screen.dart:100` â€” `s['name']?[0]`
  - `mobile_app/lib/features/store/widgets/store_order_card.dart:95` â€” `purchase.status[0].toUpperCase()` on empty status
  - `store_order_card.dart:46` â€” `purchase.createdAt.substring(0, 10)` on empty `created_at`
- **Fix:** Guard for empty strings (`(name == null || name.isEmpty) ? '?' : name[0]`), safe substring (`take(10)`), etc.

### FL-11 â€” "Copy code" button never copies
- **Location:** `mobile_app/lib/features/store/widgets/store_item_card.dart:547-553`
- **Problem:** Shows "Code copied!" but has no `Clipboard.setData` call (comment on line 549 concedes it).
- **Fix:** Add `Clipboard.setData(ClipboardData(text: code))` before showing the toast.

### FL-12 â€” Sync queue is never processed; unhandled items retry forever
- **Location:** `mobile_app/lib/core/services/sync_service.dart:26` (`startMonitoring`), `:94` (`syncNow`); `offline_db_service.dart:268-271`
- **Problem:** Zero callers for `startMonitoring()`/`syncNow()` â€” the queue is never drained (map screen's "N pending sync" badge at `nearby_map_screen.dart:993-1012` polls count but nothing consumes it). Only `place/create` has a branch; other entity/operations stay `pending` and re-pick on every attempt.
- **Fix:** Call `startMonitoring()`/`syncNow()` on app resume/network regain; handle or skip unknown entity types; add retry cap + failure flag.

### FL-13 â€” Sync-count stream recreated on every build
- **Location:** `mobile_app/lib/features/places/nearby_map_screen.dart:953, 993-1012`
- **Problem:** `_syncCountStream()` invoked inside `build` â†’ new broadcast controller + new `_pollSyncCount` loop per rebuild; old loops keep running â†’ accumulating timers and duplicate events.
- **Fix:** Create the stream/controller once in `initState` (or via a field initializer / `late final`).

### FL-14 â€” Assistant always assumes Kathmandu
- **Location:** `mobile_app/lib/features/assistant/assistant_screen.dart:59-63`; error indexing at `:67-68`
- **Problem:** `chatWithAssistant` called with hard-coded `AppConstants.defaultLatitude/defaultLongitude` instead of user GPS; `response.data['data']['reply']` indexes a non-`data` error body â†’ throws and misreported as "couldn't reach the server".
- **Fix:** Use `LocationService` (with permission fallback) for lat/lng; parse reply safely with a `data`-shape check.

### FL-15 â€” Silent submit failure when location missing
- **Location:** `mobile_app/lib/features/reporting/reports_list_screen.dart:1031`
- **Problem:** `if (_lat == null || _lng == null) { ...; return; }` â€” no snackbar/explanation; user taps Submit and nothing happens.
- **Fix:** Show an inline/snackbar message explaining location is required.

### FL-16 â€” Interceptor overwrites Authorization on refresh
- **Location:** `mobile_app/lib/core/api/api_client.dart:525-527`
- **Problem:** `onRequest` overwrites `Authorization` with the still-unexpired access token when a 401 arrives for a locally-valid token (e.g., revoked) â†’ refresh call guaranteed to fail (compounds FL-02).
- **Fix:** During refresh, send a dedicated `refresh_token` (header/body) or skip the interceptor for the refresh call.

### FL-17 â€” 'System' theme silently becomes Light
- **Location:** `mobile_app/lib/features/profile/settings_screen.dart`
- **Problem:** `settings['theme'] == 'dark' ? 'Dark' : 'Light'` â€” the 'system' option (offered in the UI) is never honored.
- **Fix:** Map `system` â†’ `ThemeMode.system` / "System" label.

### FL-18 â€” Mixed int/string place ids break selection
- **Location:** `mobile_app/lib/providers/place_provider.dart` + `mobile_app/lib/features/places/nearby_map_screen.dart:1209`
- **Problem:** Admin places get int ids; OSM places get `'node/123'`-style strings (backend `parseOsmElements`). `_selectedPlace?.id == place.id` compares int vs String â†’ false negatives â†’ selected-marker highlight never matches.
- **Fix:** Normalize ids (`id.toString()`) when comparing.

### FL-19 â€” Comment submit appends global reports to "near you" feed
- **Location:** `mobile_app/lib/features/reporting/reports_list_screen.dart:829`
- **Problem:** `fetchReports(refresh: false, lat: null, lng: null, radiusKm: null)` appends location-unfiltered reports to the near-you feed.
- **Fix:** Pass the same location params as the feed's current filter.

### FL-20 â€” Emergency tab only sees page 1
- **Location:** `mobile_app/lib/features/reporting/reports_list_screen.dart:418`
- **Problem:** `provider.reports.where((r) => r.isEmergency)` filters only the loaded page (10 items) with no pagination â€” later emergency reports never surface.
- **Fix:** Load emergency reports via a dedicated paged query (backend `is_emergency` filter) or infinite-scroll the filtered list.

## LOW

### FL-21 â€” Route ETA ignores real OSRM duration
- **Location:** `mobile_app/lib/features/places/nearby_map_screen.dart:492`
- **Problem:** `'duration': distKm / 30 * 60` is a 30 km/h guess; OSRM returns real `r['duration']` in seconds (would be `/60`).
- **Fix:** Use OSRM `duration` (fallback to estimate when absent).

### FL-22 â€” Missing coordinates default to (0,0)
- **Locations:** `mobile_app/lib/core/models/report.dart:66-67`; `mobile_app/lib/core/services/offline_db_service.dart:126-128, 158-160`
- **Problem:** `double.tryParse(...) ?? 0.0` â†’ report maps center on the Gulf of Guinea when backend omits lat/lng; offline cache writes (0,0) geohash rows, bbox queries return garbage.
- **Fix:** Keep coordinates nullable; skip markers / fall back to user location.

### FL-23 â€” Sponsors website chip is a dead button
- **Location:** `mobile_app/lib/features/sponsors/sponsors_screen.dart:115`
- **Problem:** `onPressed: () {/* launch URL */}` never launches.
- **Fix:** Use `url_launcher` (`launchUrl(Uri.parse(...))`) with HTTPS normalization.

### FL-24 â€” Assistant place-detail fallback pushes lat/lng 0
- **Location:** `mobile_app/lib/features/assistant/assistant_screen.dart:305-310`
- **Problem:** `Place(id: ..., latitude: 0, longitude: 0, ...)` â€” if the details fetch fails, the map shows (0,0).
- **Fix:** Use the assistant's known lat/lng (fix FL-14) instead of 0.

### FL-25 â€” Reaction popup ignores the pressed button
- **Location:** `mobile_app/lib/features/reporting/reports_list_screen.dart:610-611`
- **Problem:** Overlay is `Positioned(left: 16, bottom: height*0.5-60)`; `CompositedTransformFollower` has no effect there â†’ long-press on a card at top shows popup at screen bottom.
- **Fix:** Position the popup relative to the pressed card's RenderBox (`RelativeRect` / overlay entries).

### FL-26 â€” Reply-stripping is fragile
- **Location:** `mobile_app/lib/features/reporting/reports_list_screen.dart:820`
- **Problem:** `content.replaceFirst('@$_replyingToUserName ', '')` strips only the exact prefix with trailing space; edited prefix posts raw `@name` as comment text.
- **Fix:** Strip the `@name` token before the first space/regex, or track reply separately from text.

### FL-27 â€” My-location "tracking" moves once only
- **Location:** `mobile_app/lib/features/places/nearby_map_screen.dart:906-921`
- **Problem:** `_isTracking` set true but nothing follows the user; button is a one-shot jump.
- **Fix:** Subscribe to position stream while `_isTracking`; cancel on toggle-off.

### FL-28 â€” `context.read` inside `dispose()`
- **Locations:** `reports_list_screen.dart:72`, `profile_screen.dart:40`, `place_details_screen.dart:44-47`
- **Problem:** Reading providers in `dispose()` violates Provider contract; can throw if element unmounts during app-level teardown (e.g., force logout mid-frame).
- **Fix:** Capture needed data before dispose, or use `WidgetsBinding.instance.addPostFrameCallback`.

### FL-29 â€” IndexedStack keeps all 5 tabs alive
- **Location:** `mobile_app/lib/features/map/home_screen.dart:70-106`
- **Problem:** Hidden-tab timers (report 60s auto-refresh, alerts 30s poll) keep running and firing network calls while user is on another tab.
- **Fix:** Pause polling when tab loses visibility (`IndexedStack` index check) or lazy-build tabs.

### FL-30 â€” `recently_viewed` prune by `place_id` can exceed 50 rows
- **Location:** `mobile_app/lib/core/services/offline_db_service.dart:313-317`
- **Problem:** `DELETE ... WHERE place_id NOT IN (SELECT place_id ... LIMIT 50)` keeps 50 distinct places, not 50 rows; with duplicates (no unique constraint) table can exceed 50 rows.
- **Fix:** Prune by row id/`viewed_at` with `LIMIT` on row count.

### FL-31 â€” Empty phone/website sent as `''`
- **Location:** `mobile_app/lib/features/places/add_place_screen.dart:112-122`
- **Problem:** Always-included `'phone'`/`'website'` empty strings hit backend validation for those fields when blank.
- **Fix:** Omit the keys when empty (`if (v.isNotEmpty) { body['phone'] = v; }`).

### FL-32 â€” Carried-over confirmed issues (prior audit, still present)
- `mobile_app/lib/providers/ad_provider.dart:30-31` â€” banner ads listed twice (duplicate entries in list).
- `mobile_app/lib/features/partners/partners_list_screen.dart` â€” `params['district']` built but never passed to `getPartners` (`api_client.dart:380-385` accepts it) â€” district filter silently ignored (see IN-01).
- `mobile_app/lib/features/subscriptions/subscription_plans_screen.dart:46` â€” `as int?` cast on price; if backend sends string/double â†’ crash or wrong value. Verify backend type and use `num`/`double`.
- `mobile_app/lib/core/services/camera_service.dart` â€” gallery option always disabled.
- `mobile_app/lib/features/alerts/alerts_screen.dart` â€” 30s poll + snackbar diff causes repeated notifications.
- `mobile_app/lib/services/push_notification_service.dart` â€” navigator key never set â†’ FCM taps can't navigate.

---

# Part 3 â€” Combined / Integration Contract

## HIGH

### IN-01 â€” Partners screen can never load (paginator vs list shape)
- **Flutter:** `mobile_app/lib/features/partners/partners_list_screen.dart:38-39` â€” `_partners = res.data['data'] as List<dynamic>;`
- **Backend:** `backend/app/Http/Controllers/Api/ConsumerController.php:17-24` â€” `partners()` returns `['success'=>true, 'data'=>$paginator]`; `data` is a Laravel paginator object `{current_page, data: [...], ...}`.
- **Why wrong:** Cast throws `TypeError` â†’ caught â†’ partners screen silently empty.
- **Fix:** Flutter reads `res.data['data']['data']` (paginator-aware, like `booking_provider.dart:43-49`), or backend returns a plain array (like `sponsors()`).

### IN-02 â€” Password reset is a dead end (missing `reset_token`)
- **Flutter:** `mobile_app/lib/providers/auth_provider.dart:487` â€” `return response.data['reset_token'] as String?;`; `forgot_password_screen.dart:31-34` only proceeds when `resetToken != null`.
- **Backend:** `AuthController.php:351-366` â€” `forgotPassword()` returns `{success, message}` only; the Laravel Password broker token goes to email, never to the app.
- **Why wrong:** `resetToken` is always null â†’ "Enter Reset Code" UI never appears â†’ in-app password reset impossible. (Backend `resetPassword` `:368-396` correctly expects `token`, `email`, `password`, `password_confirmation`.)
- **Fix:** Backend returns the token (dev mode) or include the token in a deep link / user-entered code flow.

### IN-03 â€” `/profile-setup` opens wrong screen AND never marks profile completed
- **Flutter:** `mobile_app/lib/main.dart:116-118` â€” both `/profile-edit` and `/profile-setup` route to `ProfileEditScreen`; `profile_edit_screen.dart:109` saves via `PUT /users/me`.
- **Backend:** `AuthController.php:127-147` â€” `update()` never sets `profile_completed = true`; only `POST /auth/complete-profile` (`AuthController.php:304-338`) does.
- **Why wrong:** Users completing via this path (reached from `profile_screen.dart:396`, `email_verification_screen.dart:50`) stay `profile_completed=false` â†’ app keeps gating them; `ProfileCompleted` middleware keeps rejecting guarded endpoints.
- **Fix:** Route `/profile-setup` to the completion flow (or set flag in save), or have the edit save call `/auth/complete-profile`.

### IN-04 â€” Interceptor force-logs-out on ANY 403, including `PROFILE_INCOMPLETE`
- **Flutter:** `mobile_app/lib/core/api/api_client.dart:546-551` â€” `if (statusCode == 403) { clearSession(); }` with no discrimination.
- **Backend:** `ProfileCompleted` middleware returns 403 `{code: 'PROFILE_INCOMPLETE', missing_fields: [...]}`; `CheckUserStatus` returns 403 with `requires_logout`.
- **Why wrong:** A profile-incomplete (but valid) user hitting a `profile.completed`-guarded endpoint gets their session wiped instead of being guided to completion. Latent today (no current callers), but any future guarded call (e.g., `createAlert`, `createBooking` for some users) will trigger it.
- **Fix:** Only clear session when `code == 'ACCOUNT_BANNED'/'ACCOUNT_SUSPENDED'` or `requires_logout == true`; for `PROFILE_INCOMPLETE`, navigate to `/profile-setup` instead.

## MEDIUM

### IN-05 â€” `expertise_regions` silently dropped on profile edit
- **Flutter:** `profile_edit_screen.dart:52`, `profile_setup_screen.dart:126` send `expertise_regions` to `PUT /users/me`.
- **Backend:** `AuthController.php:131-138` â€” `update()` validates only `name, phone, bio, avatar, gender, interest`; `expertise_regions` discarded (only `ProfileController@update`, `PUT /profile`, `ProfileController.php:168-176`, accepts it).
- **Fix:** Add `expertise_regions` to `AuthController@update` validation/storage, or have Flutter call `PUT /profile`.

### IN-06 â€” Feed never shows own pending reports
- **Backend:** `ReportController.php:117` vs `:127` â€” `$user` clobbered (see BE-21).
- **Flutter:** `report_provider.dart:136-146` â€” default feed fetch has no `status` param â†’ only approved reports appear; own pending reports invisible in feed (My Reports tab via `/reports/my` works, `report_provider.dart:199`).
- **Fix:** Keep the sanctum fallback (BE-21); then authenticated users' pending reports show in feed.

### IN-07 â€” Admin place images 404 in app (relative URLs)
- **Backend:** `PlaceController.php:151, 224, 321, 821` â€” `images` = raw `image_url` relative paths (`places/{id}/...`); contrast `ReportController.php:690` which uses full `asset('storage/...')` URLs.
- **Flutter:** `mobile_app/lib/widgets/image_carousel_widget.dart:88-89` â€” `CachedNetworkImage(imageUrl: raw)` with no base-URL resolution.
- **Fix:** Prefix with `asset('storage/...')` server-side (consistent with reports) or resolve in Flutter (strip leading `/storage` and prepend base URL).

### IN-08 â€” Email verification is a dead flow end-to-end
- **Flutter:** `auth_provider.dart:64,105,154,186,222,280,414` â€” `_isEmailVerified = user.status == 'active'`; backend register never verifies email and `status` defaults to `active`.
- **Backend:** `AuthController.php:456-458` â€” `resendVerification()` caches an OTP but the email send is a TODO comment; `verifyEmail()` (`:426-432`) requires an OTP users never receive.
- **Fix:** Wire the OTP email send (or remove the flow), and gate `_isEmailVerified` on a real `email_verified_at` / OTP flag. Also un-reach FL-09.

### IN-09 â€” OSM place details 404 for unreviewed places
- **Backend:** `PlaceController.php:809-817` â€” `show()` returns 404 `success:false` for `osm_*` ids not yet in DB.
- **Flutter:** `place_details_provider.dart:26-35` â€” surfaces generic "Failed to load place details"; users tapping a fresh OSM POI (the majority on the combined map) get an error instead of "be the first to review".
- **Fix:** Return an OSM-sourced placeholder payload (from the cache/request body) instead of 404.

## LOW

### IN-10 â€” `getUserReputation` calls a route that doesn't exist
- **Flutter:** `mobile_app/lib/core/api/api_client.dart:134-136` â€” `GET /users/{id}/reputation`.
- **Backend:** `backend/routes/api.php` only defines `/users/{id}/profile` and `/users/me`.
- **Fix:** Add the backend route or remove the dead client method.

### IN-11 â€” `ProfileStats.fromJson` crashes on nested `xp_breakdown.rates`
- **Flutter:** `mobile_app/lib/providers/profile_provider.dart:157-161` â€” `(v as num).toInt()` on every value.
- **Backend:** `ProfileController.php:263-267` nests a `rates` map â†’ `TypeError`, swallowed at `profile_provider.dart:436` â†’ stats always null.
- **Fix:** Handle nested maps (`v is num ? v.toInt() : 0`) or skip non-numeric entries.

### IN-12 â€” `alerts_by_severity` counts a severity that never exists
- **Backend:** `ProfileController.php:252` counts `'low'`; `AlertController::store` validation (`AlertController.php:172`) only allows `info, medium, high, critical` â€” `info` alerts never counted.
- **Fix:** Count `'info'` (or align the enum list).

### IN-13 â€” `verification_tick` enum mismatch â†’ "NONE TICK" label
- **Flutter:** `mobile_app/lib/core/models/user.dart:75` â€” default `'gray'`.
- **Backend:** sends `'none'` (`ProfileController.php:146`, `LeaderboardController.php:120`; `me()` omits the key).
- **Why wrong:** `VerificationBadge` (`verification_badge.dart:27,59`) renders "NONE TICK" for unverified users instead of "GRAY TICK".
- **Fix:** Align enum values (recommend backend sends `gray`/`gold`/`platinum`, Flutter default `'none'` â†’ treat as gray).

### IN-14 â€” Alert creation exists server-side only
- **Flutter:** `api_client.dart:158-160` â€” `createAlert()` defined, never called; `getNearbyAlerts()` (`:154-156`) and parameterless `getWeatherGrid()` (`:346-348`) also unused (map screen calls dio directly at `nearby_map_screen.dart:2172`).
- **Backend:** `POST /alerts` sits behind `profile.completed` (`routes/api.php:127-129`) â€” would trigger IN-04's 403-logout if ever wired up.
- **Fix:** Either implement the UI + fix IN-04 first, or remove dead client methods.

---

# Part 4 â€” Recommended Fix Order (one by one)

Fix in this order â€” each item unblocks/dedupes later ones. Check boxes as completed.

## Phase 1 â€” Critical breakage (do first)

- [x] **1. IN-01** Partners list paginator shape mismatch â†’ partners screen renders.
- [x] **2. BE-04/BE-05** Push notification pipeline (named-arg Error + radius ignored + uncaught 500 in `AlertController`) â†’ alerts XP no longer double-awarded; emergency pushes actually send.
- [x] **3. FL-01** Sync service must actually upload offline places before marking complete.
- [x] **4. FL-02 / IN-04** Auth: real refresh-token handling + 403 discrimination (stop silent logout, stop session wipe on PROFILE_INCOMPLETE).
- [x] **5. BE-02/BE-03** Translation job: correct column name + short `translatable_type`.
- [x] **6. BE-01** Fix `ProcessPendingReports` command method name. â€” **VERIFIED NOT A BUG**: `AgentOrchestrator::runPendingReports()` exists (`AgentOrchestrator.php:62`) and `php artisan ai:process-reports` runs successfully. No change needed.

## Phase 2 â€” Data correctness

- [x] **7. BE-21 / IN-06** `ReportController` `$user` fallback â†’ own pending reports visible.
- [x] **8. BE-07** Default `booked_at` in API booking create.
- [x] **9. BE-08** `AnalyzeReport` enforce duplicate/legitimacy flags + award XP.
- [x] **10. BE-10** Review moderation filter + re-moderation on resubmit.
- [x] **11. BE-11** Status guards on approve/reject + `rejected_reports` increment.
- [x] **12. BE-13** Add `gender`/`interest` to `User::$fillable`.
- [x] **13. BE-14** Fix `isPremium()` semantics (align with `my()`).
- [x] **14. BE-15** Level-progress + next-level-XP math.
- [x] **15. BE-16** Unify commission math (shared service).
- [x] **16. BE-17** Validate shop-code state in admin bookings.
- [x] **17. BE-19** Store purchases: create as `pending` or align fulfill/cancel.
- [x] **18. BE-28/BE-29** Award `review_xp`; XP on AI approval path.

## Phase 3 â€” Map / UX logic

- [x] **19. FL-04** Fix dual-map controller binding.
- [x] **20. FL-07** Apply map filter selections.
- [x] **21. FL-12/FL-13** Wire sync queue processing; fix stream recreation.
- [x] **22. FL-03** Implement report search.
- [x] **23. FL-08** Auto-refresh must not clobber pagination.
- [x] **24. FL-05** Implement real Delete Account (backend + client).
- [x] **25. FL-06** Wire photo-GPS capture into submit.
- [x] **26. IN-03** Fix `/profile-setup` route + `profile_completed` flag.
- [x] **27. IN-02** Password reset token flow.
- [x] **28. IN-07** Place image base URL.

## Phase 4 â€” Cleanup (medium/low, batchable)

- [x] **29. FL-09 / IN-08** Email verification flow end-to-end. Register now generates+caches+emails OTP (Mail::raw, try/catch) and returns otp; resendVerification emails it too; me() exposes email_verified_at; UserModel parses emailVerifiedAt; _isEmailVerified gates on emailVerifiedAt != null (7 sites); register routes to /email-verification (reachable now); OTP auto-prefilled from response (dev bridge).
- [x] **30. FL-14/FL-24** Assistant: real location + safe reply parse + non-zero fallback. Uses LocationService coords (defaults fallback); safe reply/actions parse.
- [x] **31. FL-10/FL-11/FL-23/FL-25/FL-26** String guards, copy button, URL launch, popup positioning, reply strip.
- [x] **32. FL-15/FL-16/FL-17/FL-18/FL-19/FL-20** Silent failure feedback, refresh header, system theme, id normalization, feed location params, emergency pagination.
- [x] **33. FL-21/FL-22/FL-27/FL-28/FL-29/FL-30/FL-31/FL-32** ETA from OSRM, nullable coords, tracking stream, dispose reads, tab polling, viewed-prune, empty fields, carried-over nits.
- [x] **34. BE-06/BE-09/BE-12/BE-18/BE-20/BE-22/BE-23** Leaderboard join, AI admin auth, impression validation, OSM approval bypass, AdCampaign permission, report show guard, emergency feed filter.
- [x] **35. BE-24â†’BE-27, BE-17** Low-severity backend nits.
- [x] **36. IN-05, IN-09â†’IN-14** Integration low items.

## Verification steps after each fix

1. **Backend:** `php artisan test` (if tests exist) + manual curl of affected endpoint (see `backend/routes/api.php` + controller).
2. **Flutter:** `flutter analyze` in `mobile_app/`; run affected screen flows on emulator.
3. **Integration:** exercise the fixed flow end-to-end (app â†’ backend) with real payloads; compare response JSON shapes against the parse code.
4. Log each completed fix in `memory/YYYY-MM-DD.md`.
