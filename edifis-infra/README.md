# edifis-infra — Deployment

Docker stacks for the Cloud Brain and the Lite Local Node, plus the desktop-as-WAP config, backups, and monitoring. White-paper §3, §4, §13, §14.4.

## Folders
| Path | What |
|------|------|
| `lab/` | **Local test lab** — 1 Cloud Brain + 2 campus nodes on one desktop, with simulated internet outages for offline-first sync testing. Start here: [`lab/LAB.md`](lab/LAB.md). |
| `cloud/docker-compose.yml` | Cloud Brain dev/prod stack: app (Octane) + PostgreSQL 16 + Redis + Horizon. Dedicated-vCPU DB in prod (§13.1, ADR-012). |
| `local-node/docker-compose.yml` | Lite Local Node: same app image, `EDIFIS_MODE=local`, single school. |
| `local-node/hostapd.conf.example` | Wi-Fi AP (WPA2/WPA3) so staff connect to the desktop when internet is down (§3). |
| `local-node/dnsmasq.conf.example` | DHCP + resolves `pssnkwen.local` on the campus subnet (§3). |
| `local-node/deploy.sh` | Provision a repurposed desktop: Docker, LUKS check, BIOS power-on note, UPS hook, restart:always. |

## Cloud vs node = env, not different images (ADR-004)
Both compose files run the **same** backend image; only env differs. Never fork the image.

## Hardening (white-paper §3 engineering note)
- Confirm Wi-Fi adapter supports AP/master mode before purchase (e.g. `ath9k_htc`, `mt7601u`).
- WPA2/WPA3 SSID, isolated subnet, HTTPS with an internal CA cert, full-disk LUKS.
- For whole-campus reach: desktop as gateway + dedicated APs wired to a switch.

## Power (§11)
UPS triggers safe shutdown; BIOS power-on after AC loss; `restart: always` revives the stack; UPS state posted to `/monitoring/node-status`.

## Backups (§14.4)
Daily encrypted backups (cloud + node) with off-box copies; a rehearsed restore drill; a node-failure swap runbook using the pre-imaged cold spare.

## Dev quickstart
```bash
docker compose -f cloud/docker-compose.yml up        # cloud-mode dev stack
docker compose -f local-node/docker-compose.yml up   # node-mode dev stack
curl -s localhost:8000/api/health                    # {status, mode, version}
```
