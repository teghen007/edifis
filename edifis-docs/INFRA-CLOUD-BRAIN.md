# EDIFIS — Cloud Brain Infrastructure Specification

The central, multi-tenant server that all school nodes sync to and that all parents connect to. One cloud serves all 4 (→25+) schools.

---

## 1. Role of the Cloud Brain
- Holds the **master academic ledger** and authoritative parent-facing data.
- Multi-tenant: each school is an isolated tenant (DB-per-tenant, stancl/tenancy).
- Terminates parent traffic (portal + app), sends **Web Push + FCM** notifications.
- Receives node sync (deltas), resolves conflicts, returns deltas/conflicts/revocations.
- Aggregates **node health/UPS telemetry** for central monitoring.

## 2. Sizing (initial 4 schools ≈ 4,000 students + 300 staff)
Light workload; the real drivers are the **result-day read burst** and the **sync thundering-herd**. Dataset is low single-digit GB.

| Component | Recommended | Notes |
|-----------|-------------|-------|
| App/web tier | 4 vCPU / 8–16 GB RAM | Octane; handles parent read burst + API |
| **Database (PostgreSQL 16)** | **Dedicated vCPU**, 16 GB RAM | The one non-negotiable: dedicated cores before go-live; shared-CPU "noisy neighbour" hurts during sync bursts |
| Redis + Horizon + Reverb | Shares the app tier at this scale | queue, throttling, WebSocket push |
| Storage | 80–160 GB NVMe SSD | small dataset; space for backups, PDFs, signature/photo images, logs |
| Bandwidth | 20 TB-class | far more than 4 schools use |

**Indicative cost:** ~€30–65/month on a value provider (e.g. Hetzner CCX dedicated-vCPU). Start modest; scales vertically with minimal downtime.

**Scale-up triggers (toward 25 schools):** split DB onto its own instance under sustained app pressure; add a load balancer + second app instance past ~10 schools; revisit the single-IT-teacher node-admin model (an operational, not technical, cliff).

## 3. Software stack & deployment
- Docker Compose: `app` (Octane), `horizon`, `postgres:16`, `redis` (see `edifis-infra/cloud/docker-compose.yml`).
- `EDIFIS_MODE=cloud`; `TENANCY_CENTRAL_DOMAIN=edifis.cm`.
- PHP 8.3 image (`edifis-backend/Dockerfile`, `pdo_pgsql`).
- Apply env from a secured `.env` (never commit). Keys: DB, Redis, Sanctum TTLs, VAPID (Web Push), FCM (`FCM_PROJECT_ID` + service-account path), monitoring endpoint.

## 4. Networking, domains, TLS
- Per-school subdomains: `https://pssnkwen.edifis.cm`, `https://pssmankon.edifis.cm`, … (tenant resolved by domain).
- Public TLS via Let's Encrypt (or provider certs); HSTS; HTTP→HTTPS redirect.
- Firewall: expose 443 (and 80 for ACME) only; DB/Redis bound to localhost/private network.
- Reverb WebSocket endpoint over TLS for real-time portal updates.

## 5. Push notification services
- **Web Push:** generate VAPID keypair; set `VAPID_PUBLIC_KEY/PRIVATE_KEY/SUBJECT`.
- **FCM (HTTP v1):** create a Firebase project; download a **service-account JSON**; set `FCM_PROJECT_ID` + credentials path. Used only to deliver push to the Flutter app — **no student data stored in Firebase**; payloads are non-sensitive.

## 6. Backups & DR
- **Automated daily encrypted backups:** PostgreSQL dump + uploaded files (PDFs, signatures, photos), retained on a schedule with **off-box copies** (separate region/bucket).
- **Rehearsed restore drill** (timed) BEFORE go-live — an untested backup is not a backup.
- Point-in-time recovery SHOULD be enabled (WAL archiving) once schools depend on it.

## 7. Monitoring & alerting
- Ingest `POST /monitoring/node-status` (disk_ok, ups_on_battery, last_sync_at, pending_outbox); alert on node-missing-N-intervals, UPS-on-battery, disk fault, sync backlog.
- App/DB metrics (CPU, RAM, slow queries, queue depth, 429 rate). Alert on result-day/sync-burst saturation.
- Uptime checks on each school subdomain + the parent portal.

## 8. Security & compliance (cloud)
- Dedicated DB user per tenant scope; least-privilege; secrets in a vault or provider secret store.
- Full audit trail retained; data export/erasure tooling for data-subject requests (Law 2024/017).
- Regular OS/dependency patching; restrict SSH (keys only, non-default port, fail2ban).

## 9. Go-live checklist (cloud)
- [ ] Dedicated-vCPU PostgreSQL provisioned and tuned.
- [ ] Domains + TLS for all schools; portal reachable.
- [ ] VAPID + FCM configured; a test push delivers.
- [ ] Backups running + a restore drill passed.
- [ ] Monitoring/alerts live (node telemetry + app/DB).
- [ ] Tenants created per school; data migration dry-run reconciled.
