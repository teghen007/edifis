# EDIFIS — Server Deploy Runbook (picks up AFTER the repo is cloned to the VPS)

For DeepSeek + the user. The repo is on the server at `/opt/edifis` (cloned from GitHub). Now deploy the consolidated single-compose stack. The user runs commands on the server via `ssh edifis` and pastes output back.

> **Run the long build inside `tmux`** so it survives an SSH drop: `apt -y install tmux` → `tmux` → work → detach `Ctrl+b d` → reattach `tmux attach`.

## Optimizations carried over from the local attempts (IMPORTANT — saves hours)
1. **On the server, use the REAL Octane/Swoole image** (`edifis-backend/Dockerfile`). The Swoole compile that *stalled on the user's home internet* builds fine here (data-center bandwidth). Do **not** carry over the lean `local-quick` workaround — the VPS wants the production Octane setup.
2. **Remove the local hot-fix**: delete the temporary `error_reporting(E_ALL & ~E_DEPRECATED); ini_set('display_errors','0');` lines added to `edifis-backend/public/index.php`. They were only for PHP 8.5 on the dev laptop; the server image is **PHP 8.3**, so the `PDO::MYSQL_ATTR_SSL_CA` deprecation doesn't fire.
3. **Deploy the consolidated `docker-compose.yml`** (app[Octane] + db + redis + horizon + scheduler + proxy[Caddy]) — one file, one `.env`. No observability yet.
4. **Disk:** a full disk silently breaks Docker/NDK builds (it bit us locally). `df -h` first; KVM2 (≈40–80 GB) is fine, just confirm.

## Steps
1. **Server prep** (if not done): `apt update && apt -y install docker.io docker-compose-plugin git tmux && systemctl enable --now docker`
2. **Configure env:**
   ```bash
   cd /opt/edifis/edifis-infra
   cp .env.example .env   # or the consolidated env template
   nano .env
   ```
   Set: `EDIFIS_MODE=cloud`, `APP_ENV=production`, `APP_DEBUG=false`, `TENANCY_CENTRAL_DOMAIN=myedifis.com`, a **strong `DB_PASSWORD`**, Redis on, and mail (for OTP). Leave VAPID/FCM blank for now (push can come later) or fill if ready.
3. **DNS** (registrar for myedifis.com): `A @ → SERVER_IP`, `A * → SERVER_IP`, `A www → SERVER_IP`. (Wildcard `*` is what makes school subdomains work.)
4. **Build + start (inside tmux):**
   ```bash
   docker compose up -d --build
   ```
   First build pulls images + compiles — minutes on the VPS, not hours.
5. **App key + central migrations:**
   ```bash
   docker compose exec app php artisan key:generate --force
   docker compose exec app php artisan migrate --force
   ```
6. **Onboard the first school** (creates tenant + `pssnkwen.myedifis.com` + migrations + roles + a Principal with a claim code):
   ```bash
   docker compose exec app php artisan edifis:onboard-school pssnkwen \
     --name="Presbyterian Secondary School Nkwen" --principal-email=principal@pssnkwen.example
   ```
7. **Verify:**
   - `https://myedifis.com` → homepage loads (Caddy auto-HTTPS).
   - `https://pssnkwen.myedifis.com/staff` → staff login (cert auto-issued on first hit via the `domain-allowed` gate).
   - Principal claims the account + logs in.
   - `curl https://pssnkwen.myedifis.com/api/health` → clean JSON.
   - Queue alive: `docker compose exec app php artisan horizon:status` (or check the horizon container is up).

## Common errors → fixes
- **Caddy can't get a cert** for a subdomain → the tenant must exist first (onboard the school), and `/api/tenancy/domain-allowed` must return 200 for it; check DNS `*` record resolves to the server.
- **502 from Caddy** → the `app` container isn't ready; `docker compose logs app`.
- **DB connection refused** → `db` not healthy yet; `docker compose ps`, wait for healthy.
- **Build out of space** → `df -h`; `docker system prune -af` (we hit this locally).

## After it's live — bring in the architect (Claude) for a go-live review
Before real student data: TLS + secrets, the `pea_admin` onboarding gate, backups (daily encrypted DB dump + off-box), and a restore drill. Then onboard the remaining schools.
