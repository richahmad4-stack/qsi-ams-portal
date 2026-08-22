# AMS Project Progress

QSI AMS is a CodeIgniter 4 / PHP / MariaDB Certification Body Audit Management System for application, review, proposals/contracts, audit programmes, appointments, audit plans/reports, NCR/CAPA, Technical Review, decisions, certificates, surveillance, recertification, feedback, finance, roles, and controlled documents.

## Complete / Working

- Tenant login, multiple roles, personnel, client/standard masters, reference data, audit logging, role dashboards, finance, and controlled QSI document templates.
- Certification workflow through Stage 1, Stage 2, NCR/CAPA, Technical Review, decision, certificate issue, Surveillance 1/2, recertification, and feedback.
- Super Admin-only Cycle Builder for controlled client-cycle digitalization; normal workflow actions retain assigned-role and conflict-of-interest controls.
- Approved operational baselines remain ISO 9001:2015, ISO 14001:2015, ISO 45001:2018, ISO 22000:2018, and HACCP/Codex CXC 1-1969.
- Controlled integrated audit catalogue: one QSI requirement can map to multiple standards/clauses while retaining clause snapshots, evidence, findings, NCR/CAPA, and confirmation identity.
- Canonical application flow: selected standards and saved answers feed Application, Application Review, workflow screens, and PDFs from the same records.
- Canonical audit-report flow: controlled requirement responses feed execution, full-file views, and PDFs; legacy report sections are fallback only.
- Super Admin can view and edit complete stage files. Compliance Auditor remains read-only. Report, NCR/CAPA, Technical Review, Decision, and PDF cards open the correct stage tab.
- Technical Review and Decision checklists display as structured workflow tables and controlled PDFs.
- Clause-specific NCR logic no longer rotates unrelated themes. HACCP wording is not mistaken for CCP; linked NCR findings and CAPA are repaired against their actual requirement.
- Three repeat-safe 2024 food-safety demo cycles: HACCP-only, ISO 22000:2018-only, and combined HACCP + ISO 22000. Single-standard files stay strictly isolated.
- HACCP catering application verified with correct scope-specific content, 2 HACCP plans in Application and Review, Saudi Arabia/Riyadh identity fields, and no bakery or unselected-standard contamination.
- Professional QSI PDF headers/layouts and certificate PDF/DOCX outputs retained. Latest Application, Application Review, and Stage 2 Audit Report were rendered and visually inspected.
- Deployment baseline includes PHP 8.3/Apache, MariaDB, Caddy, persistent storage, health checks, and deployment documentation.
- Local Windows startup is repaired and verified: the launcher resolves the current repository, starts the preserved `.mysql-data` MariaDB instance on port 3307, and serves the real CodeIgniter AMS on port 8080. The Super Admin seeder and browser login were verified end to end.
- Cross-computer continuation is documented in `AMS_HANDOVER_CURRENT.md`; the older `SESSION_HANDOFF_AMS.md` is retained as superseded historical context.
- Automated suite: **45 tests / 340 assertions passing**.
- Google Apps Script AMS project package added under `google-appscript-ams/`, including Apps Script manifest/source/UI, no-paid Google Sheets backend, preserved optional Cloud SQL backend/schema, Drive/Docs document generation workflow, deployment notes, RBAC, workflow, finance, certificate verification, and audit-trail scaffolding.
- Google Apps Script deployment updated and verified online: the no-paid Google Sheets backend now batch-seeds baseline records, creates the first admin user, and renders the private QSI AMS dashboard through the active web app deployment.
- Google Apps Script AMS blank-load issue fixed in the live Version 5 deployment: the UI now renders dashboard/navigation immediately and skips repeated schema checks for existing Google Sheets storage.
- Google Apps Script AMS live deployment now includes an idempotent demo-client seeder and displays the seeded `DEMO-001` client file with ISO 22000:2018 and HACCP standards.
- Google Apps Script AMS live Version 10 now seeds and displays a full demo certification cycle: application, application review, proposal, contract, audit programme/event/plan/timetable, appointment, requirement responses, NCR/CAPA, technical review, certification decision, certificate, invoice, payment, and richer per-tab workflow tables.

## Current Focus

The canonical report connection, Super Admin full-file access, local login/startup repair, and Google Apps Script AMS package are complete. Continue systematic workflow/PDF validation on the ISO 22000-only and combined food-safety demo cycles, then repeat the same E2E checks for surveillance and recertification stages.

## Next

1. Add database-backed feature/E2E tests for all role conflicts and full certification/surveillance/recertification gates.
2. Add immutable approval/document snapshots and hashes, complaints/appeals, suspension/withdrawal/reinstatement, and replacement-certificate history.
3. Complete batch migration controls for the 700-client import: approval, downloadable errors, resume/retry, and reconciliation report.
4. Configure and test SMTP, appointment/reminder emails, scheduler, website-lead conversion, backups/restore drill, monitoring, and management/accreditation registers.
5. Validate the Docker deployment on a test VPS; Docker is not installed on this Windows machine.

## Safety

Only explicitly named demo files are repaired by `RepairCanonicalDemoDataSeeder`; it does not delete or rewrite unrelated clients. Runtime uploads, local database state, `.env`, credentials, logs, sessions, and generated PDFs remain outside Git.
