# QSI AMS - Current Project Handover

> Updated: 2026-08-20
> Workspace: `C:\Users\PCD\Documents\AMS`
> Repository: `https://github.com/richahmad4-stack/qsi-ams-portal.git`
> Branch: `main`
> Owner: Ahmad / QSI-CERT

## Instruction for the Next Codex

Use this prompt:

> Work in the QSI AMS repository. Read `AGENTS.md`, `AMS_HANDOVER_CURRENT.md`, and `PROGRESS.md` completely before changing code. Inspect the current code and database first. Continue from the existing implementation; do not rebuild working modules or change approved certification rules without evidence and Ahmad's approval. Run tests before and after changes. Update `PROGRESS.md`, commit, and push at the end of the session.

On the other computer:

```powershell
cd C:\Users\PCD\Documents\AMS
git pull origin main
composer install
```

If `.env` is absent, create it from `env.example` and configure local database and administrator settings. Never copy a live `.env` into Git.

For a new empty database:

```powershell
php spark migrate
php spark db:seed InitialAmsSeeder
php spark db:seed InitialAdminSeeder
vendor\bin\phpunit
```

For the controlled 2024 food-safety demonstration cycles, only when required:

```powershell
php spark db:seed Demo2024FoodSafetyCycleSeeder
php spark db:seed RepairCanonicalDemoDataSeeder
```

`RepairCanonicalDemoDataSeeder` is deliberately limited to three named demonstration clients. Do not broaden it to unrelated clients.

## Git State

This original computer uses `.repo-git` as its active Git directory:

```powershell
git --git-dir=.repo-git --work-tree=. status
```

A normal clone uses ordinary Git commands.

At handover preparation:

- `origin/main`: `7c1f35f` - canonical workflow/report repair.
- Local `main`: also contains `2cec82f` - local database/login startup repair.
- The startup commit was not yet on GitHub because Windows GitHub credentials were unavailable.
- This handover commit is newer. Treat `git log -3 --oneline` and `git ls-remote origin` as authoritative.

Do not claim the other computer is current until the newer commits are visible on `origin/main`.

## Purpose and Business Position

QSI AMS is a multi-tenant CodeIgniter Certification Body Audit Management System. It digitalizes controlled certification-cycle records and produces workflow screens, registers, professional PDFs, and certificate DOCX files.

The Cycle Builder is an essential Super Admin-only digitalization function. It prepares complete controlled client-cycle records from authorized inputs. Do not describe it as fake, fabricated, or optional. Other users continue through assigned role screens and independence controls.

The system covers:

- clients, sites, processes, standards, scopes, personnel and competence;
- applications and independent application review;
- proposals, contracts, finance and acceptance;
- three-year audit programmes;
- separate appointments, plans, reports and files for each audit stage;
- NCR/CAPA, Technical Review, Decision, GM approval and certificates;
- Surveillance 1, Surveillance 2 and recertification;
- feedback, reminders, notifications and controlled documents;
- role dashboards and read-only compliance-audit access;
- PDF workflow documents and PDF/DOCX certificates.

## Approved Standards

The operational baseline remains:

- ISO 9001:2015
- ISO 14001:2015
- ISO 45001:2018
- ISO 22000:2018
- HACCP / Codex CXC 1-1969

Do not move to a 2026 edition automatically. A transition requires a controlled business decision, licensed references, migration rules, template updates, competence review and Ahmad's approval.

Do not reproduce copyrighted ISO or IAF documents in the repository. Internal wording, mappings and calculations must be traceable to QSI's controlled licensed sources.

## Technology

- CodeIgniter `4.7.3`
- PHP `8.2.12` locally; declared production target PHP `8.3`
- MariaDB / MySQL
- DomPDF for PDFs
- PHPWord for certificate DOCX
- Bootstrap, DataTables and Chart.js
- PHPUnit tests under `tests/Unit`
- Docker/Caddy deployment baseline in `compose.yaml` and `docker/`

Do not weaken the production PHP requirement because the local CLI is PHP 8.2.12.

## Local Startup and Login

Start with:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\start-local.ps1
```

or:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File START-AMS-LOCAL.ps1
```

Verified local services:

- Database directory: `.mysql-data` (ignored by Git)
- MariaDB: `127.0.0.1:3307`
- AMS: `http://localhost:8080/login`
- Tenant: `QSI`
- Super Admin email: `admin@qsi.local`
- Password: local `.env` value `AMS_ADMIN_PASSWORD`; never put it in Git

If login is rejected:

1. Confirm the page title is `Sign in | QSI AMS`. `QSI Compliance Portal - CB 17021` is a stale mock server.
2. Stop the stale PHP process occupying port 8080.
3. Start `.mysql-data` on port 3307 with the current launcher.
4. Run `php spark db:seed InitialAdminSeeder` to synchronize the local account hash, status and Super Admin role.
5. Open `/login` and test `/dashboard`.

Never delete or initialize `.mysql-data` to solve login.

## Canonical Data Rule

Workflow forms, workflow views and generated documents must use the same canonical database records.

```text
Application inputs
  -> saved answers and selected standards
  -> Application screen
  -> Application Review
  -> workflow file tabs
  -> controlled PDFs

Audit execution inputs
  -> controlled requirement responses and linked findings
  -> full stage file
  -> NCR/CAPA
  -> Technical Review and Decision
  -> controlled PDFs
```

Never create a PDF-only data path, hardcode a different scope in a template, or show clauses in the form but omit them from the PDF. Legacy report sections are fallback only when canonical responses do not exist.

For every form/PDF repair:

1. Identify the source table/model and save path.
2. Identify the workflow read path.
3. Identify the document-generation read path.
4. Make all three use the same record.
5. Browser-test saved data and visually inspect the PDF.

## Certification Workflow

Initial certification:

1. Client Application and selected standards.
2. Technical Manager review.
3. Quality Manager independent approval.
4. Proposal and client response.
5. Contract.
6. Three-year audit programme.
7. Separate Stage 1 appointment.
8. Stage 1 plan, checklist, execution and report.
9. Separate Stage 2 appointment, plan, checklist, execution and report.
10. NCR/CAPA closure where applicable.
11. Independent Technical Review.
12. Independent certification decision.
13. General Manager final approval.
14. One certificate per approved standard.
15. Client feedback.

Surveillance 1 and 2 each have their own appointment, plan, report, NCR/CAPA, Technical Review, maintain-certification decision, status and PDFs. Recertification is a separate audit event.

Cycle dates:

- Surveillance 1 = issue date + 1 year - 1 day
- Surveillance 2 = issue date + 2 years - 1 day
- Expiry = issue date + 3 years - 1 day

Backend states are `Locked - Not Due Yet`, `Active - Due Now`, `Overdue`, and `Completed`. Do not bypass the backend lock merely to enable a card.

## Roles and Independence

Multiple roles per user are allowed; conflicts are enforced per client file and audit event.

- Super Admin: full tenant visibility and Cycle Builder.
- Administrator/Certification Manager: permitted operations.
- Auditor/Lead Auditor: assigned open and closed audits only.
- Technical Reviewer: assigned independent reviews.
- Decision Maker: assigned independent decisions.
- General Manager: final approval.
- Finance: commercial and finance views.
- Compliance Audit Viewer: read-only complete-cycle/report access.

Super Admin can inspect and maintain complete stage files, but regulated actor identity and conflict rules remain traceable. Technical Review and Decision must not silently become one conflicted action.

Personnel is the visible people master. Do not restore a duplicate normal-sidebar user/personnel master.

## Application and Review Rules

Question selection is strict by selected standards. HACCP-only or ISO 22000-only files must not receive ISO 9001, ISO 14001 or ISO 45001 questions.

Implemented rules include:

- scope-driven products, services, processes and outsourced processes;
- relevant Saudi regulatory and food-safety context;
- audit language and system-readiness answers;
- Number of HACCP Studies / Plans;
- no Preferred Audit Dates or Preferred Auditor;
- no application QR, obsolete footer or time component;
- Application Review receives the same saved scope, standards, answers and HACCP-plan count;
- Technical Manager and Quality Manager identities appear;
- GM comments do not belong in Application Review.

The saved application value is the source of truth. Two HACCP plans in Application must remain two in Review.

## Duration and Planning

Audit duration considers effective personnel, standards, risk, sites, shifts, reductions and scheme-specific inputs. One auditor man-day equals eight auditor-hours.

Two auditors may share duration; two auditors working one day provide two man-days. Plans must divide timings, processes, clauses and auditor responsibilities without double-counting.

Stage 1 and Stage 2 are separate. Stage 1 is not universally fixed at one day. Do not change HACCP plan-count or duration rules without reviewing QSI's controlled source documents and agreeing the result with Ahmad.

## Reports, Evidence and NCR/CAPA

Each stage has a separate report and submission date. The complete stage file shows requirement/clause, evidence, findings, NCR/CAPA, reviewer and decision records.

Evidence must be clause-specific and scope-specific, not the same generic documents under every clause. References follow a client/clause/sequence pattern such as `SUN-4.3-001`.

The content pipeline uses:

- `SmartAuditContentEngine`
- `AuditReportNarrativeService`
- `ClauseContentPoolService`
- Clause Library requirements
- approved Clause Pool content
- client scope/process/standard/stage context

Internal labels such as `Clause Pool basis` and template references must not appear as duplicate client-facing report content.

NCR/CAPA must link to the actual requirement and must not duplicate an existing finding. CAPA contains requirement, NC statement, root cause, correction, corrective action, responsible person, target date, evidence, closure status, auditor verification and closure date.

## Controlled Documents

QSI document family:

- QSI navy
- pale blue-gray fills
- restrained gold accent
- official QSI logo
- clean controlled-document header
- readable tables and margins
- no redundant legacy footer

Proposal and Contract use a cover page; the controlled header begins on page 2. Audit Program, Appointment, Plan, Report, NCR/CAPA, Technical Review and Decision use the clean official header.

Document number, revision, issue and date are editable in Templates and visible in workflow document sections. Future work should preserve immutable historical snapshots.

| Document | Current default |
|---|---|
| Application | F25 / Rev 1 / Issue 2 / 2024-11-01 |
| Application Review | F28 / Rev 4 / Issue 2 / 2025-02-01 |
| Proposal | F26 / Rev 2 / Issue 2 / 2022-05-15 |
| Contract | F27 / Rev 2 / Issue 2 / 2022-05-15 |
| Audit Program | F42 / Rev 2 / Issue 2 / 2022-05-15 |
| Appointment | F30_app / Rev 2 / Issue 2 / 2022-05-15 |
| Audit Plan | F31 / Rev 2 / Issue 2 / 2022-05-15 |
| Audit Report | F32 / Rev 2 / Issue 2 / 2022-05-15 |
| NCR/CAPA | F33 / Rev 2 / Issue 2 / 2022-05-15 |
| Technical Review | F34 / Rev 2 / Issue 2 / 2022-05-15 |
| Decision | F35 / Rev 2 / Issue 2 / 2022-05-15 |
| Feedback | F36 / Rev 2 / Issue 2 / 2022-05-15 |

Confirm uncertain official form numbers with Ahmad before production use.

## Certificates

Each approved standard gets a separate certificate.

- Branded PDF for electronic issue.
- DOCX for printing on QSI stock.
- No digital seal; QSI applies a physical embossed seal.
- Approved and Printed-by signatures are integrated.
- Optional client logo is supported.
- Long names/scopes scale without overlap.
- Cycle dates, number, QR/validity code and verification details stay aligned.

## Controlled Demo Files

1. `Demo 2024 Riyadh Central Catering LLC` - HACCP only, two HACCP studies.
2. `Demo 2024 Fresh Valley Dairy Factory` - ISO 22000:2018 only.
3. `Demo 2024 Gulf Ready Meals Industries` - HACCP + ISO 22000:2018, four HACCP studies.

These validate real system behavior. Single-standard isolation is mandatory. Do not mix in unselected standards or another client's generic content.

## Key Code Locations

- Routes: `app/Config/Routes.php`
- Authentication: `app/Services/AuthService.php`
- Workflow: `app/Controllers/Workflow/`, `app/Views/workflow/`
- Status: `app/Services/CertificationWorkflowService.php`
- Role/conflict policy: `app/Services/WorkflowRoleService.php`
- Duration: `app/Services/AuditDurationService.php`
- Report content: `app/Services/SmartAuditContentEngine.php`
- Narrative/evidence: `app/Services/AuditReportNarrativeService.php`
- Clause matching: `app/Services/ClauseContentPoolService.php`
- Commercial terms: `app/Services/CommercialTermsService.php`
- PDF/DOCX: `app/Services/DocumentGeneratorService.php`
- Cycle Builder: `app/Services/CycleAutomationService.php`
- Dashboard: `app/Services/DashboardService.php`
- Notifications: `app/Services/NotificationService.php`
- Migrations: `app/Database/Migrations/`
- Seeders: `app/Database/Seeds/`
- Tests: `tests/Unit/`
- Startup: `scripts/start-local.ps1`, `scripts/run-local-database.ps1`, `scripts/run-local-app.ps1`
- Deployment: `compose.yaml`, `docker/`, `docs/DEPLOYMENT.md`

## Verified State

- Super Admin seeder completed successfully.
- Browser login reached `/dashboard` with full Super Admin navigation.
- Canonical Application/Application Review/report connection repaired.
- Full stage file and PDFs use controlled requirement responses; legacy sections are fallback only.
- PHPUnit: **45 tests, 340 assertions passing**.
- PowerShell launchers pass syntax parsing.
- `.env`, `.mysql-data`, `vendor`, uploads and runtime data remain outside Git.

## Immediate Next Work

1. Push local startup and handover commits after GitHub authentication is restored.
2. Browser-test the ISO 22000-only and combined food-safety files from saved inputs through PDFs.
3. Repeat canonical E2E validation for Surveillance 1, Surveillance 2 and recertification.
4. Add database-backed tests for role conflicts, due-date locks, completion gates, Technical Review, Decision and certificate issue.
5. Add immutable approval/document snapshots and hashes.
6. Complete historic-client batch import with validation, error download, resume/retry and reconciliation.
7. Configure/test SMTP, appointment/reminder emails and scheduler.
8. Complete website-lead qualification/conversion screens.
9. Test backup/restore, deployment/TLS/secrets, monitoring and management/accreditation registers.
10. Add complaints, appeals, suspension, withdrawal, reinstatement and replacement-certificate history.

## Safety and End-of-Session Rules

- Never commit `.env`, secrets, private keys or tokens.
- Never commit `.mysql-data`, backups, uploads, logs, sessions, client data, personal data or real audit records.
- Never reset an existing database to solve login or code issues.
- Do not revert unrelated user changes.
- Use migrations and narrow repeat-safe seeders.
- Do not change standards, certification rules or duration from memory alone.
- Do not invent client facts, evidence, approvals, findings, fees or competence.

At every session end:

1. Update `PROGRESS.md`.
2. Update this handover when major behavior changes.
3. Run tests and relevant browser/PDF checks.
4. Review staged files for secrets/runtime data.
5. Commit with a clear message.
6. Push to `origin main`.
7. Verify the remote commit before reporting GitHub success.
