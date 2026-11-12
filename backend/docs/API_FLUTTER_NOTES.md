# API notes for the Flutter app

Status: **final for Phase 9 / backend Phase 10** — every endpoint below is implemented and was exercised with
curl against the dev database. Endpoints marked **NEW** were added in this pass (additive: no existing request or
response field changed). Full machine-readable schemas: **`/docs/api`** (Scramble UI) and **`/docs/api.json`**
(OpenAPI 3.1) — open in the local environment, elsewhere only to a Super Admin signed in to `/admin`. A static
export is committed next to this file: **`backend/docs/openapi.json`** (regenerate with
`php artisan scramble:export --path=docs/openapi.json`).

## Conventions (all endpoints)

- Base URL `{APP_URL}/api/v1`. Send `Accept: application/json`, `Accept-Language: ar|en` (messages and labels
  follow it), and `Authorization: Bearer {token}` on protected routes.
- Envelope: success `{"success": true, "message": string|null, "data": …, "meta"?: {…}}`;
  failure `{"success": false, "message": string, "code": string, "errors"?: {field: [msg]}, "data"?: …}`.
  Codes: `validation_error` (422), `unauthenticated` (401), `forbidden` (403), `not_found` (404),
  `too_many_requests` (429, `Retry-After` header), `method_not_allowed`, `server_error`, `http_error`.
- Paginated lists: `meta.pagination = {current_page, per_page, total, last_page}`; query `page`, `per_page`
  (max 100), `sort` (`-field` = descending, whitelisted per endpoint), `filter[name]=value`.
- Dates `YYYY-MM-DD`; instants ISO-8601 (UTC `Z` for attendance); money is a **string** with 2 decimals
  (never parse to double for arithmetic); every `*_label` field is already translated.
- Ownership: every `/me/*` route is resolved from the token owner. Any id that is not yours answers **404**.
- Rate limits: 60 req/min per user (`api`), login 10/min per email+IP, check-in/out 12/min, device registration
  20/min, forgot-password 3/min per email+IP and 10/hour per IP, verify / reset / change password 10/min.
  A 429 carries `Retry-After`.
- Tokens: Sanctum personal access tokens, one per device, `expires_at` in the login answer (30 days default).
  Rotate with `POST auth/refresh`. Admin accounts are refused by the API (403 `forbidden`).

## Endpoints per screen

| # | Screen | Endpoints |
|---|---|---|
| 1 | Splash | **NEW** `GET app/config` (public) · then `GET auth/me` if a token is stored |
| 2 | Login | `POST auth/login` |
| 3 | Forgot password | **NEW** `POST auth/password/forgot` |
| 4 | OTP verification | **NEW** `POST auth/password/verify-otp` · **NEW** `POST auth/password/reset` |
| 5 | Home dashboard | **NEW** `GET me/home` (one call for every card) · `GET me/notifications/unread-count` |
| 6 | Employee profile | `GET me/profile` · **NEW** `GET me/profile/photo` · `GET me/requests/profile-fields` + `POST me/requests` (change request) |
| 7 | Attendance | `GET me/attendance/today` · `GET me/attendance?month=YYYY-MM` |
| 8 | Check-in | `GET me/attendance/today` → `POST me/attendance/check-in` |
| 9 | Check-out | `POST me/attendance/check-out` |
| 10 | GPS validation | `GET me/attendance/today` (`locations`, `rules`) — the server re-validates on submit |
| 11 | Face verification | `GET me/face` · `POST me/face/consent` · `DELETE me/face/consent` · `POST me/face/enrol` · `face_image` on check-in/out |
| 12 | Attendance history | `GET me/attendance?month=` · `GET me/attendance/attempts` · `GET/POST me/attendance/corrections` |
| 13 | Work schedule | `GET me/schedule?from=&to=` (max 62 days) |
| 14 | Leave balance | `GET me/leave/balances?year=` |
| 15 | New leave request | `GET me/leave/types` · `POST me/leave/requests/preview` · `POST me/leave/requests` (multipart for attachments) |
| 16 | Leave history | `GET me/leave/requests` · `GET me/leave/requests/{id}` · `POST …/{id}/cancel` · `POST …/{id}/attachments` · `GET …/{id}/attachments/{a}` |
| 17 | Payslips | `GET me/payslips?year=` · `GET me/payslips/summary?year=` · `GET me/payslips/{id}/pdf` |
| 18 | Salary breakdown | `GET me/payslips/{id}` (`lines`) · `GET me/payslips/summary` (`structure`) |
| 19 | Loans and advances | `GET me/loans` · `GET me/loans/limits` · `POST me/loans` · `GET me/loans/{id}` · `POST me/loans/{id}/cancel` |
| 20 | HR requests | `GET me/requests` · `GET me/requests/categories` · `POST me/requests` |
| 21 | Request details | `GET me/requests/{id}` · `POST …/replies` · `POST …/attachments` · `GET …/attachments/{a}` · `POST …/cancel` |
| 22 | Documents | **NEW** `GET me/documents/all` (shared documents, contracts, certificates, letters, payslips) · `GET me/documents` (shared documents only, paginated) · download URLs inside each item |
| 23 | Announcements | `GET me/announcements` · `GET me/announcements/{id}` · `POST …/read` · `POST …/acknowledge` · `GET …/attachments/{a}` |
| 24 | Notifications | `GET me/notifications` · `POST me/notifications/{uuid}/read` · `POST me/notifications/read-all` · `POST/DELETE me/devices` (FCM token) |
| 25 | Settings and language | **NEW** `PATCH me/profile` (`locale`) · **NEW** `PUT auth/password` · `GET/PUT me/notification-preferences` · **NEW** `GET auth/sessions` + `DELETE auth/sessions/{id}` · `POST auth/logout` · `POST auth/logout-all` · `GET auth/login-history` |

Also available: performance (`me/performance*`), training (`me/training*`), assets (`me/assets*`),
onboarding (`me/onboarding`), `company`, `branches`.

## NEW endpoints (shapes)

### `GET app/config` — public
```json
{"data": {
  "company": {"name": "…", "name_translations": {"ar": "…", "en": "…"}, "logo_url": "https://…/company/logo"|null},
  "locales": [{"code": "ar", "dir": "rtl", "name": "العربية"}, {"code": "en", "dir": "ltr", "name": "English"}],
  "default_locale": "ar",
  "localisation": {"timezone": "Africa/Cairo", "date_format": "Y-m-d", "time_format": "H:i", "use_arabic_digits": false,
                   "currency_code": "EGP", "currency_decimals": 2, "first_day_of_week": "sunday"},
  "features": {"face_verification_required_by_default": false, "face_provider": "simulated", "face_provider_simulated": true,
               "push_enabled": false, "push_provider": "simulated", "password_reset_otp": true,
               "loan_requests": true, "hr_requests": true, "training_requests": true, "employee_goals": true},
  "auth": {"otp_length": 6, "otp_ttl_seconds": 600, "otp_resend_seconds": 60, "password_min_length": 8,
           "password_require_mixed_case": false, "password_require_symbols": false},
  "app": {"min_version": "1.0.0", "latest_version": "1.0.0", "update_required_below_min": true},
  "server_time": "2026-11-12T08:00:00Z"
}}
```
Face mode per employee is policy-driven: always read `me/attendance/today → face.mode` before a check-in.

### Forgot password with an e-mailed OTP
1. `POST auth/password/forgot` `{email}` → always **200** with the same message whether or not the account
   exists (no account probing); `data: {"expires_in": 600, "resend_after": 60, "code_length": 6}`. A 6-digit
   code is e-mailed (dev: written to `storage/logs/laravel.log` by the log mailer). Only employee accounts that
   may use the app get a code. Throttled: 3/min per email+IP and 10/hour per IP (429).
2. `POST auth/password/verify-otp` `{email, code}` → 200 `{"data": {"valid": true, "expires_at": "…"}}`
   or 422 `errors.code`. Does not consume the code. 5 wrong tries burn the code (request a new one).
3. `POST auth/password/reset` `{email, code, password, password_confirmation}` → 200; the code is consumed and
   **every token of the account is revoked** (sign in again). Wrong / expired code → 422 `errors.code`.

### `PUT auth/password` (auth)
`{current_password, password, password_confirmation}` → 200. Revokes the account's **other** tokens and web
sessions; the current token stays valid. Wrong current password → 422 `errors.current_password`.

### `GET auth/sessions` / `DELETE auth/sessions/{id}` (auth)
List of the account's app tokens: `[{"id", "name" (device name), "created_at", "last_used_at", "expires_at", "is_current"}]`.
`DELETE` revokes one (404 when it is not yours); deleting the current one equals `auth/logout`.

### `PATCH me/profile` (auth)
`{locale: "ar"|"en"}` → 200 `data` = `auth/me` user object (with the new `locale`). Server texts (labels,
messages, push / e-mail notifications) follow it. Other profile data is changed by a profile change request.

### `GET me/profile/photo` (auth)
The employee's photo (image, inline), 404 when there is none. `me/profile` gains `photo_url` (null without photo).

### `GET me/home` (auth)
```json
{"data": {
  "employee": {"id", "employee_number", "name", "job_title", "department", "branch", "manager", "photo_url"},
  "attendance_today": {"date", "timezone", "state": "not_checked_in|checked_in|checked_out", "next_action": "check_in|check_out",
                       "employed", "is_working_day", "is_rest_day", "holiday": "name"|null, "within_shift_window",
                       "shifts": [{"name", "start_local", "end_local"}], "check_in_local"|null, "check_out_local"|null,
                       "status"|null, "status_label"|null, "mobile_checkin_enabled", "face_mode", "pending_reviews"},
  "attendance_month": {"month": "YYYY-MM", "present_days", "late_days", "absent_days", "leave_days", "incomplete_days",
                       "missing_punches", "worked_minutes", "scheduled_minutes", "late_minutes", "overtime_minutes"},
  "leave_balances": [{"leave_type": {"id", "code", "name", "color", "unit"}, "entitlement", "used", "pending", "balance", "available"}],
  "pending": {"leave_requests", "hr_requests", "loan_requests", "attendance_corrections", "announcements_to_acknowledge"},
  "latest_payslip": {"id", "number", "period_name", "period_end", "pay_date", "currency", "net_pay", "payment_status", "payment_status_label", "pdf_url"}|null,
  "upcoming": {"shifts": [{"date", "name", "start_local", "end_local"}], "rest_days": ["YYYY-MM-DD"], "holidays": [{"date", "name"}]},
  "tasks": [{"kind": "onboarding_task|acknowledge_asset|self_assessment|acknowledge_review", "id", "title", "due_date"|null}],
  "announcements": [AnnouncementResource × 3], "unread_announcements",
  "notifications": [NotificationResource × 5], "unread_notifications"
}}
```
Notes: times `*_local` are `HH:mm` in the branch timezone; `attendance_today.state` is display only — always
call `me/attendance/today` before a check-in (it has the locations, rules and face status). `tasks[].kind` →
screen: `onboarding_task` (onboarding), `acknowledge_asset` (`POST me/assets/{id}/acknowledge`),
`self_assessment` / `acknowledge_review` (`me/performance/reviews/{id}`). `latest_payslip` is null until a
payslip is issued. `upcoming.shifts` covers the next 7 days from tomorrow, `upcoming.holidays` the next 60 days
(max 5). `leave_balances` only lists types that track a balance; amounts are decimal strings.

### `GET me/documents/all` (auth)
```json
{"data": {
  "documents":    [{"id", "type": "document", "category", "category_label", "title", "date", "expiry_date", "is_expired", "file_name", "file_mime", "file_size", "download_url"}],
  "contracts":    [{"id", "type": "contract", "title", "date", "expiry_date", "is_current", "file_name", "file_mime", "file_size", "download_url"}],
  "certificates": [{"id", "type": "certificate", "title", "date", "expiry_date", "is_expired", "file_name", …, "download_url"}],
  "letters":      [{"id", "type": "letter", "title", "date", "request_id", "request_reference", "file_name", …, "download_url"}],
  "payslips":     [{"id", "type": "payslip", "title", "date", "download_url"}]
}, "meta": {"counts": {"documents": 3, "contracts": 1, "certificates": 0, "letters": 1, "payslips": 12}}}
```
Download URLs need the bearer token. Contracts: **NEW** `GET me/contracts/{id}/download` (only the employee's own
contracts that have a signed copy; every download is logged). Letters are HR-request attachments flagged as issued
letters (internal HR files never appear). Payslips: the latest 24 issued, `download_url` = the PDF. The older
`GET me/documents` (paginated, shared documents only, filters `filter[category]`, `filter[expired]`) is unchanged.

### Error answers worth handling on these screens
- OTP screens: 422 `errors.code` (wrong / expired / burnt code — offer "send a new code"), 429 (wait
  `Retry-After` seconds), 422 `errors.password` (password rules from `app/config → auth`).
- Every `/me/*` id that is not the user's: 404 `not_found` (never 403).
- `app/config → app.min_version` higher than the installed version: block and send to the store URL.

## Changes to existing answers (additive only)
- `GET me/profile` gained `photo_url` (string|null). Nothing else changed.
