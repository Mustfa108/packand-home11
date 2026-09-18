# PROJECT_CONTEXT — HumaScale Backend (`packand-home11`)

**Last Updated:** 2026-09-18

## Overview

Laravel API for HumaScale: questionnaire versioning, weighted assessments, Gemini AI analysis, project reviews with map claims, community chat (Reverb), and admin settings.

## Tech stack

- Laravel 13 / PHP 8.3
- Sanctum auth (users + admins)
- **mPDF** reports (Arabic RTL)
- Google Gemini (key from DB settings or `.env`)
- Laravel Reverb for realtime community chat

## Architecture notes

- Questionnaire = pillars + questions with `answer_type` (`likert` | `yes_no`)
- PDF: `PdfReportService` uses mPDF + `dejavusans` + `directionality=rtl`
- AI jobs: `ProcessAssessmentAI` on default queue — writes summary (Gemini or rule-based fallback), analysis, then **regenerates PDF** so الملخص الذكي is included
- Gemini credentials: `SiteSettingService` (admin panel) → fallback `GEMINI_API_KEY`
- Default model: **`gemini-3.6-flash`** (older 1.5 / 2.5 flash return 404 for new keys)
- Gemini helpers: `App\Support\GeminiHelpers` (JSON sanitize, secret redact); retries on 503/timeout
- AI analysis: `POST .../ai-analysis` with `force=true` regenerates when latest is `is_fallback`
- Admin statistics: date filter uses `COALESCE(prefix.completed_at, …)`; axis averages grouped by `pillar_name_ar` (no version duplicates)

### Questionnaire versioning

- Table: `assessment_versions` (`draft` | `published` | `archived`)
- Admin: create draft → edit axes/questions on draft → publish
- Users load published version only

## Important endpoints

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/admin/statistics` | Filters: from, to, type, size |
| POST | `/api/admin/assessment-versions` | Create draft |
| POST | `/api/assessments/{id}/ai-analysis` | Body optional `{ "force": true }` for fallback retry |
| POST | `/api/report/{id}/regenerate` | Sync PDF rebuild |

## Env required

- DB_*, `GEMINI_API_KEY`, `GEMINI_MODEL=gemini-3.6-flash`
- `GEMINI_TIMEOUT=45` (recommended), `GEMINI_MAX_RETRIES=2`
- `QUEUE_CONNECTION=database` (+ `php artisan queue:work`)
- `BROADCAST_CONNECTION=reverb` + Reverb keys
- `FRONTEND_URL` for CORS

## Known issues / decisions

- Named queues `ai`/`pdf` removed; default queue + afterResponse for initial PDF; AI job rebuilds PDF after summary
- DomPDF may still be installed but generation path uses mPDF
- Never log full Gemini request URLs (keys redacted via `GeminiHelpers::redactSecrets`)
