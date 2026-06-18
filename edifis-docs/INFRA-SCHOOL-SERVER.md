# EDIFIS — School Server (Lite Local Node) Specification

Everything a single school needs to run EDIFIS on campus, on a **repurposed old desktop**. This node keeps the school working even when the internet and (briefly) power are down, then syncs to the Cloud Brain when connectivity returns.

---

## 1. What the node does
- Runs the **same** EDIFIS app as the cloud, in `EDIFIS_MODE=local` (single school; multi-tenancy + parent portal stripped).
- Acts as the **campus network**: broadcasts Wi-Fi and serves the app at `https://<school>.local` so staff devices reach it **even with no internet** (the LAN is up).
- Stores this school's data locally (append-only events) and **syncs to the cloud** when the internet returns.
- Reports health/UPS/disk/sync telemetry to the cloud.

> **Important (ADR-016):** the node + browser is the school's offline path. The Flutter app is online-only. So **node uptime is load-bearing** — UPS, auto-restart, and a cold spare are not optional extras.

## 2. Hardware requirements (per school)

| Component | Minimum | Recommended | Why |
|-----------|---------|-------------|-----|
| **CPU** | Dual-core x86-64 | Quad-core (2015+ i5/Ryzen) | Octane keeps the app in memory; per-request load is low — don't chase CPU |
| **RAM** | 8 GB | **16 GB** | The real constraint: Docker + Octane + PostgreSQL + Redis. 4 GB thrashes |
| **Storage** | 256 GB **SSD** | 480–512 GB SSD, **mirrored pair (2× SSD)** | SSD is non-negotiable (biggest speed win on old hardware). A mirror survives one disk death locally |
| **Network** | Gigabit Ethernet + **AP-capable Wi-Fi adapter** | + dedicated access points wired to a switch | One desktop radio ≈ one wing; add APs for full-campus coverage |
| **Power** | **UPS (required)** | UPS sized for safe shutdown + brief ride-through | Drives the ENEO blackout-handling logic |
| **Clock** | system clock | battery-backed hardware RTC | Disciplines timestamps during long offline windows when NTP is unreachable |

**Two cheap upgrades + one spare (do this for every node):** max RAM to 16 GB, fit an SSD (a mirrored pair if budget allows), and keep **one pre-imaged cold-spare desktop** on hand. A single node is a single point of failure for the campus; swap-and-resync is cheaper than any redundancy scheme. The cloud holds the shadow copy + academic master, so a dead node loses no data up to its last sync.

### ⚠ Wi-Fi adapter — verify before bulk purchase
The adapter MUST support **AP/master mode** (so the desktop can broadcast Wi-Fi). Confirmed-good chipsets: Atheros `ath9k_htc`, MediaTek `mt7601u`. For whole-campus reach, treat the desktop as the gateway and add **dedicated access points wired to a switch** rather than relying on one radio.

## 3. Software / OS
- **OS:** Ubuntu Server or Debian (Linux), 64-bit.
- **Stack:** Docker + Docker Compose running app (Octane) + PostgreSQL 16 + Redis (+ Horizon). Same image as cloud (`EDIFIS_MODE=local`).
- **Wi-Fi AP:** `hostapd` (SSID e.g. `EDIFIS-PSSNKWEN`, WPA2/WPA3).
- **DHCP + local name:** `dnsmasq` (hands out addresses; resolves `pssnkwen.local` to the desktop).
- **TLS:** serve over HTTPS with an **internal CA certificate** (so `https://<school>.local` is trusted on campus devices).
- **Disk encryption:** **LUKS** full-disk encryption — a stolen machine yields no readable student/financial data.

## 4. Network setup (campus)
```
   Teacher/Bursar devices (tablets, desktops)
        │  Wi-Fi (EDIFIS-<SchoolCode>, WPA2/WPA3)        │ Ethernet
        ▼                                                ▼
   ┌────────────────────────────────────────────────────────┐
   │  Repurposed desktop = AP (hostapd) + DHCP/DNS (dnsmasq) │
   │  Docker: app + PostgreSQL + Redis   https://<school>.local
   └───────────────────────────┬────────────────────────────┘
                               │ internet (when available)
                               ▼  sync → Cloud Brain (https://<school>.edifis.cm)
```
- Isolate the campus subnet; only the app port is reachable on the LAN; the node initiates outbound sync to the cloud.
- Add dedicated APs (wired to a switch) for buildings beyond one radio's range.

## 5. Power & resilience (ENEO)
- **UPS required.** On power loss the UPS signals the node to **shut down safely** (clean PostgreSQL stop).
- BIOS: **power-on after AC loss** so the desktop reboots when power returns.
- Docker: `restart: always` so the stack comes back unattended.
- UPS state is reported to central monitoring (so a UPS-on-battery is seen centrally, not when a bursar calls).

## 6. Install steps (per school) — summary
Detailed scaffold: `edifis-infra/local-node/` (`docker-compose.yml`, `hostapd.conf.example`, `dnsmasq.conf.example`, `deploy.sh`).
1. Install Ubuntu/Debian; enable **LUKS** full-disk encryption.
2. Max RAM to 16 GB; fit SSD(s) (mirror if possible).
3. Install Docker + Compose. Confirm the Wi-Fi adapter does AP mode.
4. Configure `hostapd` (SSID + WPA2/WPA3) and `dnsmasq` (DHCP + `<school>.local`).
5. Issue/install the internal-CA cert; serve the app over HTTPS.
6. Set `.env` (`EDIFIS_MODE=local`, `EDIFIS_SCHOOL_CODE`, `EDIFIS_NODE_ID`, `SYNC_CLOUD_BASE_URL`, DB creds).
7. `docker compose up -d` (app + PostgreSQL + Redis + Horizon); run migrations; seed roles/initial data.
8. Configure UPS daemon (safe shutdown + telemetry) and BIOS power-on-after-AC.
9. Register the node with the cloud; run a **first sync** and verify data appears centrally.
10. Schedule daily encrypted backups (local + off-box) and run a **restore drill** once.

## 7. What each school must provide (checklist)
- [ ] One repurposed desktop meeting the table above (16 GB RAM + SSD).
- [ ] An **AP-capable Wi-Fi adapter** (verified chipset); optionally extra APs + a switch for coverage.
- [ ] A **UPS** sized for safe shutdown.
- [ ] A nominated **IT teacher** as the node system-administrator (keeps it online, accountable for it).
- [ ] A **cold-spare desktop** (pre-imaged) on hand for the fleet.
- [ ] Physical security for the desktop (locked room) — LUKS covers theft of data, not the machine.
- [ ] Student **QR ID cards** printed (with photo) and rubric/catalogue prepared for issuance.

## 8. Operations runbook (node)
- **Backups:** automated daily encrypted backup of the node DB + files, with off-box copies; verify restorability.
- **Node failure:** swap in the cold spare, restore its data, resync with the cloud (documented, tested once per school).
- **Offboarding a staff member:** disable on cloud (immediate there); the node revokes at next sync (token TTL + revocation list). Records retained.
- **Monitoring:** the node posts health/UPS/disk/last-sync/pending-outbox every few minutes; the cloud alerts on anomalies.

## 9. Go-live checklist (node)
- [ ] LUKS on; SSD(s) fitted; 16 GB RAM.
- [ ] Wi-Fi AP broadcasting; `https://<school>.local` reachable on campus.
- [ ] UPS safe-shutdown + BIOS power-on-after-AC verified.
- [ ] Stack auto-restarts after a power cycle.
- [ ] First sync to cloud succeeds; telemetry visible centrally.
- [ ] Backup runs + restore drill passed.
- [ ] IT teacher trained; cold spare ready.
