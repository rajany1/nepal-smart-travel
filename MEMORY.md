# MEMORY.md — Long-Term Memory

## Project

Nepal Smart Travel & Local Intelligence Platform — Laravel backend (`backend/`) + Flutter app (`mobile_app/`) + admin panel. Working directory: `C:\Users\ACER\Desktop\nepalsmarttravelandlocalintelligenceplatform`.

## Milestones

### 2026-08-04 — Logic audit complete: all 73 issues fixed
Ran a full logic audit (backend/admin BE-*, Flutter FL-*, integration IN-*) → `LOGIC_AUDIT_FIX_PLAN.md`. All 73 items are `[x]` (phases 1–4). The final session completed items 34–36. Full per-item changelog in `memory/2026-08-04.md`.

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

## Process notes (do these)

- Verification standard: `php -l` per changed PHP file; `flutter analyze` from `mobile_app/` must be 0 errors (354 pre-existing warnings/infos are the baseline); update `LOGIC_AUDIT_FIX_PLAN.md` checkbox + `memory/YYYY-MM-DD.md` per item.
- `php artisan test` passes (2 stock Pest examples only — no real test suite exists yet).
- PowerShell 5.1 env: `rg` unavailable (use Select-String/grep tool). PowerShell `.Replace()` + `Set-Content -Encoding UTF8` inserts a UTF-8 BOM into PHP files → strip bytes 0-2 (EF BB BF) after, or use the edit tool instead. Single-quoted PS strings can't contain `'`.
- Destructive ops: move to `C:\Users\ACER\AppData\Local\Temp\opencode\` backup instead of deleting.
- Per AGENTS.md: never commit unless explicitly asked; memory files = continuity.

## Open threads / ideas

- No real backend test suite — writing Pest feature tests for the fixed endpoints (auth OTP, report moderation visibility, ad impression dedupe, alert feed) would lock in the fixes.
- Admin-side UI polish for AI agent pages not in scope of the audit.
