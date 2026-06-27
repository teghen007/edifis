# EDIFIS prod-DB tunnel for the Postgres MCP server.
#
# The production database is NOT exposed to the internet (no published port) —
# this opens a local port 15432 that forwards, over SSH, to the Postgres
# container on the VPS. Leave this window open while you want Claude's
# Postgres MCP to read the live database. Press Ctrl+C to close it.
#
#   Usage:  pwsh scripts/db-tunnel.ps1
#
# The Postgres MCP is configured to connect to localhost:15432, so it only
# works while this tunnel is running.

$ErrorActionPreference = 'Stop'

Write-Host 'Looking up the Postgres container address on the VPS...'
$ip = (ssh edifis "docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' prod-postgres-1").Trim()

if (-not $ip) {
    Write-Error 'Could not find the prod-postgres-1 container. Is the stack up?'
    exit 1
}

Write-Host "Tunnel: localhost:15432  ->  ${ip}:5432 (via VPS)"
Write-Host 'Keep this window open. Ctrl+C to stop.'
ssh -N -L "15432:${ip}:5432" edifis
