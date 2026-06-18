# EDIFIS — Hostinger VPS: SSH Setup + Run the AI on the Server

Goal: buy a VPS, connect with one command (`ssh edifis`), then run an AI agent **on the server** (fast internet there) to deploy EDIFIS. Done once.

---

## 1. Buy the VPS (Hostinger)
- Hostinger → **VPS hosting** → a small KVM plan (KVM 2: ~2 vCPU / 8 GB RAM is plenty to start; KVM 1 works for testing).
- OS template: **Ubuntu 24.04** (or 22.04).
- During setup Hostinger gives you: a **server IP address** and a **root password** (and lets you add an SSH key).
- Note the **IP** — you'll need it below.

## 2. Make an SSH key on your Windows PC (one time)
Open **PowerShell** and run:
```powershell
ssh-keygen -t ed25519 -C "edifis"
```
- Press **Enter** to accept the default location (`C:\Users\teghe\.ssh\id_ed25519`).
- Press **Enter** twice for an empty passphrase (or set one).
This creates two files: a **private** key (`id_ed25519`, keep secret) and a **public** key (`id_ed25519.pub`, safe to share).

Show the public key (copy the whole line it prints):
```powershell
type $env:USERPROFILE\.ssh\id_ed25519.pub
```

## 3. Put the public key on the server
**Easiest (Hostinger panel):** hPanel → your VPS → **SSH Keys** → **Add SSH key** → paste the public key from step 2 → save. (You can also paste it when you first install/reinstall the OS.)

**Or from your PC (if you have the root password):**
```powershell
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh root@YOUR_SERVER_IP "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
```
(enter the root password once).

## 4. Create the SSH shortcut so `ssh edifis` works
Create/edit the file `C:\Users\teghe\.ssh\config` (no extension). In PowerShell:
```powershell
notepad $env:USERPROFILE\.ssh\config
```
Paste this (replace `YOUR_SERVER_IP`), save:
```
Host edifis
    HostName YOUR_SERVER_IP
    User root
    IdentityFile ~/.ssh/id_ed25519
    ServerAliveInterval 60
```
Now connect with just:
```powershell
ssh edifis
```
✅ You should land on the server's command line (`root@srv...:~#`) with no password.

## 5. Prep the server (run these once, ON the server after `ssh edifis`)
```bash
apt update && apt -y upgrade
apt -y install docker.io docker-compose-plugin git curl
systemctl enable --now docker
docker --version
```

## 6. Get EDIFIS onto the server
Best: push your project to a **private GitHub repo**, then on the server:
```bash
git clone https://github.com/<you>/edifis.git /opt/edifis
```
(Or copy it up from your PC with `scp -r "C:\Users\teghe\OneDrive\Dokumenty\SMS RESEARCH" edifis:/opt/edifis` — but git is cleaner.)

Then deploy the production stack (fast pulls here!):
```bash
cd /opt/edifis/edifis-infra/prod
cp .env.prod.example .env.prod && nano .env.prod   # set DB password, keys, mail, domain
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```
Point `myedifis.com` DNS (`@`, `*`, `www`) at the server IP — Caddy auto-issues HTTPS. See `edifis-infra/prod/README.md`.

## 7. Run an AI agent ON the server (your "send an AI inside" idea)
The server has fast internet, so an AI agent there avoids all your local download pain. On the server:
```bash
# Node (for Claude Code) — once
curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt -y install nodejs
npm install -g @anthropic-ai/claude-code
# then run it inside the project
cd /opt/edifis && claude
```
- It runs **in the server**, with full local access + fast internet, and can build/run Docker, deploy, debug — exactly what's been painful locally.
- (DeepSeek/other CLI agents install similarly; or you SSH in and paste their commands.)

> Tip: run long jobs in `tmux` so they survive a dropped SSH connection:
> `apt -y install tmux` → `tmux` → work → detach with `Ctrl+b then d` → reattach later with `tmux attach`.

---

## Quick reference
| Action | Command (from your PC) |
|--------|------------------------|
| Connect | `ssh edifis` |
| Copy a file up | `scp localfile edifis:/opt/edifis/` |
| Run one remote command | `ssh edifis "docker ps"` |
