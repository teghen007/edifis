# Backend SPEC 05 — Compliance & Operations (Phase 5)

White-paper §14.2, §14.3, §14.4, §2.1, §11.

---

## 1. Account provisioning & credential bootstrapping (T-5.1) — §14.3

- **Staff:** admin creates the account; one-time activation link / temp password forced to reset on first login; Spatie role assigned at creation.
- **Students:** generated in bulk from the migration import against the Master PEA ID; scope follows captured consent.
- **Guardians (hardest):** bind a guardian phone to each child at enrolment; issue a short **claim code** (printed on enrolment slip / report card) the guardian redeems once to set a credential. Unredeemed codes expire. Where no smartphone, parent-facing reads also flow via notifications / printed reports.
- **No default passwords survive go-live** — every path forces a reset.
- **Must-test:** claim code redeemable once then expires; staff temp password forces reset; bulk student provisioning ties accounts to PEA IDs.

## 2. Validating data-migration pipeline (T-5.2) — §14.2

```
ImportRecords(type, excelFile):
  rows = parse(excelFile)
  for row: validate(schema, required, duplicates, malformed)
  if any invalid: return report{ accepted:[], rejected:[{row, reasons}] }  # never import silently
  dryRun ? stage(rows) : commit(rows)
  reconcile(counts, balances) against source totals
```
- Templates per record type: students, classes, prior marks, outstanding balances, guardians.
- Dry-run into staging; staff verify a sample; freeze; final import; reconcile; go live.
- **Must-test:** malformed/duplicate rows are rejected with reasons; dry-run mutates nothing; reconciliation flags a count/balance mismatch.

## 3. Backup / restore (T-5.3) — §14.4

- Automated daily encrypted backups (cloud DB+files; node DB+files) with off-box copies.
- A rehearsed, timed restore drill script. A node-failure runbook: swap the cold-spare desktop, restore, resync.
- **Must-test:** backup artifact is produced + restorable into a scratch DB; restore drill script exits non-zero on a corrupt archive.

## 4. Monitoring telemetry (T-5.4) — §2.1, §11

- Node posts `POST /monitoring/node-status` every `MONITORING_REPORT_INTERVAL_SECONDS`: disk_ok, ups_on_battery, last_sync_at, pending_outbox.
- Cloud aggregates; an alert surfaces a dead disk / node offline / UPS on battery centrally (not when a bursar calls).
- **Must-test:** a node missing N intervals is flagged offline; ups_on_battery raises an alert state.

## Outputs
```
app/Domain/Auth/Actions/{CreateStaffAccount,IssueClaimCode,RedeemClaimCode}.php
app/Domain/Students/Actions/BulkProvisionFromImport.php
app/Domain/Documents/.. (import templates live with Excel)
app/Domain/Monitoring/{Actions/RecordNodeStatus.php, Services/NodeHealth.php}
app/Console/Commands/{BackupRun,RestoreDrill}.php
tests/Feature/{ProvisioningTest, MigrationImportTest, MonitoringTest}.php
```
