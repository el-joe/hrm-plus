# Attendance devices — integration guide

How fingerprint / card / face terminals deliver attendance to HRM Plus, and how to add a
vendor adapter. **No device brand is supported yet**: the manufacturer, model and firmware of
the client's devices are not known (PROJECT_PLAN.md, blocker B3). Until a vendor adapter has been
tested on the real hardware, devices run with the **simulated adapter** (labelled "Simulated" on
every screen and log) or deliver their logs through the **CSV / Excel import**.

## 1. Architecture

```
device / vendor bridge ──push──▶ POST /api/v1/devices/{serial}/events ─┐
scheduler (attendance:sync-devices) ──pull──▶ adapter->pull() ─────────┤
admin "Sync now" / "Import range" ──pull──▶ adapter->pull() ───────────┤──▶ DeviceEventIngestor ──▶ RawEventRecorder ──▶ AttendanceEngine
admin CSV / Excel upload (dry run first) ──▶ DeviceLogImporter ─────────┘        (+ attendance_device_events, sync log)
```

| Piece | Where |
|---|---|
| Adapter contract | `App\Services\Attendance\Devices\Contracts\AttendanceDeviceAdapter` (`testConnection`, `pull`, `parsePush`) |
| Adapter registry | `config/attendance_devices.php` → `adapters` |
| Simulated adapter | `App\Services\Attendance\Devices\Adapters\SimulatedDeviceAdapter` |
| Ingestion (shared by every transport) | `DeviceEventIngestor` — idempotency key `device:{serial}:{device user id}:{unix time}`, device-user → employee mapping, unmatched events, queued processing |
| Sync runs, failures, backoff, health | `DeviceSyncService`, `AttendanceDevice::health()` |
| Tables | `attendance_devices`, `attendance_device_users` (mappings), `attendance_device_sync_logs`, `attendance_device_events` (device side of each raw event) |

Raw device logs are stored as `attendance_raw_events` (source `device`) and never edited; the
processed days are calculated from them by the engine. The same log arriving twice (re-pull,
push + file, a re-sent batch) hits the unique idempotency key and is counted as a duplicate.

## 2. Connection modes

| Mode | When | How |
|---|---|---|
| **Push** | The device (or the vendor's server / a small bridge) can call an HTTPS endpoint | `POST /api/v1/devices/{serial}/events` with `Authorization: Bearer <push token>`. The token is generated on the device page, shown once and stored as a SHA-256 hash; "New push token" invalidates the previous one. Rate limit: `push_per_minute`. |
| **Pull** | HRM Plus can reach the device or the vendor API | `attendance:sync-devices` runs every 5 minutes and pulls devices whose interval elapsed, from the stored cursor. "Sync now" and "Import range" (historical) run on demand. |
| **File** | No working integration | Export the attendance log from the device or its software and upload it (device page → Import device log). A dry run shows new / unmatched / duplicate / invalid rows before anything is written. |

### Generic JSON push format (simulated adapter, and recommended for bridges)

```json
{ "events": [
  { "user_id": "1024", "time": "2026-11-10 08:01:22", "direction": "in", "log_id": "88123", "verify_mode": "fingerprint" },
  { "user_id": "1024", "time": "2026-11-10T16:59:02+02:00", "direction": "out" }
] }
```

`time` without an offset is device wall-clock time (device timezone, else the branch timezone).
`direction` is `in`, `out` or omitted (`unknown`: the engine pairs punches by order).
Response: `{"success": true, "data": {"received", "created", "duplicates", "unmatched", "sync_log_id", "simulated"}}`;
`401 device_unauthorised`, `403 device_inactive`, `422 invalid_payload` (logged as a failed push).

### CSV / Excel columns

`device_user_id`, `time` (`YYYY-MM-DD HH:MM[:SS]` device time, or ISO-8601 with offset),
`direction` (optional), `log_id` (optional). Up to `import_max_rows` rows per file.

## 3. Failures, retries and health

- A failed pull is logged with its message, increments `consecutive_failures` and sets
  `next_retry_at` = now + `min(retry_base_minutes × 2^(failures−1), retry_max_minutes)`. The scheduler
  waits for that time. The cursor only moves on success, so nothing is skipped.
- Health: **Disabled** (inactive), **Failing** (3+ failures in a row), **Needs attention** (a recent failure,
  or no contact for more than *Settings → Attendance → device stale after* hours), **No contact yet**, **Healthy**.
- Messages shown to admins never contain credentials. Pull credentials are encrypted at rest.

## 4. Unknown device users

A log whose device user id has no mapping is stored as an **unmatched** raw event (never
dropped). Resolve it on the device page ("Map device users" lists the unknown ids with their
waiting events) or in *Attendance → Raw events* (map one event, optionally remembering the id).
Saving a mapping attaches every waiting event of that id and queues its processing. A mapping can
apply to one device or to every device (same enrolment numbers everywhere).

## 5. Adding a vendor adapter

Only after the client confirms manufacturer, model and firmware, and you have the vendor's SDK /
protocol documentation **and a device to test on**.

1. Create `app/Services/Attendance/Devices/Adapters/<Vendor><Model>Adapter.php` implementing
   `AttendanceDeviceAdapter`:
   - `testConnection()` — open a session, read firmware / user count, return `ConnectionResult`.
   - `pull($device, $cursor, $from, $to)` — read logs after the cursor (or inside the range for a
     historical import); return `PullResult` with `DevicePunch` values (absolute UTC instants; use
     `$device->effectiveTimezone()` for wall-clock times) and the next cursor (e.g. the last log id or time).
     Throw `DeviceSyncException` with a safe message on any error.
   - `parsePush($device, $request)` — only if the device / vendor pushes; translate its payload.
2. Register it in `config/attendance_devices.php`:
   ```php
   'vendor_model' => ['class' => VendorModelAdapter::class, 'simulated' => false, 'modes' => ['pull']],
   ```
   and add the label `devices.adapters.vendor_model` in `lang/{ar,en}/devices.php`.
3. Examples of protocols you may meet (examples only — none is implemented or claimed as supported):
   ZKTeco devices with the "push" / ADMS protocol or the pull SDK, Suprema BioStar 2 REST API,
   Hikvision ISAPI access-control events, Anviz CrossChex. Each needs its own adapter and its own test.
4. Write a feature test with recorded payloads from the real device (push) or a fake transport (pull):
   idempotency, time zones, direction mapping, failure path.
5. Test on the real device: clock drift, power loss, log buffer overflow, re-enrolled users.
   Only then describe the model as supported in the client documentation.

## 6. Operations

| Command | Schedule | Purpose |
|---|---|---|
| `attendance:sync-devices` | every 5 min | Pull devices that are due (honours backoff); `--now` pulls in-process, `--device=ID` |
| `attendance:process --pending` | every 10 min | Process raw events whose queued job did not run |
| `queue:work` | always | Processes device events (`ProcessAttendance`) and scheduled pulls (`SyncAttendanceDevice`) |
