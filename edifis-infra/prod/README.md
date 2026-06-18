# EDIFIS Production — myedifis.com + seamless school onboarding

Deploys the Cloud Brain behind Caddy with **automatic HTTPS for every school subdomain**. Onboarding a school = **one command**; its `https://<code>.myedifis.com` works immediately (Caddy issues the cert on first visit, gated by the app).

## 0. Prerequisites
- A **public Linux VPS** (Ubuntu), ports **80 + 443** open, Docker + Compose installed.
- DNS for `myedifis.com` (your registrar) →
  - `A  @            → <VPS IP>`
  - `A  *            → <VPS IP>`   (wildcard: every `*.myedifis.com` resolves to the VPS)
  - `A  www          → <VPS IP>`

## 1. Deploy
```bash
git clone <repo> /opt/edifis && cd /opt/edifis/edifis-infra/prod
cp .env.prod.example .env.prod && nano .env.prod         # set DB pass, keys, mail, FCM/VAPID
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force   # central tables
```
Caddy auto-provisions TLS for `myedifis.com`. The homepage is `prod/landing/index.html`.

## 2. Onboard a school (the seamless part — needs the `edifis:onboard-school` command, see build note)
```bash
docker compose -f docker-compose.prod.yml exec app \
  php artisan edifis:onboard-school pssnkwen \
    --name="Presbyterian Secondary School Nkwen" \
    --principal-email=principal@pssnkwen.example
```
This single command:
1. Creates the **tenant** + its **domain** `pssnkwen.myedifis.com` (stancl/tenancy).
2. Runs the tenant **migrations** + seeds roles/permissions + starter data.
3. Creates the **Principal** account with a one-time claim code (forced reset on first login).
4. Prints the **login URL** + claim code to hand to the school.

Because `*.myedifis.com` already resolves to the VPS and Caddy uses **on-demand TLS gated by the app's `domain-allowed` endpoint**, `https://pssnkwen.myedifis.com` is live with a valid cert the moment a school is onboarded — **no cert or DNS step per school.**

> Optional: a **super-admin Filament page** on `myedifis.com` ("Schools") can do the same onboarding via a form instead of the CLI.

## 3. How the auto-TLS gate works (security)
Caddy only issues a cert for a subdomain if `GET /api/tenancy/domain-allowed?domain=<host>` returns `200`. The app returns `200` only for **registered tenant domains**, `404` otherwise — so nobody can make Caddy mint certs for arbitrary hostnames. (See `Caddyfile` → `on_demand_tls.ask`.)

## 4. After deploy — checklist
- [ ] Homepage loads at `https://myedifis.com`.
- [ ] `edifis:onboard-school` creates a tenant; `https://<code>.myedifis.com` loads with valid TLS.
- [ ] Principal can claim the account and log in.
- [ ] Backups + monitoring configured (`INFRA-CLOUD-BRAIN.md`).
- [ ] VAPID + FCM keys set; a test push delivers.

## Files
| File | Purpose |
|------|---------|
| `docker-compose.prod.yml` | app + horizon + postgres + redis + caddy |
| `Caddyfile` | apex homepage + on-demand-TLS subdomains → app |
| `.env.prod.example` | production env template |
| `landing/index.html` | the `myedifis.com` homepage |

> **To build (hand to DeepSeek):** the `edifis:onboard-school` artisan command and the `GET /api/tenancy/domain-allowed` ask-endpoint. See the chat prompt / BUILD_PLAN Phase 13.
