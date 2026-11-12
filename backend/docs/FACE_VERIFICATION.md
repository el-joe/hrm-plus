# Face verification — setup guide

Face verification for mobile check-in / check-out. **No real provider is configured yet**
(PROJECT_PLAN.md, blocker B4). The installation ships with a **simulated provider** that performs
no biometric matching and no liveness detection; it exists so the whole flow (consent, enrolment,
retries, review queue, deletion) can be built and tested. Everything it produces is labelled
"Simulated" in the API (`is_simulated: true`) and in every admin screen. Do not use it in production.

## 1. How it fits together

| Piece | Where |
|---|---|
| Provider contract | `App\Services\Attendance\Face\Contracts\FaceVerificationProvider` (`enrol`, `verify`, `delete`) |
| Registry | `config/face_verification.php` → `providers`; the active one is *Settings → Face verification → provider* |
| Simulated provider | `Providers\SimulatedFaceProvider` — the token sent by the app decides the outcome: `sim-no-match`, `sim-liveness-fail`, `sim-error`, anything else = matched |
| Consent, enrolment, deletion, retention | `FaceEnrolmentService` |
| Verification at check-in, retry rules | `FaceVerifier` (called by `Mobile\MobileCheckIn`) |
| Encrypted storage | `BiometricStore` — private disk `biometric_files`, contents encrypted with the app key |
| Tables | `face_consents`, `face_enrolments` (no biometric content; provider reference encrypted), `face_verifications` (results only) |

HRM Plus never compares images itself. A real provider must do the matching and the
anti-spoofing / liveness check, on its servers or in a certified on-device SDK.

## 2. Policy rules (per attendance policy version)

*Shifts & schedules → Attendance policies → new version → Face verification*:

| Rule | Meaning |
|---|---|
| Face verification: off / optional / required | Off: never asked. Optional: verified when the app sends a face; a failure is never accepted silently. Required: a face sample is needed for every check-in / check-out. |
| Face attempts per day and direction | Failures (not matched, liveness failed, provider error) allowed before the fallback. |
| When face verification fails for good | **Send to review** (manual fallback: HR approves or rejects in *Mobile and web check-in review*) or **Reject** (the employee must contact HR). Employees who are not enrolled follow the same rule. |

Policies are assigned per company, branch, department, group, employment type or employee, so
the mode can differ per branch.

## 3. Consent, retention and deletion

- The employee consents in the app (`POST /api/v1/me/face/consent` with the current consent
  version, *Settings → Face verification → consent text version*). Changing the version asks
  everybody again; enrolment is refused without a consent for the current version.
- Withdrawing consent (`DELETE /api/v1/me/face/consent`) deletes the biometric data at once:
  provider subject, encrypted files, reference. The enrolment row keeps only dates and the reason.
- A termination deletes the data when it takes effect (`EmployeeTerminated` event).
- `face:purge` (daily 02:15) deletes enrolments older than *re-enrol after (days)*, enrolments of
  terminated employees, and verification results older than *keep verification results (days)*.
- *Attendance capture → Face enrolments* (permission `face_verification.view`) shows consent and
  enrolment status only; every view is logged as `sensitive_access.face_enrolments`.
  `face_verification.delete` erases one employee's data (reason required, audited).
- Enrolment images are not kept unless *keep an encrypted enrolment image* is switched on.

## 4. API for the Flutter app

| Endpoint | Body | Result |
|---|---|---|
| `GET /api/v1/me/face` | — | `provider`, `is_simulated`, `consent {current_version, given, given_version, given_at}`, `enrolment {enrolled_at, expires_at, ...}`, `enrolled` |
| `POST /api/v1/me/face/consent` | `{accepted: true, version}` | 201 + status |
| `DELETE /api/v1/me/face/consent` | — | 200 + status (data deleted) |
| `POST /api/v1/me/face/enrol` | `{token?, images?: [base64], liveness_passed?, liveness_score?}` | 201 + status; 422 `errors.face` (consent required, no sample, provider unavailable) |
| check-in / check-out | `face: {token?, images?, liveness_passed?, liveness_score?}` | see `MobileAttendanceController`: 422 `face_required` / `face_retry` (+ `data.face_remaining_attempts`), 202 `face_failed` / `face_not_enrolled` (review) |

The app must show the "Simulated" notice whenever `is_simulated` is true.

## 5. Connecting a real provider

1. Choose a provider with a mobile SDK that does liveness / anti-spoofing (e.g. a cloud face
   API with a liveness session, or a certified on-device SDK) and obtain the licence and credentials.
2. Put credentials in `.env` and `config/services.php` (never in the database or logs).
3. Implement `FaceVerificationProvider`:
   - `enrol()` — create the subject with the provider from the SDK token / images; return the
     provider's subject id, and a template only if the provider hands one back.
   - `verify()` — validate the SDK session / token server-side with the provider, return
     `FaceMatchResult` (matched, score, liveness). Throw `FaceProviderException` on errors.
   - `delete()` — delete the subject on the provider side.
4. Register it in `config/face_verification.php` (`'simulated' => false`), add its key to the
   `options` of `face_verification.provider` in `config/settings.php` and a label
   `face.providers.<key>` in both languages.
5. Switch the setting and have employees re-enrol (existing enrolments keep being verified by the
   provider that created them until they are replaced), and run a privacy review (DPIA) before go-live.
