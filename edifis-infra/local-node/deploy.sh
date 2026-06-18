#!/usr/bin/env bash
# Provision a repurposed desktop as an EDIFIS Lite Local Node (white-paper §3, §11, §13.2).
# Run once per campus desktop. Builder (T-0.4/T-5.3): flesh out the TODO steps.
set -euo pipefail

echo "[1/7] Preconditions: 16GB RAM + SSD (mirrored pair preferred), AP-capable Wi-Fi adapter"
# TODO: assert RAM >= 8GB (warn if <16), root disk is SSD, wlan supports AP/master mode

echo "[2/7] Full-disk encryption (LUKS) check"
# TODO: verify root is on a LUKS volume; refuse to proceed in prod if not

echo "[3/7] Install Docker + compose plugin"
# TODO: idempotent install

echo "[4/7] Wi-Fi AP + DHCP"
# cp hostapd.conf.example /etc/hostapd/hostapd.conf ; cp dnsmasq.conf.example /etc/dnsmasq.conf
# TODO: enable+start hostapd, dnsmasq

echo "[5/7] BIOS/power: set power-on after AC loss; install UPS daemon -> safe shutdown + telemetry"
# TODO: nut/apcupsd hook posting ups_on_battery to MONITORING_ENDPOINT

echo "[6/7] Bring up the node stack (restart:always)"
docker compose -f "$(dirname "$0")/docker-compose.yml" up -d

echo "[7/7] Register node + schedule daily encrypted backup with off-box copy (§14.4)"
# TODO: cron the backup command; verify a restore drill has been run

echo "Done. Health: curl -s https://pssnkwen.local/api/health"
