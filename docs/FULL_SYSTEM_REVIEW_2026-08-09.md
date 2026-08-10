# AMS Full-System Review - 2026-08-09

## Executive Verdict

**Production status: NO-GO.** The AMS has broad workflow and document coverage, but it is not yet a defensible Certification Body system of record. This review found and contained several critical trust-boundary problems. The remaining work is mainly controlled requirements data, database-backed end-to-end tests, immutable records, scale migration, notifications, backup/restore proof, and production deployment validation.

The review used twelve independent perspectives: Certification Coordinator, Lead Auditor, Technical Manager, Quality Manager, Technical Reviewer, Decision Maker, General Manager, ISO/IEC 17021-1 assessor, Software Architect, Database Architect, QA Engineer, and DevOps Engineer.

## Classification

### Working Correctly

- Tenant login, role assignments, personnel links, client masters, reference masters, and audit logging exist.
- Certification files cover application through certificate, surveillance, recertification, feedback, finance, and controlled document output.
- Separate Stage 1 and Stage 2 audit events, plans, reports, NCR/CAPA, Technical Review, and decision records exist.
- PDF generation uses database records, and certificate Word download now produces a real DOCX.
- Existing automated suite passes after the containment changes.

### Working But Needs Improvement

- Role permissions are broad and require more database-backed conflict tests for every workflow transition.
- Document layouts are mature, but approved documents still need immutable snapshots, hashes, supersession history, and stronger record locking.
- Surveillance date/status structures exist, but scheduled reminder delivery is not operationally proven.
- Public certificate verification now respects status, but suspension, withdrawal, reinstatement, and replacement-certificate histories need full workflows.
- Audit trails exist, but field-level change presentation and management/accreditation registers need improvement.

### Partially Implemented

- The integrated-audit data model now supports one question mapped to several standards and clauses, but controlled QSI-authored mappings have not yet been loaded or connected to audit execution/report output.
- Client portal screens exist, but authorization and data isolation require full end-to-end tests.
- SMTP, reminders, finance, website leads, backup, and management reporting have structures or plans but are not production-proven.
- Docker deployment files now exist, but Docker is not installed on this machine, so the stack has not been run here.

### Incorrect Or Dangerous - Contained In This Review

- GET/view/PDF actions created report records and proxy-confirmed evidence. Reads are now side-effect free.
- Cycle Builder is a required Super Admin-only digitalization function. It now revalidates the preview on the server, rejects exact duplicate client/cycle records, prepares each client within a transaction, and records the complete run and audit trail.
- Normal interactive regulated actions now require the assigned Auditor, Technical Reviewer, Decision Maker, or GM identity. Cycle Builder remains the separately logged Super Admin digitalization path for complete client-cycle preparation.
- Decision Maker and General Manager approval were mixed. They are separate backend actions with independence checks and finalized-record locks.
- Unsupported schemes were active. Operations are now locked to ISO 9001:2015, ISO 14001:2015, ISO 45001:2018, ISO 22000:2018, and HACCP/Codex CXC 1-1969.
- The clause library consisted of 249 synthetic placeholder rows. They are retained for traceability but quarantined and inactive.
- 140 conformity sections were proxy-confirmed. They are retained but now unconfirmed and require an appointed auditor's explicit act.
- Public certificate verification treated records as valid without sufficient lifecycle status handling. It now displays valid, suspended, withdrawn, expired, or invalid state.
- Certificate DOCX download returned PDF content. It now returns a genuine Word package with the correct MIME type and filename.
- Client portal queries referenced child-table tenant columns that do not exist. Those queries now use the tenant-owned parent relationship.

### Missing

- Authorized, source-validated clause/requirement mappings and approved QSI audit-question content.
- The batch digitalization path validates each row, rejects exact duplicate client/cycles, rolls back a failed client transaction, and reports row errors. Downloadable error export, batch approval, and resume/retry controls remain to be added for the 700-client migration.
- Database-backed feature/E2E tests for complete positive and negative certification paths.
- Complaints/appeals and complete suspension/withdrawal/reinstatement controls.
- Immutable approval/document snapshots and reliable document supersession.
- Operational SMTP, job scheduler, due-date reminders, website-lead qualification, off-site backups, restore drill, monitoring, and production reporting.
- Proven Hostinger/VPS deployment and disaster-recovery runbook.

## Integrated Audit Architecture

The new controlled model implements the required relationship:

`Audit requirement/question -> standard and clause mappings -> one response/evidence set -> finding -> NCR/CAPA -> report snapshots`

It supports HACCP with ISO 22000 and Annex SL integrated audits without concatenating duplicate checklists. Requirements are inactive until approved, and clause references are snapshotted into each audit response so later library edits do not rewrite historical evidence.

## Verification Evidence

- Full automated suite: **38 tests / 273 assertions passing**.
- Isolated HTTP validation confirmed workflow and file views do not create report drafts or sections.
- Generated PDF was validated by `%PDF-` signature.
- Certificate DOCX was validated as a ZIP/Office package with the correct Word MIME type.
- Corrective migrations were first exercised against `qsi_ams_validation_20260809`, then applied deliberately to the local database after backup.
- Local database after migration: five active approved baselines; 249 synthetic clauses quarantined; 140 proxy confirmations removed; four integrated-audit tables present.

## Priority Plan

1. Load authorized requirement references and approve QSI-authored integrated questions; wire them into audit execution and reports.
2. Extend batch controls, reconcile a pilot batch, and then complete the 700-client migration.
3. Add database-backed end-to-end tests for every role conflict, gate, lifecycle status, and document lock.
4. Implement immutable approval/document snapshots plus complaints, appeals, suspension, withdrawal, reinstatement, and replacement history.
5. Configure/test SMTP, scheduler, reminders, lead handling, off-site backup/restore, monitoring, and registers.
6. Run the Docker stack in a test environment and complete a Hostinger VPS deployment rehearsal.

No content has been pushed to GitHub as part of this review.
