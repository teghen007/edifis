# EDIFIS Local Test Lab — Runbook

Run the **real EDIFIS topology on one desktop**: a multi-tenant **Cloud Brain** + two **campus nodes** (PSS Nkwen, PSS Mankon), with a way to pull a node "offline" to test the offline-first sync. Built for your machine (i7 10th-gen / 32 GB / Docker).

> **Why Docker Compose, not k3s?** In the field EDIFIS runs `docker compose` on a repurposed desktop per school + one cloud VPS — there is no Kubernetes. The lab mirrors that exactly. k3s would test an orchestration layer that won't exist where the schools run it. (k3s stays an *optional future* path only for scaling the cloud past ~10 schools — never for the campus nodes.)

---

## 0. Prerequisites

- Docker Desktop running (with Compose v2 — `docker compose version`).
- This repo on disk. All commands below run from `edifis-infra/lab/`.
- ~4 GB free RAM for the default lab (cloud + Nkwen); ~6 GB with the second node.

```bash
cd edifis-infra/lab
chmod +x lab-entrypoint.sh    # once (Git Bash / WSL / macOS / Linux)
```

## 1. Topology

```
                 ┌──────────────────────────┐
   localhost:8080│   CLOUD BRAIN  (cloud-app)│  multi-tenant, master academic ledger
                 │   db: edifis_central      │
                 └────────────┬──────────────┘
                              │  cloudlink  (the "internet")
              ┌───────────────┴───────────────┐
   :8091      │                               │      :8092 (--profile twonodes)
 ┌────────────┴───────────┐         ┌──────────┴────────────┐
 │ NKWEN node (nkwen-app) │         │ MANKON node (mankon..)│
 │ db: edifis_nkwen       │         │ db: edifis_mankon     │
 │ campus LAN: nkwen-net  │         │ campus LAN: mankon-net│
 └────────────────────────┘         └───────────────────────┘
```
Each node has its **own** DB and stays fully usable when detached from `cloudlink` (offline). The cloud is the only multi-tenant instance.

## 2. Bring the lab up

```bash
# Cloud + Nkwen (default):
docker compose -f docker-compose.lab.yml up -d --build

# …or add the second school to test tenant isolation:
docker compose -f docker-compose.lab.yml --profile twonodes up -d --build
```

First boot installs `vendor/` once (the `init` service) and migrates each server's DB. Watch progress:
```bash
docker compose -f docker-compose.lab.yml logs -f cloud-app nkwen-app
```

**Health check (all three should return `{status, mode, version}`):**
```bash
curl -s localhost:8080/api/health   # -> mode: cloud
curl -s localhost:8091/api/health   # -> mode: local  (Nkwen)
curl -s localhost:8092/api/health   # -> mode: local  (Mankon, if started)
```

## 3. Seed demo data

Roles/permissions, the two school tenants, demo staff, and a little academic/fee data come from the **lab seeder** (Phase 8, T-8.1). Once it exists:
```bash
docker compose -f docker-compose.lab.yml exec cloud-app  php artisan db:seed --class=LabSeeder
docker compose -f docker-compose.lab.yml exec nkwen-app  php artisan db:seed --class=LabSeeder
```
The seeder prints demo logins (e.g. a Principal for VACUUM, a bursar, a class master) and a couple of students.

## 4. THE MAIN EVENT — offline → online sync test

This is what the whole architecture exists for. We make a node go offline, do real work on it, bring it back, and watch the data reconcile to the cloud.

```bash
LAB="docker compose -f docker-compose.lab.yml"

# (1) Confirm Nkwen can reach the cloud right now:
$LAB exec nkwen-app php artisan tinker --execute="echo file_get_contents('http://cloud-app:8000/api/health');"

# (2) GO OFFLINE — detach Nkwen from the 'cloudlink' network (internet outage).
#     The campus LAN (nkwen-net) stays up, so the node keeps working.
docker network disconnect lab_cloudlink lab-nkwen-app-1
#     (container/network names: `docker network ls` and `docker ps` if the prefix differs)

# (3) Do real work WHILE OFFLINE — e.g. take attendance / issue items via the API
#     (use a seeded teacher/bursar token). These write append-only events locally.
#     curl -s -X POST localhost:8091/api/attendance/sessions ... etc.

# (4) COME BACK ONLINE:
docker network connect lab_cloudlink lab-nkwen-app-1

# (5) Trigger the node→cloud sync runner (Phase 8, T-8.2):
$LAB exec nkwen-app php artisan edifis:sync

# (6) VERIFY on the cloud — the offline-created events are now present and
#     attributed to the right school tenant, with derived balances intact.
```

**What you're proving:** append-only events created with no internet survive, sync without double-posting (idempotency), land in the correct tenant, and balances/attendance totals reconcile. This is the field reality: ENEO/MTN drop, the school keeps running, data catches up later.

### 4b. Conflict test (marks, cloud-wins)
1. Seed a mark for a student.
2. Offline on Nkwen: edit that mark (teacher correction).
3. On the cloud: edit the **same** mark via VACUUM (Principal).
4. Reconnect + `edifis:sync`.
5. **Expect:** cloud-wins, a `mark_conflicts` row, a `mark.conflict` audit entry, and the conflict delivered back to Nkwen on pull (the rejected edit is surfaced, never silently dropped).

### 4c. Tenant isolation (needs `--profile twonodes`)
Create data on Nkwen and on Mankon, sync both, and confirm on the cloud that **neither school can see the other's** students/marks/ledger.

## 5. Run the backend test suite in the real PHP 8.3 image
```bash
docker compose -f docker-compose.lab.yml exec cloud-app composer install
docker compose -f docker-compose.lab.yml exec cloud-app ./vendor/bin/pest
docker compose -f docker-compose.lab.yml exec cloud-app ./vendor/bin/pint --test
docker compose -f docker-compose.lab.yml exec cloud-app ./vendor/bin/phpstan analyse
```
This clears the "verified on SQLite only" caveat — the suite now runs on PHP 8.3 + PostgreSQL 16, the production runtime.

## 6. Useful operations
```bash
$LAB ps                       # what's running
$LAB logs -f cloud-app        # follow a server
$LAB exec nkwen-app sh        # shell into a node
$LAB exec cloud-db psql -U edifis -d edifis_central   # poke the cloud DB
$LAB down                     # stop (keep data)
$LAB down -v                  # stop + wipe all DB volumes (fresh start)
```

## 7. Troubleshooting
- **Network name in step 4 differs?** Run `docker network ls` (look for `*_cloudlink`) and `docker ps` for the exact node container name; substitute them.
- **App restarts / DB not ready:** first boot waits for PostgreSQL health; give it a minute, then `logs -f`.
- **`edifis:sync` / `LabSeeder` not found:** those are Phase 8 tasks (T-8.1/T-8.2) — the lab files are ready; the builder wires these in next. Until then you can still exercise sync by POSTing a `SyncEnvelope` to `/api/sync` directly.
- **Port already in use:** change the host port in `docker-compose.lab.yml` (e.g. `8080:8000` → `18080:8000`).

---

*The lab is deployment scaffolding, not app logic — it lets us prove EDIFIS behaves correctly under the exact conditions (no power, no internet) the schools live with.*
