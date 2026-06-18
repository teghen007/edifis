# EDIFIS — Deployment Guide (step by step)

Covers: deploying the **Cloud Brain** (`edifis.com` + per-school subdomains), deploying a **school node** for offline `.local` use with a **cheap SIM-router network**, how the **Flutter app** connects, and the optimal low-cost school hardware.

> Conventions: replace `edifis.com` with your real domain and `pssnkwen` with each school code.

---

## PART A — Cloud Brain (one server for all schools)

### A1. Get a server + domain
- A VPS with **dedicated vCPU** (e.g. Hetzner CCX, ~€30–65/mo), Ubuntu 22.04/24.04, public IP.
- Buy `edifis.com`. In DNS, create A-records:
  - `edifis.com` → server IP (the homepage + central domain)
  - `*.edifis.com` (wildcard) → server IP (so `pssnkwen.edifis.com`, `pssmankon.edifis.com`, … all resolve)
  - `api.edifis.com` → server IP (optional single API host for the app)

### A2. Install Docker
```bash
ssh root@SERVER_IP
apt update && apt -y install docker.io docker-compose-plugin git
```

### A3. Get the code + configure
```bash
git clone <your-repo> /opt/edifis && cd /opt/edifis/edifis-backend
cp .env.example .env
nano .env
```
Set (cloud):
```
EDIFIS_MODE=cloud
APP_URL=https://edifis.com
TENANCY_CENTRAL_DOMAIN=edifis.com
DB_CONNECTION=pgsql  DB_HOST=postgres  DB_DATABASE=edifis  DB_USERNAME=edifis  DB_PASSWORD=<strong>
REDIS_HOST=redis  QUEUE_CONNECTION=redis  CACHE_STORE=redis
# Push:
VAPID_PUBLIC_KEY=...  VAPID_PRIVATE_KEY=...  VAPID_SUBJECT=mailto:admin@edifis.com
FCM_PROJECT_ID=<firebase-project>   # service-account JSON path mounted into the container
```

### A4. Bring up the stack
```bash
cd /opt/edifis/edifis-infra/cloud
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

### A5. TLS + reverse proxy (auto-HTTPS) with Caddy
Caddy issues + renews Let's Encrypt certs automatically. Create `/opt/edifis/Caddyfile`:
```
edifis.com {
    root * /opt/edifis/landing      # the homepage (static HTML)
    file_server
}
*.edifis.com, api.edifis.com {
    reverse_proxy 127.0.0.1:8000    # the EDIFIS app (Octane)
}
```
For the wildcard cert you need a **DNS-01 challenge** (a Caddy DNS plugin + your DNS provider's API token). Run Caddy (as a container or `apt install caddy`). Now every `*.edifis.com` is HTTPS and proxied to the app; the app resolves the **tenant by the Host header**.

### A6. Create the schools (tenants)
Each school = a tenant with its subdomain (stancl/tenancy, domain identification):
```bash
docker compose exec app php artisan tenants:create pssnkwen --domain=pssnkwen.edifis.com
docker compose exec app php artisan tenants:create pssmankon --domain=pssmankon.edifis.com
docker compose exec app php artisan tenants:migrate
```
(Use the `LabSeeder`/your seeder per tenant for roles + initial data.) Now `https://pssnkwen.edifis.com` serves PSS Nkwen; `pssmankon.edifis.com` serves PSS Mankon — fully isolated databases.

### A7. The homepage `edifis.com`
A simple static landing page (`/opt/edifis/landing/index.html`): logo, motto, short blurb, and **"Find your school"** links to each `https://<school>.edifis.com`, plus a parent-portal link. (Caddy serves it on the apex domain in A5.) No app logic needed.

### A8. Push keys
- **VAPID** (Web Push): `php artisan webpush:vapid` → put keys in `.env`.
- **FCM** (app push): create a Firebase project → Project Settings → Service accounts → generate key → mount the JSON into the container; set `FCM_PROJECT_ID`. (Firebase is used **only** to deliver push — no school data is stored there.)

**Cloud is live.** Backups + monitoring per `INFRA-CLOUD-BRAIN.md` §6–7.

---

## PART B — School node (offline `.local`) with a cheap SIM-router network

### B1. The cheap, optimal network (recommended)
You do **not** need the desktop to be the Wi-Fi access point. Use one cheap device for Wi-Fi + internet:

```
        ┌────────────────────────────────────────────┐
        │  4G/LTE Wi-Fi router (SIM card + RJ45 LAN)  │  ← campus Wi-Fi + internet via SIM
        └───────┬───────────────────────────┬─────────┘
        Wi-Fi   │                           │ Ethernet (RJ45)
   staff tablets/phones              ┌──────┴───────────────┐
   (reach the server over LAN)       │ Old desktop = SERVER │  https://pssnkwen.edifis.com (on campus)
                                      │ Docker: app+PG+Redis │  syncs → cloud when SIM has data
                                      └──────────────────────┘
        Both router + desktop on a UPS.
```
- **One small 4G/LTE CPE router** with a **SIM slot + RJ45 + Wi-Fi** (≈ $30–60). It gives the campus Wi-Fi **and** the internet (for sync) from a data SIM. Cheaper and simpler than making the desktop an access point.
- Staff devices join the **router's Wi-Fi** and reach the server over the LAN — this works **even if the SIM has no data** (offline-first). The internet (SIM) is only needed by the **server**, to sync to the cloud — exactly as you wanted.
- Optional coverage: add a cheap **unmanaged switch** + extra access points wired to the router for larger campuses.
- Put **both** the router and the desktop on the **UPS**.

### B2. Hardware (your budget version)
| Part | Spec | Note |
|------|------|------|
| Desktop | quad-core-ish, **8 GB RAM** (works for one school with tuning), **256 GB SATA SSD (required)** | SATA SSD is fine at this scale; **an SSD is non-negotiable**. Offer the school an optional RAM chip (→16 GB) for more headroom |
| Network | 1× 4G/LTE Wi-Fi router (SIM + RJ45) | + optional switch/APs for coverage |
| Power | 1× UPS (covers desktop + router) | safe shutdown on outage |

> **8 GB note:** workable for a single small school if you cap PostgreSQL `shared_buffers` (~512 MB), keep Octane workers modest, and run Redis small. Tighter than 16 GB but fine for ~1,000 accounts and low concurrency. Recommend the school add a chip when they can.

### B3. Install the OS + Docker + mDNS
```bash
# Ubuntu Server 22.04/24.04 on the desktop. (LUKS full-disk encryption recommended.)
apt update && apt -y install docker.io docker-compose-plugin git avahi-daemon
```
`avahi-daemon` gives you the **`.local` name** automatically (mDNS) — set the hostname:
```bash
hostnamectl set-hostname pssnkwen
```
Staff can now reach the box at `pssnkwen.local` on the LAN.

### B4. Configure + run the node
```bash
git clone <your-repo> /opt/edifis && cd /opt/edifis/edifis-backend
cp .env.example .env && nano .env
```
Set (node):
```
EDIFIS_MODE=local
EDIFIS_SCHOOL_CODE=pssnkwen
EDIFIS_NODE_ID=node-pssnkwen-01
SYNC_CLOUD_BASE_URL=https://pssnkwen.edifis.com/api
DB_CONNECTION=pgsql DB_HOST=postgres DB_DATABASE=edifis_nkwen DB_USERNAME=edifis DB_PASSWORD=<strong>
```
```bash
cd /opt/edifis/edifis-infra/local-node
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=RolesAndPermissionsSeeder
```

### B5. HTTPS on the LAN (needed for the camera!)
The browser **camera** (QR attendance) only works over **HTTPS** (a "secure context"). Two options:

- **Recommended (same URL on/off campus):** get a real cert for `pssnkwen.edifis.com` on the node via Caddy **DNS-01 challenge** (needs internet once + a DNS API token; renews automatically). Then make the **router/desktop resolve `pssnkwen.edifis.com` to the desktop's LAN IP** (router local DNS, or dnsmasq on the desktop). Result: on campus, `https://pssnkwen.edifis.com` hits the **local node** with a fully trusted cert; off campus the same URL hits the **cloud**. Camera works; nothing to install on tablets.
- **Simpler (pilot):** generate an **internal-CA cert** for `pssnkwen.local`, serve HTTPS, and **install the CA cert on the handful of staff tablets** (one-time). Camera works on `https://pssnkwen.local`.

### B6. Sync, power, uptime
- **Sync runner:** schedule `php artisan edifis:sync` every minute (Laravel scheduler / cron) — it pushes/pulls deltas whenever the SIM has data; idempotent.
- **UPS:** install a UPS daemon (`nut`/`apcupsd`) → safe shutdown on battery + post telemetry. BIOS: **power-on after AC loss**. Compose already uses `restart: always`.
- **Backups:** daily encrypted DB+files backup with an off-box copy; run a restore drill once (`INFRA-SCHOOL-SERVER.md` §8).

**Node is live.** It serves the school on the LAN offline, and syncs to `pssnkwen.edifis.com` when the SIM has data.

---

## PART C — The Flutter app (how it works + how to ship it)

### C1. How it works
- It's a **native Android app** (online-only) that talks to the **cloud** over HTTPS (`https://<school>.edifis.com/api` or `https://api.edifis.com`).
- User installs the APK → logs in (staff: credentials; **parents: phone + PIN**, new-device email OTP) → the app calls the cloud API, shows data, **registers its FCM token** (`POST /api/fcm/register`) → receives **push notifications**.
- No offline buffer — if there's no internet, staff use the **browser on the node** (`.local`) instead. The app is for convenience + push, especially **parents**.

### C2. Build + distribute (Android)
On a machine with Flutter (you have it):
```bash
cd edifis-mobile
flutter build apk --release --dart-define=EDIFIS_CLOUD_BASE=https://api.edifis.com/api
# output: build/app/outputs/flutter-apk/app-release.apk
```
- For push: add Firebase to the app (`flutterfire configure` → `google-services.json`), then rebuild.
- **Distribute:** sideload the APK (share a link / put it on `edifis.com/app`), or publish to **Google Play** (one-time $25 dev account) for auto-updates. iOS later (needs a Mac).

### C3. (Optional) Flutter Web
You can also `flutter build web` and host it at `app.edifis.com` — but the **parent web portal** already covers browser users, so this is optional.

---

## PART D — Order of operations (do this)
1. **Cloud:** Part A → `edifis.com` homepage live, tenants created, push keys set, backups + monitoring on.
2. **One pilot school:** Part B → node on the SIM-router network, `.local`/subdomain HTTPS working, first sync to cloud verified.
3. **Data migration** for that school (dry-run → reconcile → import); validate report-card PDFs.
4. **App:** Part C → build the APK, test login + push against the cloud.
5. **Train staff**, soft-launch, watch monitoring; then roll to the other schools (repeat Part B per school).

See `ROADMAP-WHATS-LEFT.md` for the full go-live checklist and what's deliberately deferred (SMS, mobile-money, iOS).
