# PROJECT_CONTEXT — HumaScale Backend (`packand-home11`)

**Last Updated:** 2026-09-17

## Overview

Laravel API for HumaScale: questionnaire versioning, weighted assessments, Gemini AI analysis, project reviews with map claims, community chat (Reverb), and admin settings.

## Tech stack

- Laravel 13 / PHP 8.3
- Sanctum auth (users + admins)
- **mPDF** reports (Arabic RTL) — replaced DomPDF generation path
- Google Gemini (key from DB settings or `.env`)
- Laravel Reverb for realtime community chat

## Architecture notes

- Questionnaire = pillars + questions with `answer_type` (`likert` | `yes_no`)
- Yes = score 5, No = score 1 (compatible with existing scoring)
- PDF: `PdfReportService` uses mPDF + `dejavusans` + `directionality=rtl`
- Manual PDF rebuild: `POST /api/report/{id}/regenerate`
- AI jobs: `ProcessAssessmentAI` on default queue
- Gemini credentials: `SiteSettingService` (admin panel) → fallback `GEMINI_API_KEY`
- Community chat: `community_messages` + `CommunityMessageSent` on private channel `community-chat`
- User model exposes `org_type_ar` / `org_size_ar` accessors (needed by organization profile API)
- AI analysis gated until user has `org_type` + `org_size`
- Uncaught API exceptions return Arabic JSON (`error_code: server_error`) instead of Laravel `Server Error`
- Gemini HTTP timeout default is 25s so analysis can fall back before PHP/proxy kill the request
- Sync AI analysis/chat/project-review wrap failures in try/catch, log to `ai` channel, and prefer rule-based fallback

### Questionnaire versioning (implemented)

- Table: `assessment_versions` (`draft` | `published` | `archived`)
- Pillars/questions carry `assessment_version_id`
- Past assessments keep `assessment_version_id` snapshot for scoring
- Admin flow: create draft (copies published) → edit axes/questions **in-place on draft only** → publish (archives previous published)
- `PATCH` on published questions returns 403 — create a draft first (no auto-version on edit)
- Users always load the current **published** version via assessment start/questions APIs
- Manager: `AssessmentVersionManager`

### Project review AI

- Sync `POST /api/project-reviews` (no queue)
- On Gemini/parse failure: rule-based fallback + `ai_is_fallback` / response `is_fallback`
- Frontend should show a fallback banner when `is_fallback` is true

## Important endpoints (new/updated)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/public/social-links` | Public footer links |
| GET/PUT | `/api/admin/settings/ai` | Gemini key/model |
| GET/PUT | `/api/admin/settings/social` | Social URLs |
| GET/POST | `/api/community-chat/messages` | User community chat |
| GET/POST | `/api/admin/community-chat/messages` | Admin community chat |
| GET | `/api/project-map` | Claimed/available project pins |
| POST | `/api/project-reviews` | Returns goals, features, how_it_works, ideal_steps, `is_fallback` |
| POST | `/api/report/{id}/regenerate` | Sync PDF rebuild for owner |
| POST | `/api/broadcasting/auth` | Echo channel auth |

## Recent major changes

- 2026-09-17: Gemini failure logs now include model, Google `error.message` / status, and truncated body (chat + analysis + NLG)
- 2026-09-17: API 500 handler returns Arabic JSON; AI analysis/project review catch exceptions and persist fallback when possible
- 2026-09-17: Default `GEMINI_TIMEOUT` reduced to 25s; `persistFallback()` on `GeminiAssessmentAnalysisService`
- 2026-09-17: Project review `ai_is_fallback` column + hardened Gemini JSON parse (`responseMimeType`, higher tokens)
- 2026-09-17: Server ops doc expanded for Gemini priority (admin vs `.env`) and queue worker requirements
- 2026-09-14: Switched PDF generation from DomPDF to mPDF for correct Arabic RTL
- Documented CORS env requirements in `config/cors.php`
- Added User `org_type_ar` / `org_size_ar` accessors
- Server ops prompt: `docs/server-fix-prompt.md`

## Run checklist

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan queue:work
php artisan reverb:start
```

## Env required

- DB_*, `GEMINI_API_KEY`, `GEMINI_MODEL`
- `BROADCAST_CONNECTION=reverb`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, host/port/scheme
- `FRONTEND_URL` (+ optional `FRONTEND_URL_ALT`) for CORS

## Known issues / decisions

- Named queues `ai`/`pdf` removed from dispatch path for reliability; use default queue + afterResponse for PDF
- Smart assessment summary has **no** rule-based fallback; needs queue worker + Gemini success to set `ai_generated_at`
- `Message` model (1:1 DM) remains unused; community chat uses `community_messages`
- Hoppscotch not present; Postman collection under `docs/postman_collection.json`
- Realtime chat requires server DNS + TLS proxy for Reverb (see `docs/server-fix-prompt.md`)
- DomPDF package may still be installed but generation path uses mPDF
