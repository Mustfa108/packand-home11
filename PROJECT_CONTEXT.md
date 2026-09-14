# PROJECT_CONTEXT — HumaScale Backend (`packand-home11`)

**Last Updated:** 2026-09-14

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

## Important endpoints (new/updated)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/public/social-links` | Public footer links |
| GET/PUT | `/api/admin/settings/ai` | Gemini key/model |
| GET/PUT | `/api/admin/settings/social` | Social URLs |
| GET/POST | `/api/community-chat/messages` | User community chat |
| GET/POST | `/api/admin/community-chat/messages` | Admin community chat |
| GET | `/api/project-map` | Claimed/available project pins |
| POST | `/api/project-reviews` | Returns goals, features, how_it_works, ideal_steps |
| POST | `/api/report/{id}/regenerate` | Sync PDF rebuild for owner |
| POST | `/api/broadcasting/auth` | Echo channel auth |

## Recent major changes (2026-09-14)

- Switched PDF generation from DomPDF to mPDF for correct Arabic RTL
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
- `Message` model (1:1 DM) remains unused; community chat uses `community_messages`
- Hoppscotch not present; Postman collection under `docs/postman_collection.json`
- Realtime chat requires server DNS + TLS proxy for Reverb (see `docs/server-fix-prompt.md`)
- DomPDF package may still be installed but generation path uses mPDF
