# HRM Plus — REST API documentation (`/api/v1`)

**Version:** 1.0.0 (unreleased) · **Last updated:** 2026-11-12

This document is the human-readable overview of the HRM Plus REST API used by the employee mobile app (and
by attendance devices for the push endpoint). The authoritative, machine-readable contract is generated from
the code:

- **`/docs/api`** — interactive OpenAPI UI (Scramble), and **`/docs/api.json`** — OpenAPI 3.1 document.
  Open without sign-in in the `local` environment; in every other environment only to a **Super Admin**
  signed in to `/admin`.
- **`backend/docs/openapi.json`** — static export committed with the code
  (`php artisan scramble:export --path=docs/openapi.json`).
- **`backend/docs/API_FLUTTER_NOTES.md`** — screen-by-screen mapping for the Flutter app, with full JSON shapes
  of the newer endpoints.

The API is **employee-only**: admin accounts are refused (`403 forbidden`). There is no admin API in this release;
administration happens in the web admin area.

---

## Contents

1. [Base URL and headers](#1-base-url-and-headers)
2. [Authentication and token lifecycle](#2-authentication-and-token-lifecycle)
3. [Response envelope](#3-response-envelope)
4. [Errors and error codes](#4-errors-and-error-codes)
5. [Pagination, filtering, sorting](#5-pagination-filtering-sorting)
6. [Data formats](#6-data-formats)
7. [Rate limits](#7-rate-limits)
8. [Ownership and privacy rules](#8-ownership-and-privacy-rules)
9. [Endpoint reference](#9-endpoint-reference)
10. [Attendance device push endpoint](#10-attendance-device-push-endpoint)
11. [Versioning and compatibility](#11-versioning-and-compatibility)

---

## 1. Base URL and headers

```
{APP_URL}/api/v1
```

| Header | Value |
|---|---|
| `Accept` | `application/json` (always) |
| `Accept-Language` | `ar` or `en` — messages and every `*_label` field follow it (falls back to the user's locale) |
| `Authorization` | `Bearer {token}` on protected routes |
| `Content-Type` | `application/json`, or `multipart/form-data` for uploads |

Production must use HTTPS. Plain HTTP is only acceptable for local development.

---

## 2. Authentication and token lifecycle

Laravel Sanctum **personal access tokens**, one per device, scoped to employee accounts.

### 2.1 Sign in — `POST /auth/login` (public)

Request:

```json
{ "email": "employee1@example.com", "password": "••••••••", "device_name": "Pixel 8 — Android 15" }
```

Response `200`:

```json
{
  "success": true,
  "message": "…",
  "data": {
    "token": "12|AbCd…",
    "token_type": "Bearer",
    "expires_at": "2026-12-12T08:00:00+00:00",
    "user": { "id": 7, "name": "…", "email": "…", "locale": "ar", "employee": { … } }
  }
}
```

- Lifetime: `SANCTUM_TOKEN_EXPIRATION` minutes (default 43,200 = 30 days); `expires_at` is returned.
- Errors: `422 validation_error` (wrong credentials, reported on `email`), `403 forbidden` (admin account /
  account not allowed), `403 account_inactive`, `429 too_many_requests` (10/min per email + IP; plus the
  sign-in lockout from Settings → Security).
- Every attempt is written to the login history (area `api`).

### 2.2 Token operations (bearer required)

| Method & path | Purpose |
|---|---|
| `GET /auth/me` | Current user (same object as `data.user` at login) |
| `POST /auth/refresh` | Rotate: issues a new token, revokes the current one |
| `POST /auth/logout` | Revoke the current token (and its push device registration) |
| `POST /auth/logout-all` | Revoke every token of the account |
| `GET /auth/sessions` | App tokens of the account: `id`, `name`, `created_at`, `last_used_at`, `expires_at`, `is_current` |
| `DELETE /auth/sessions/{id}` | Revoke one of them (404 when not yours) |
| `GET /auth/login-history` | Recent sign-ins of the account (paginated) |
| `PUT /auth/password` | `{current_password, password, password_confirmation}` → revokes the other tokens and web sessions; the current token stays valid. Wrong current password → `422` on `current_password` |

On `401 unauthenticated` (expired / revoked token) the app must sign in again.

### 2.3 Password reset with an e-mailed OTP (public)

1. `POST /auth/password/forgot` `{email}` → always `200` with the same message whether or not the account
   exists (no account probing). `data: {expires_in, resend_after, code_length}`. Only employee accounts that may
   use the app receive a code. Throttle: 3/min per email + IP and 10/hour per IP.
2. `POST /auth/password/verify-otp` `{email, code}` → `200 {valid: true, expires_at}` or `422` on `code`.
   Does not consume the code; wrong attempts count (default 5, Settings → Security) and burn it.
3. `POST /auth/password/reset` `{email, code, password, password_confirmation}` → `200`; the code is consumed and
   **all tokens of the account are revoked**.

Codes are stored hashed in the cache; their lifetime (default 10 minutes) is a setting. Password rules come
from Settings → Security and are published in `GET /app/config → auth`.

### 2.4 Public app configuration — `GET /app/config`

Company name and logo URL, available locales and text direction, localisation (timezone, date / time format,
Arabic-Indic digits, currency code and decimals, first day of week), feature flags (face provider and whether
it is simulated, push enabled / provider, OTP reset, loan / HR / training requests, goals), password and OTP
rules, app versions (`min_version`, `latest_version`, `update_required_below_min`) and `server_time`. The app
must block itself when its version is below `min_version`.

---

## 3. Response envelope

Every JSON answer (success or failure) uses one envelope (`App\Support\ApiResponse`):

```json
// success
{ "success": true, "message": "string|null", "data": { … } | [ … ], "meta": { … } }

// failure
{ "success": false, "message": "Human-readable, localised", "code": "machine_code",
  "errors": { "field": ["message"] }, "data": { … } }
```

`meta` appears on paginated lists; `errors` on validation failures; `data` on some failures that carry context
(e.g. attendance rejection reasons). File downloads (PDF, attachments, photos) return the file itself.

---

## 4. Errors and error codes

### 4.1 Generic codes

| HTTP | `code` | Meaning |
|---|---|---|
| 401 | `unauthenticated` | Missing / invalid / expired / revoked token |
| 403 | `forbidden` | Not allowed (e.g. admin account on the API, feature switched off) |
| 403 | `account_inactive` | Account disabled |
| 404 | `not_found` | Unknown route or **any record that is not the caller's** |
| 405 | `method_not_allowed` | |
| 422 | `validation_error` | Field errors in `errors` |
| 422 | `not_cancellable` | Request can no longer be cancelled |
| 429 | `too_many_requests` | Throttled; honour the `Retry-After` header |
| 500 | `server_error` | Unexpected error (no internals exposed) |
| other | `http_error` | Other HTTP exceptions |

### 4.2 Attendance check-in / check-out

| HTTP | `code` | Meaning |
|---|---|---|
| 201 | — | Recorded (the only success) |
| 202 | — | Flagged and **sent for review — not recorded yet** |
| 422 | `attendance_rejected` | `data.reasons[]` (codes below), distance / radius / accuracy details, `data.face_remaining_attempts` |

Reason codes: `not_employed`, `mobile_disabled`, `web_disabled`, `location_required`, `poor_accuracy`,
`no_location_configured`, `outside_geofence`, `face_required`, `face_retry`, `mock_location`,
`outside_shift_window`, `face_failed`, `face_not_enrolled`, `time_drift`, `repeated_attempts`. Each has a
localised message (`lang/*/mobile.php → reasons`).

### 4.3 Device push

`401 device_unauthorised`, `403 device_inactive`, `422 invalid_payload` — see §10.

---

## 5. Pagination, filtering, sorting

- Query: `page`, `per_page` (max 100), `sort` (`field` ascending, `-field` descending, whitelisted per
  endpoint), `filter[name]=value` (whitelisted per endpoint). Some list endpoints also take simple query
  parameters (`status`, `year`, `month`, `unread`, `from`, `to`) as listed in §9.
- Paginated answers carry:

```json
"meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 134, "last_page": 7 } }
```

---

## 6. Data formats

- Dates `YYYY-MM-DD`; instants ISO-8601 (attendance instants in UTC `Z`); local wall-clock times as `HH:mm`
  fields named `*_local` with the branch `timezone` alongside.
- **Money is a string** with the currency's decimals (e.g. `"9725.11"`) — never parse to a floating-point number
  for arithmetic.
- Leave quantities are decimal strings (half days `0.5`, hours as a fraction of the scheduled day).
- Every `*_label` field is already translated to the request language.
- Uploads: `multipart/form-data`; allowed types and maximum size come from Settings → Documents (and per-module
  rules); files land on private disks and are only served through authorised download endpoints.

---

## 7. Rate limits

| Limiter | Limit | Applies to |
|---|---|---|
| `api` | 60 requests / minute per user (or IP) | every `/api/v1` route |
| `api-login` | 10 / minute per email + IP | `POST /auth/login` |
| `attendance-checkin` | 12 / minute | `POST me/attendance/check-in`, `check-out` |
| `password-code` | 3 / minute per email + IP and 10 / hour per IP | `POST auth/password/forgot` |
| `password-code-check` | 10 / minute | `verify-otp`, `reset`, `PUT auth/password` |
| `device-push` | `attendance_devices.push_per_minute` (default 120) per device | device push endpoint |
| device registration | 20 / minute | `POST me/devices` |

A `429` answer carries `Retry-After` (seconds).

---

## 8. Ownership and privacy rules

- Every `/me/*` route resolves the employee from the token. An id that does not belong to the caller answers
  **404 `not_found`** (never 403), so the API never confirms that another employee's record exists.
- Internal HR notes on requests, other employees' data, salary data of others and disciplinary records are never
  exposed. There is no disciplinary API.
- Payslip views / PDF downloads and contract downloads are written to the sensitive-data access log.
- Face data: only consent / enrolment status is returned; images are sent once and stored encrypted (or not at
  all, depending on the provider and settings).

---

## 9. Endpoint reference

All paths relative to `/api/v1`. 🔒 = bearer token required (employee account). Request bodies list required
fields with `*`.

### 9.1 Public

| Method | Path | Summary |
|---|---|---|
| GET | `app/config` | App bootstrap configuration (§2.4) |
| POST | `auth/login` | `{email*, password*, device_name*}` → token (§2.1) |
| POST | `auth/password/forgot` | `{email*}` → OTP e-mailed (§2.3) |
| POST | `auth/password/verify-otp` | `{email*, code*}` |
| POST | `auth/password/reset` | `{email*, code*, password*, password_confirmation*}` |
| POST | `devices/{serial}/events` | Device push (device token, not a user token) — §10 |

### 9.2 Account 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `auth/me` | Current user |
| POST | `auth/refresh` | Rotate token |
| POST | `auth/logout` · `auth/logout-all` | Revoke current / all tokens |
| GET | `auth/login-history` | Sign-in history (paginated) |
| PUT | `auth/password` | Change password |
| GET | `auth/sessions` · DELETE `auth/sessions/{id}` | App devices signed in |

### 9.3 Profile, home, company 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/home` | One call for the home screen: employee card, `attendance_today` (state, next action, shifts, face mode), `attendance_month` totals, leave balances, pending counts, latest payslip, upcoming shifts / rest days / holidays, tasks, 3 announcements, 5 notifications, unread counts |
| GET | `me/profile` | Personal and employment data, manager, branch / department, `photo_url` |
| PATCH | `me/profile` | `{locale*: ar|en}` — the only directly editable field (language of server texts and notifications) |
| GET | `me/profile/photo` | Photo (inline image), 404 when none |
| GET | `company` | Company profile |
| GET | `branches` · `branches/{id}` | Branches (paginated) |

Other profile changes go through an HR request of the profile-change category (§9.9).

### 9.4 Documents and onboarding 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/documents/all` | `documents`, `contracts`, `certificates`, `letters`, `payslips` (latest 24) with `download_url`s, plus `meta.counts` |
| GET | `me/documents` | Shared documents only (paginated; `filter[category]`, `filter[expired]`) |
| GET | `me/documents/{id}` · `me/documents/{id}/download` | One shared document / its file |
| GET | `me/contracts/{id}/download` | Signed copy of one of the employee's contracts (logged) |
| GET | `me/onboarding` | Onboarding checklist and the employee's tasks |
| POST | `me/onboarding/tasks/{task}/complete` | Complete an employee-owned task |

### 9.5 Attendance and schedule 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/attendance/today` | Today's state, shifts, allowed `locations` (lat / lng / `radius_meters` / `checkout_radius_meters`), face policy (`mode`, `max_attempts`, `remaining_attempts`, enrolment status) and `rules`: `mobile_checkin_enabled`, `web_checkin_enabled`, `web_checkin_requires_geofence`, `checkout_extra_meters`, `face_failure_action` (`reject` / `review`), `max_accuracy_meters`, `accuracy_tolerance_meters`, `max_time_drift_seconds`, `mock_location_action`, `outside_window_action`. Call before every check-in |
| POST | `me/attendance/check-in` · `me/attendance/check-out` | `{request_uuid*, latitude*, longitude*, accuracy*, is_mock_location, device_time, device_id, platform, app_version, face: {token?, images?: [base64], liveness_passed?, liveness_score?}}` → 201 / 202 / 422 (§4.2). Re-sending the same `request_uuid` after a timeout is idempotent |
| GET | `me/attendance/attempts` | The employee's check-in attempts with result and review status. Each attempt has `channel` (`mobile` = the app, `web` = the employee portal in a browser); `location.accuracy_meters` may be `null` (a web attempt whose policy only records the position, sent without one) |
| GET | `me/attendance` | `?month=YYYY-MM` — processed days with status, times, late / early / overtime minutes, source (raw-event sources: `device`, `mobile_gps`, `web_portal` — a check-in from the employee portal —, `manual`, `import`) |
| GET | `me/attendance/corrections` | Correction requests |
| POST | `me/attendance/corrections` | `{work_date*, shift_id, check_in, check_out, attendance_status_id, reason*}` → 201 (goes through the attendance approval workflow) |
| GET | `me/schedule` | `?from=&to=` (max 62 days) — resolved shifts, rest days, holidays |

### 9.6 Face verification 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/face` | `provider`, `is_simulated`, consent `{current_version, given, given_version, given_at}`, enrolment, `enrolled` |
| POST | `me/face/consent` | `{accepted*: true, version*}` → 201 |
| DELETE | `me/face/consent` | Withdraw consent — biometric data deleted at once |
| POST | `me/face/enrol` | `{token?, images?: [base64], liveness_passed?, liveness_score?}` → 201; 422 on `face` (consent required, no sample, provider unavailable) |

The app must show a "simulated" notice whenever `is_simulated` is true (current state — blocker B4).

### 9.7 Leave 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/leave/types` | Types the employee may request, with unit, rules, attachment threshold |
| GET | `me/leave/balances` | `?year=` — entitlement, used, pending, balance, available per type |
| POST | `me/leave/requests/preview` | Same body as create → counted days, dates, balance after, warnings (nothing saved) |
| POST | `me/leave/requests` | multipart `{leave_type_id*, start_date*, end_date, day_part (full / first_half / second_half / hours), hours, start_time, reason, attachments[]}` → 201 |
| GET | `me/leave/requests` | `?status=` (paginated) |
| GET | `me/leave/requests/{id}` | Details with days, approval history, attachments |
| POST | `me/leave/requests/{id}/cancel` | `{reason}` — immediate or a cancellation request depending on policy / start date |
| POST | `me/leave/requests/{id}/attachments` | multipart `attachments[]*` |
| GET | `me/leave/requests/{id}/attachments/{a}` | Download |

### 9.8 Payroll and loans 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/payslips` | `?year=` — issued payslips: number, period, gross, deductions, net, payment status |
| GET | `me/payslips/summary` | `?year=` — year totals and (if Settings → Payroll allows) the current salary `structure` |
| GET | `me/payslips/{id}` | Payslip with `lines` (earnings, deductions, employer contributions hidden as configured) |
| GET | `me/payslips/{id}/pdf` | Arabic / English PDF (`?lang=`), logged |
| GET | `me/loans` | `?status=` |
| GET | `me/loans/limits` | Effective limits for this employee (eligibility, max amounts, instalments) |
| POST | `me/loans` | `{type*: loan|advance, amount*, instalments*, start_month*, reason}` → 201 (loan approval workflow) |
| GET | `me/loans/{id}` | Loan with schedule and transactions |
| POST | `me/loans/{id}/cancel` | `{reason}` — pending requests only |

### 9.9 HR requests 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/requests/categories` | Categories with type (general / letter / profile change), SLA, required attachment, custom fields |
| GET | `me/requests/profile-fields` | Profile fields that can be requested to change, with current values |
| GET | `me/requests` | `?status=&per_page=` |
| POST | `me/requests` | Category, subject, body, custom field values, attachments; profile-change requests carry the requested new values → 201 |
| GET | `me/requests/{id}` | Thread (employee-visible replies only), attachments, status history |
| POST | `me/requests/{id}/replies` | multipart `{body*, attachments[]}` |
| POST | `me/requests/{id}/attachments` | multipart `attachments[]*` |
| GET | `me/requests/{id}/attachments/{a}` | Download (issued letters included) |
| POST | `me/requests/{id}/cancel` | `{reason}` |

### 9.10 Announcements and notifications 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/announcements` | `?unread=&per_page=` — live announcements targeted at the employee |
| GET | `me/announcements/{id}` | Details (sanitised rich text in the request language) |
| POST | `me/announcements/{id}/read` | Mark read |
| POST | `me/announcements/{id}/acknowledge` | Acknowledge (mandatory ones) |
| GET | `me/announcements/{id}/attachments/{a}` | Download |
| GET | `me/notifications` | `?unread=&per_page=` — in-app notifications with deep-link data |
| GET | `me/notifications/unread-count` | |
| POST | `me/notifications/{uuid}/read` · `me/notifications/read-all` | |
| GET / PUT | `me/notification-preferences` | Per event × channel (mail / push / in-app); mandatory events locked |
| GET / POST / DELETE | `me/devices` | Push device tokens: `POST {token*, platform*, device_name, app_version, locale}`; `DELETE ?token=` |

Push payloads carry `data.announcement_id`, `leave_request_id`, `hr_request_id`, `payslip_id`, `loan_id` or a
portal `url` for deep links.

### 9.11 Performance, training, assets 🔒

| Method | Path | Summary |
|---|---|---|
| GET | `me/performance` | Summary: active cycle, open reviews, goals progress |
| GET | `me/performance/goals` | Goals and KPIs |
| POST | `me/performance/goals/{id}/progress` | `{progress, current_value, note}` |
| GET | `me/performance/reviews` · `me/performance/reviews/{id}` | Reviews (manager comments visible once released) |
| POST | `me/performance/reviews/{id}/self-assessment` | `{goals, competencies, comments, strengths, improvements, submit}` |
| POST | `me/performance/reviews/{id}/acknowledge` | `{comment}` |
| GET | `me/training` | Summary |
| GET | `me/training/sessions` | Open sessions the employee may request |
| POST | `me/training/requests` | `{training_session_id*, reason}` → 201 (training approval workflow) |
| GET | `me/training/enrolments` · `…/{id}` · POST `…/{id}/cancel` | Enrolments |
| GET / POST | `me/training/certificates` | List / upload multipart `{type*, name*, issuer, certificate_number, issued_on, expires_on, file*}` |
| GET | `me/training/certificates/{id}/download` | |
| GET | `me/assets` · `me/assets/{id}` | Assets in custody |
| POST | `me/assets/{id}/acknowledge` | `{note}` — confirm receipt |

---

## 10. Attendance device push endpoint

```
POST /api/v1/devices/{serial}/events
Authorization: Bearer {device push token}
```

- `{serial}`: the device serial number configured in Admin → Attendance capture → Attendance devices
  (`[A-Za-z0-9._-]{1,100}`).
- The token is generated on the device page (**New push token**), shown once, stored as a SHA-256 hash; a new
  token invalidates the previous one. It is not a user token.
- Body (generic JSON format of the simulated adapter, recommended for bridges):

```json
{ "events": [
  { "user_id": "1024", "time": "2026-11-10 08:01:22", "direction": "in", "log_id": "88123", "verify_mode": "fingerprint" },
  { "user_id": "1024", "time": "2026-11-10T16:59:02+02:00", "direction": "out" }
] }
```

  `time` without offset = device wall-clock time (device timezone, else branch timezone); `direction` `in`,
  `out` or omitted. Up to 500 events per request.
- Response `200`:
  `{"success": true, "data": {"received", "created", "duplicates", "unmatched", "sync_log_id", "simulated"}}`.
- Errors: `401 device_unauthorised`, `403 device_inactive`, `422 invalid_payload` (logged as a failed push),
  `429` above the per-device rate.
- Duplicates (same device, device user, second) are counted, never recorded twice. Unknown device users are
  stored as *unmatched* events and processed once mapped.
- A real vendor's native push protocol needs its own adapter (`parsePush`) — none exists yet (blocker B3); see
  `ATTENDANCE_INTEGRATION_AR.md`.

---

## 11. Versioning and compatibility

- The version is in the path (`/api/v1`). Changes inside v1 are **additive only** (new endpoints, new fields);
  removing or renaming a field requires `/api/v2`.
- Clients must ignore unknown fields.
- Minimum app version is enforced by the app through `GET app/config → app.min_version` (Settings → Mobile app).
