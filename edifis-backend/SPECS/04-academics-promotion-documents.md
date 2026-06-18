# Backend SPEC 04 — Academics, Promotion, Documents (Phase 4)

White-paper §5, §6, §14.5. Contract: `mark.schema.json`. Validate document outputs against the real samples in `/RESOURCES/*.pdf`.

---

## 1. Marks entry (T-4.1)

- `POST /academics/marks` creates/edits a mark with **per-record ownership** (the teacher of record). Carries `revision`/`revision_parent` for sync lineage (SPEC 03).
- Sequences/terms model the Cameroon structure (e.g. `T1-Seq1`, `T1-Seq2`, term average). Marks out of 20.
- A mark is visible to student/parent only when `published=true`.
- Build `RecordMark` as the **single** audited write path for a mark: it sets `revision`/`revision_parent`, persists, and writes an `audit_entry(before/after)`. Every mark mutation in the system goes through it.
- **Must-test:** a teacher edits only own-subject marks; published gating hides unpublished from student/parent; an edit bumps revision and logs audit before/after.

> **CARRY-OVER FIX from the Phase 3 review (REQUIRED, T-4.1) — fix `ConflictResolver::resolveMark`.** The Phase-3 version (written ahead of the real Mark model) has two defects that only become reachable now:
> 1. **Not idempotent.** After a linear edit `current.revision` becomes `payload.revision`; replaying the same edit then fails the `revision_parent === current.revision` test and returns a **spurious `cloud_wins` conflict**. Fix: at the top of `resolveMark`, if `current && current.revision === payload.revision` → return `{status:'replay'}` (already applied, no-op).
> 2. **No audit.** Linear edits do a raw `update()` and the cloud-side `cloud_wins` decision writes nothing. Fix: route the linear-edit apply through `RecordMark` (so it audits), and on a `cloud_wins` resolution append `audit_entry(action='mark.conflict', before=current, after=incoming, reason='cloud-wins divergent')` — the rejected edit must be **logged** as well as surfaced (white-paper §5/§8.3).
> 3. **Persist the conflict** (not only return it) so an offline owning teacher reliably receives it on their next pull (a `mark_conflicts` row or equivalent), then include it in the pull envelope's `conflicts[]`.
> - **Must-test:** replay of an already-applied mark edit returns `replay` (NOT a conflict); a linear edit writes an audit entry; a true divergent conflict writes a `mark.conflict` audit entry AND a surfaceable/persisted conflict; the rejected revision is never silently dropped.

## 2. Promotion engine (T-4.2) — white-paper §6

```
ComputePromotion(student, year, ruleset_version):
  for each subject:
      weighted = term_average(subject) * coefficient(subject, pathway)   # General vs Comm/Tech
  yearly_average = sum(weighted) / sum(coefficients)
  outcome = yearly_average >= baseline(ruleset) ? 'advance' : 'repeat'
  record promotion_decision{ student, outcome, yearly_average, ruleset_version, computed_at }
  # principal override is a SEPARATE, explicit, audited action:
OverridePromotion(decision, new_outcome, reason, principal):
  require role=principal
  append promotion_override{ decision_id, old:decision.outcome, new:new_outcome, reason, by:principal, at:now }
  append audit_entry(action='promotion.override', before, after)
```
- **Rule-set versioning:** store the ruleset version per run so any report card reproduces later (white-paper §6).
- Coefficients are configurable per pathway; baseline configurable (default ≥10/20).
- **CARRY-OVER FIX (REQUIRED):** the Phase-4 `ComputePromotion` selects a year's marks via `sequence LIKE 'YYYY%'`, but sequences are named like `T1-Seq1` and never start with the year — so it matches nothing on real data (the test only passed because of how it seeded). Give marks a proper **academic-year linkage** (an `academic_year` column on `marks`, or a sequence→year mapping) and filter on that, not a string-prefix on `sequence`. Add a test with realistically named sequences proving the right marks are selected.
- **Must-test:** coefficient weighting matches a hand-computed sample; baseline boundary (exactly 10) advances; an override is audited and does not edit the original decision (append).

## 3. Documents (T-4.3) — white-paper §14.5

Generate with DOMPDF / Laravel Excel, matched to the school's real layout:
- **Report cards** (per term/sequence, bilingual where required, coefficient structure) — validate against `RESOURCES/USS.pdf, LSA.pdf, f1.pdf`, etc.
- **Mark sheets / broadsheets** (class-wide).
- **Transcripts / attestations** (cumulative, follow the Master PEA ID).
- **Fee receipts / attendance registers** (printable from the append-only ledgers & sessions).
- **Must-test:** a report-card PDF renders for a seeded student with correct averages/rank/coefficient totals; a fee receipt total equals the ledger sum; an attendance register matches session events.

## Outputs
```
app/Domain/Academics/{Actions/{RecordMark,PublishResults}.php, Models/{Mark,Subject,Sequence}.php, Policies/MarkPolicy.php}
app/Domain/Promotion/{Actions/{ComputePromotion,OverridePromotion}.php, Models/{PromotionDecision,Ruleset}.php}
app/Domain/Documents/{Actions/{RenderReportCard,RenderReceipt,RenderRegister,RenderTranscript}.php, Templates/*}
tests/Feature/{MarksTest, PromotionEngineTest, DocumentOutputTest}.php
```
