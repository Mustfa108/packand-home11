# PROJECT_CONTEXT — HumaScale Backend (`packand-home11`)

**Last Updated:** 2026-09-14

## Overview

Laravel API for HumaScale: questionnaire versioning, weighted assessments, Gemini AI analysis, project reviews with map claims, community chat (Reverb), and admin settings.

## Tech stack

- Laravel 13 / PHP 8.3
- Sanctum auth (users + admins)
- DomPDF reports
- Google Gemini (key from DB settings or `.env`)
- Laravel Reverb for realtime community chat

## Architecture notes

- Questionnaire = pillars + questions with `answer_type` (`likert` | `yes_no`)
- Yes = score 5, No = score 1 (compatible with existing scoring)
- PDF: `GenerateAssessmentPdf` runs **afterResponse without ShouldQueue** (no queue worker required for PDF)
- Manual PDF rebuild: `POST /api/report/{id}/regenerate`
- AI jobs: `ProcessAssessmentAI` on default queue
- Gemini credentials: `SiteSettingService` (admin panel) → fallback `GEMINI_API_KEY`
- Community chat: `community_messages` + `CommunityMessageSent` on private channel `community-chat`
- Project map: `project_reviews.lat/lng` + `is_claimed`
- Project AI detail fields: `ai_goals`, `ai_how_it_works`, `ai_features`, `ai_full_summary_ar`, `ai_ideal_steps`

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

- Fixed PDF stuck state by removing ShouldQueue from PDF job
- Added PDF regenerate endpoint + frontend retry
- Enriched project AI analysis with goals and how_it_works

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
- `FRONTEND_URL` for CORS

## Known issues / decisions

- Named queues `ai`/`pdf` removed from dispatch path for reliability; use default queue + afterResponse for PDF
- `Message` model (1:1 DM) remains unused; community chat uses `community_messages`
- Hoppscotch not present; Postman collection under `docs/postman_collection.json`
