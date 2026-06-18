# EDIFIS API Reference

> **Source of truth:** `edifis-contracts/openapi/edifis.openapi.yaml` — this document mirrors that spec. All errors use the [shared error schema](edifis-contracts/schemas/_error.schema.json). Every write is idempotent on `{id, revision}`.

---

## Quick reference

| Module | Endpoint | Method | Auth | Role |
|--------|----------|--------|------|------|
| Health | `/health` | GET | — | — |
| Auth | `/auth/login` | POST | — | — |
| Auth | `/auth/revocations` | GET | — | — |
| Auth | `/parent/login` | POST | — | — (cloud only) |
| Auth | `/parent/verify-otp` | POST | — | — (cloud only) |
| Sync | `/sync` | POST | — | — |
| Students | `/students` | POST | Sanctum | secretary, bursar |
| Issuance | `/issuance/catalogue:import` | POST | Sanctum | bursar |
| Issuance | `/issuance/issue` | POST | Sanctum | bursar |
| Issuance | `/issuance/return` | POST | Sanctum | bursar |
| Attendance | `/attendance/sessions` | POST | Sanctum | class_master, subject_teacher |
| Attendance | `/attendance/sessions/{id}/scan` | POST | Sanctum | class_master, subject_teacher |
| Attendance | `/attendance/sessions/{id}/close` | POST | Sanctum | class_master, subject_teacher |
| Attendance | `/attendance/void` | POST | Sanctum | class_master, subject_teacher |
| Attendance | `/attendance/sessions/{id}/tally` | GET | Sanctum | class_master, subject_teacher, principal |
| Academics | `/academics/marks` | POST | Sanctum | subject_teacher, class_master, principal |
| Fees | `/fees/students/{id}/balance` | GET | Sanctum | bursar, parent |
| Timetable | `/timetable` | GET, POST | Sanctum | GET: any, POST: vice_principal, principal |
| Timetable | `/timetable/{id}/approve` | POST | Sanctum | principal |
| Calendar | `/calendar` | GET, POST | Sanctum | any |
| VACUUM | `/vacuum/query` | POST | Sanctum | principal |
| VACUUM | `/vacuum/command` | POST | Sanctum | principal |
| Parent | `/parent/set-pin` | POST | Sanctum | parent (cloud only) |
| Parent | `/parent/children` | GET | Sanctum | parent (cloud only) |
| Parent | `/parent/children/{id}/balance` | GET | Sanctum | parent (cloud only) |
| Parent | `/parent/children/{id}/results` | GET | Sanctum | parent (cloud only) |
| Parent | `/parent/children/{id}/attendance` | GET | Sanctum | parent (cloud only) |
| Parent | `/parent/calendar` | GET | Sanctum | parent (cloud only) |
| FCM | `/api/fcm/register` | POST | Sanctum | any |
| Monitoring | `/monitoring/node-status` | POST | — | — |

---

## Health

**GET /health** — liveness check. Returns the run mode and version. No auth.

Response `200`:
```json
{ "status": "ok", "mode": "cloud|local", "version": "0.1.0" }
```

---

## Auth / Staff Login

**POST /auth/login** — exchange credentials for a short-TTL Sanctum token. Use `identifier` as email/phone. The token has a short TTL; clients cache it for brief offline use and re-validate on reconnect.

Request:
```json
{ "identifier": "user@school.local", "password": "secret", "device_id": "optional" }
```

Response `200`:
```json
{
  "token": "1|abc...",
  "expires_at": "2026-06-18T12:00:00Z",
  "role": "principal|vice_principal|bursar|class_master|subject_teacher|discipline_master|secretary|parent",
  "user_id": "018f3c2a-..."
}
```

**GET /auth/revocations?since=** — pulled at sync by nodes/apps. Returns tokens and user IDs revoked since the given timestamp. A disconnected node applies these on next sync.

Response `200`:
```json
{
  "revoked_token_ids": ["..."],
  "revoked_user_ids": ["018f3c2a-..."],
  "as_of": "2026-06-18T11:00:00Z"
}
```

---

## Parent Auth (Cloud Only — 404 on local node)

**POST /parent/login** — first login uses `phone` + `phone reversed` as credential. Forces PIN set on first login. If an email is registered, a new-device OTP is sent unless a trusted-device cookie is provided.

Request:
```json
{ "phone": "670000001", "credential": "100000076", "device_token": "optional" }
```

Response `200` (OTP required):
```json
{ "status": "otp_required", "message": "A 6-digit code has been sent to your email.", "must_reset_pin": true }
```

Response `200` (trusted device / after OTP):
```json
{ "token": "1|...", "must_reset_pin": false, "device_trusted": true, "device_token": "abc...", "user_id": "..." }
```

**POST /parent/verify-otp** — submit the email OTP code.

Request:
```json
{ "phone": "670000001", "code": "123456" }
```

Response `200`:
```json
{ "token": "1|...", "must_reset_pin": false, "device_token": "abc..." }
```

---

## Sync

**POST /sync** — bidirectional delta sync. Push sends local changes; pull fetches cloud deltas since `since_cursor`. Idempotent. Accountability items are processed first. See [sync envelope schema](../edifis-contracts/schemas/sync-envelope.schema.json).

Request (push):
```json
{
  "direction": "push",
  "node_id": "node-pssnkwen-01",
  "since_cursor": null,
  "items": [
    { "type": "issue_event", "id": "...", "revision": "r1", "payload": { ... } }
  ]
}
```

Response `200`:
```json
{
  "direction": "push",
  "node_id": "...",
  "since_cursor": "...",
  "next_cursor": "...",
  "items": [],
  "conflicts": [
    { "type": "mark", "id": "...", "resolution": "cloud_wins", "winning_revision": "r2", "rejected_revision": "r1-node" }
  ]
}
```

Errors: `409` (idempotency replay), `429` (rate limited — retry with backoff).

---

## Students

**POST /students** — enrol a student. Issues a Master PEA ID on cloud; requires guardian consent for minors. See [student schema](../edifis-contracts/schemas/student.schema.json) and [consent schema](../edifis-contracts/schemas/consent.schema.json).

Request:
```json
{
  "student": { "given_name": "Goodness", "family_name": "Shei", "sex": "F", "date_of_birth": "2008-04-15", "current_class_id": "..." },
  "consent": { "consenter_name": "Martha Shei", "relationship": "mother", "consenter_contact": "+237670000001", "scope": ["academic_records", "photo_on_id", "parent_portal"] }
}
```

Response `201`:
```json
{ "id": "018f3c2a-...", "master_pea_id": "PEA-2026-00001", "given_name": "Goodness", "family_name": "Shei", "enrolled_at": "2026-06-18T10:00:00Z" }
```

Errors: `422` (consent missing/invalid for a minor).

---

## Issuance

**POST /issuance/catalogue:import** — import the rubric catalogue from Excel. See [catalogue item schema](../edifis-contracts/schemas/catalogue-item.schema.json).

Response `202` (accepted for import).

**POST /issuance/issue** — issue a batch of items to a student under one signature. Creates one immutable `issue_event` per item and auto-posts ledger debits. See [issue event schema](../edifis-contracts/schemas/issue-event.schema.json).

Request:
```json
{
  "batch_id": "018f3c2a-...",
  "student_id": "018f3c2a-...",
  "signature_ref": "signatures/a1b2c3.png",
  "items": [
    { "catalogue_item_id": "018f3c2a-..." },
    { "catalogue_item_id": "018f3c2a-..." }
  ]
}
```

Response `201`:
```json
{
  "events": [
    { "id": "...", "revision": "r1", "student_id": "...", "catalogue_item_id": "...", "cost": 8000, "status": "issued" }
  ],
  "posted": [
    { "student_id": "...", "source_event_id": "...", "amount": 8000 }
  ]
}
```

**POST /issuance/return** — return an item. Creates a new `issue_event` with `status: "returned"` and a credit ledger entry. The original event is never modified.

Errors: `409` (replay), `200` with `code: "idempotency_replay"` for already-applied batches.

---

## Attendance

**POST /attendance/sessions** — open a class session. See [session schema](../edifis-contracts/schemas/session.schema.json).

Request:
```json
{ "class_id": "...", "subject_id": "...", "period": "AM" }
```

**POST /attendance/sessions/{id}/scan** — record a QR scan or manual override. Override requires a reason. See [attendance event schema](../edifis-contracts/schemas/attendance-event.schema.json).

Request:
```json
{ "student_id": "...", "source": "qr_scan|manual_override", "void_reason": "optional — required for override" }
```

**POST /attendance/sessions/{id}/close** — close the session.

**POST /attendance/void** — void a recorded scan. Creates a new attendance event with `status: "void"`; the original is never modified.

**GET /attendance/sessions/{id}/tally** — scanned count vs teacher headcount for the session.

---

## Academics / Marks

**POST /academics/marks** — enter or edit a mark. Per-record teacher ownership: a linear edit (revision_parent matches current revision) is accepted. A true divergent conflict returns `409` with cloud-wins arbitration. See [mark schema](../edifis-contracts/schemas/mark.schema.json).

Request:
```json
{
  "id": "mark-uuid",
  "revision": "r2",
  "revision_parent": "r1",
  "student_id": "...",
  "subject_id": "...",
  "class_id": "...",
  "sequence": "T1-Seq1",
  "owner_teacher_id": "...",
  "score": 15.5,
  "max_score": 20
}
```

Response `201`: the mark. Response `409`: conflict — cloud version preserved, your edit surfaced as a `mark.conflict` entry.

---

## Fees

**GET /fees/students/{id}/balance** — derived balance. `SUM(ledger_entry.amount)` in CFA minor units. Never a stored column.

Response `200`:
```json
{ "student_id": "...", "balance": 12500, "currency": "XAF" }
```

---

## Timetable & Calendar

**GET /timetable?class_id=&teacher_id=** — role-scoped read. Subject teachers see their own periods; class masters see the class; VP/Principal see all.

**POST /timetable** — create/update a timetable entry (VP/timetable officer). Saved as `is_approved: false` pending Principal approval.

**POST /timetable/{id}/approve** — Principal approves. Writes an audit entry.

**GET /calendar** — school calendar events.

**POST /calendar** — create/update a calendar event.

---

## VACUUM (Principal Only)

**POST /vacuum/query** — Principal AI co-pilot. Natural-language question → read-only structured answer. Examples: "Who is borderline for promotion in Form 4?", "Show attendance summary for Form 3."

Request: `{ "question": "..." }`
Response: `{ "answer": "...", "records": [...] }`

**POST /vacuum/command** — Principal audited command. Always writes an `audit_entry` (actor, before/after, reason). Finance targets return `403`. `deactivate_account` requires `confirm: true`. See [audit entry schema](../edifis-contracts/schemas/audit-entry.schema.json).

Request:
```json
{
  "command": "correct_mark|promote_student|repeat_student|override_promotion|deactivate_account",
  "target": { "mark_id": "...", "type": "mark" },
  "payload": { "score": 16.0 },
  "reason": "Medical exemption approved by PEA",
  "confirm": true
}
```

Response `200`: `{ "applied": {...}, "audit": [...] }`. Response `403`: non-Principal or finance target.

---

## Monitoring

**POST /monitoring/node-status** — node + UPS telemetry. Public endpoint.

Request:
```json
{ "node_id": "node-pssnkwen-01", "reported_at": "...", "disk_ok": true, "ups_on_battery": false, "last_sync_at": "...", "pending_outbox": 0 }
```

---

## Error Codes

All from [error schema](../edifis-contracts/schemas/_error.schema.json):

| Code | HTTP | Meaning |
|------|------|---------|
| `validation_failed` | 422 | Invalid request body |
| `unauthenticated` | 401 | Missing/invalid token |
| `token_expired` | 401 | Token TTL elapsed |
| `token_revoked` | 401 | Token revoked (offboarding) |
| `forbidden` | 403 | Role or permission denied |
| `not_found` | 404 | Resource not found |
| `conflict` | 409 | Sync conflict (see conflicts[] for resolution) |
| `idempotency_replay` | 200 | Replayed envelope — no-op |
| `rate_limited` | 429 | Too many requests — retry after `retry_after_seconds` |
| `consent_required` | 422 | Guardian consent required for a minor |
| `account_deactivated` | 401 | Account disabled (offboarding) |
| `invalid_credentials` | 401 | Wrong credential |
| `invalid_otp` | 422 | Wrong/expired OTP code |
| `account_locked` | 423 | Too many failed attempts |

---

## CORS & Versioning

Both Cloud Brain and Lite Local Node serve from their domain (`https://{school}.edifis.cm/api` or `https://{school}.local/api`). The API version is reported at `/health`. All dates are ISO 8601 Zulu. All amounts are CFA minor units (integers, never floats).
