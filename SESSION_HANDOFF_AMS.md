# QSI AMS - Complete Session Handoff

> Last updated: 2026-07-11
> Workspace: `C:\Users\PCD\Documents\AMS`
> Repository: `https://github.com/richahmad4-stack/qsi-ams-portal.git`
> Branch: `main`

## Instructions for the Next Codex Session

Read this file, `AGENTS.md`, and `PROGRESS.md` before changing code. Treat the repository and database migrations as the source of truth. Do not rebuild completed modules from scratch and do not remove working certification-body controls merely to simplify the UI.

At the start of the session:

```powershell
cd C:\Users\PCD\Documents\AMS
git pull origin main
composer install
Copy-Item env.example .env   # only when .env does not already exist
php spark migrate
vendor\bin\phpunit
```

Configure the local `.env` before migrations. Never commit `.env`, passwords, SMTP secrets, client uploads, local database files, or runtime files.

At the end of every session:

1. Update `PROGRESS.md` and this handoff when a decision or major module changes.
2. Run relevant syntax checks and `vendor\bin\phpunit`.
3. Check that no secrets/runtime files are staged.
4. Commit with a clear session-specific message.
5. Push to `origin main` and verify the remote commit.

## Project Purpose

QSI AMS is a multi-tenant Certification Body Audit Management System for real certification operations. It is intended to support ISO/IEC 17021-style management-system certification workflows and applicable scheme/IAF mandatory-document controls. It is not a generic task tracker.

The system manages:

- leads, clients, sites, processes, standards and certification scopes;
- certification applications and independent application review;
- proposals, contracts, invoices and payments;
- three-year audit programmes;
- stage-specific auditor appointments, plans, reports and files;
- NCR/CAPA, technical review, independent decision and GM approval;
- one certificate per approved standard, in PDF and Word DOCX;
- Surveillance 1, Surveillance 2 and recertification;
- client feedback, reminders, notifications and controlled records;
- IAF, NACE, food, medical, clause and approved content reference data;
- role-specific dashboards and read-only compliance audit access.

## Technology and Local Runtime

- PHP `8.2.12` locally; project target is PHP 8.2+.
- CodeIgniter `4.7.3`.
- MySQL/MariaDB.
- DomPDF for PDFs.
- PHPWord for certificate DOCX output.
- Bootstrap/DataTables/Chart.js in the existing UI.
- PHPUnit tests under `tests/Unit`.
- Local URL: `http://127.0.0.1:8080/login`.
- Local tenant code: `QSI`.
- Administrator email/password come from the local `.env`; do not place passwords in this file.

Preferred local startup:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\start-local.ps1
```

This starts the project-local MariaDB on `127.0.0.1:3307` and the AMS on port `8080`. An additional helper, `START-AMS-LOCAL.ps1`, is also present for XAMPP-style startup. See `docs/local-run.md`.

## Repository Notes

This original computer uses `.repo-git` as its Git directory, so commands here were often run as:

```powershell
git --git-dir=.repo-git --work-tree=. status
```

A normal clone on the other computer will have a standard `.git` folder and should use ordinary `git status`, `git pull`, `git commit`, and `git push` commands.

The last local commit before this handoff was:

```text
b09f142 Show document controls in workflow
```

When this handoff is committed, use the new HEAD shown by `git log -1 --oneline` as the authoritative continuation point.

## Certification Workflow

The controlled initial certification sequence is:

1. Client Application with one or more selected standards.
2. Technical Manager application review for scope, competence, resources and capability.
3. Quality Manager independent application approval.
4. Proposal preparation and client acceptance/rejection.
5. Contract containing scope, standards, duration, fees, terms and cycle requirements.
6. Three-year audit programme.
7. Stage-specific competent auditor appointment with impartiality/conflict checks.
8. Stage 1 plan, execution, checklist and report.
9. Stage 2 plan, execution, checklist and report.
10. NCR/CAPA closure where applicable.
11. Technical Review of the complete audit file.
12. Independent certification decision.
13. General Manager final approval.
14. Separate certificate for each approved standard.
15. Client feedback.

Surveillance 1 and Surveillance 2 are separate workflows. Each includes its own appointment, plan, report, NCR/CAPA, Technical Review, maintain-certification decision, status and PDFs. Recertification is a separate event/cycle rather than a continuation of Stage 2.

## Certification Cycle Date Rules

The system uses:

- Surveillance 1 due date = certificate issue date + 1 year - 1 day.
- Surveillance 2 due date = certificate issue date + 2 years - 1 day.
- Certificate expiry date = certificate issue date + 3 years - 1 day.

Surveillance sections are locked before their due date. Their states are:

- `Locked - Not Due Yet`
- `Active - Due Now`
- `Overdue`
- `Completed`

Do not bypass the backend due-date gate merely by making a card clickable.

## Role and Independence Rules

Multiple roles per user are allowed, but conflicts are enforced per client file and audit event.

Important roles include:

- Super User: full tenant owner access.
- Administrator / Certification Manager: operational administration.
- Technical Manager and Quality Manager: application/programme controls.
- Auditor / Lead Auditor: assigned audits only.
- Technical Reviewer: assigned independent reviews.
- Certification Decision Maker: assigned independent decisions.
- General Manager: final approval.
- Finance: finance dashboard and commercial records.
- Compliance Audit Viewer: read-only access to cycles, files, reports and PDFs.

Auditors should see only their assigned open/closed audits and relevant sidebar entries. Technical Review and Decision cannot be performed by a conflicted person for the same file. Audit team competence must cover all selected standards/scopes, but multiple auditors may collectively cover the required competence. Auditor appointment creates an in-app notification and can email when SMTP is configured.

Personnel is the visible people master for real CB personnel and client representatives. User/role administration remains access-control plumbing and is not shown as a duplicate people master in the normal sidebar.

## Audit Duration and Planning Rules

Audit duration is scheme-aware and considers effective personnel, standards, risk, sites, shifts, reductions and scheme-specific inputs. Stage 1 and Stage 2 are separate. One auditor man-day equals eight auditor-hours.

Two auditors may share an audit: for example, two auditors working one day provide two man-days. Plans must allocate timing, activities, clauses/processes and responsible auditor without double-counting the same hours.

For HACCP, `Number of HACCP Studies / Plans` is captured in the application and synchronized into Application Review. The saved application value is the source of truth. Do not change the HACCP duration formula casually: Ahmad explicitly requested that the exact HACCP plan-count calculation be reviewed and agreed before further modification.

Licensed ISO/IAF/MD text must not be copied into the application or clause library without authorization. Use controlled internal checklist wording and verify duration tables against QSI's licensed/current scheme documents before production approval.

## Application Rules

Question selection is strict by selected standard. A HACCP-only client must not receive ISO 9001, ISO 14001 or ISO 45001 questions.

The Certification Application PDF excludes the separate standard-specific questionnaire sections and supporting-document upload request. It has no application QR block, no old document-control footer and no time component in date-only fields. It uses simple page numbers.

HACCP application defaults include legal/regulatory context, food-safety risks, technical issues, safety conditions, audit language, implementation/internal-audit/management-review readiness, and scope-driven products/services/processes. Preferred Audit Dates and Preferred Auditor were removed. `Number of HACCP Studies / Plans` remains visible for HACCP files.

Application Review includes the detailed F28 checklist, Technical Manager/Quality Manager identities, man-day calculation, calculation basis, risk and recommendation. General Manager comments are not part of the application-review report because GM approval belongs later in the certification decision flow.

## Audit Reports, Evidence and NCR/CAPA

Stage 1, Stage 2, Surveillance 1, Surveillance 2 and recertification reports are separate records with their own checklists and report submission dates.

Conformity evidence must be clause-specific and scope-specific. Avoid repeating the same generic evidence under every clause. Controlled evidence references follow a client/clause/sequence pattern such as `SUN-4.3-001`.

The shared `SmartAuditContentEngine` combines:

- the Clause Library requirement/checklist context;
- approved Clause Pool content;
- stage, client, scope, process and standard context;
- clause-specific document/evidence references.

The supplied NC/CAPA workbook was imported into Clause Pool as 5,076 reusable controlled templates for HACCP, ISO 22000, ISO 9001, ISO 14001 and ISO 45001. It includes conformity answers, evidence, NCs, root cause, correction, corrective action, preventive action and verification content.

System-prepared cycle files can prepare and confirm conformity sections under the assigned auditor and can prepare four varied NCR/CAPA samples. Do not create duplicate findings if report/NCR rows already exist. Avoid fabricating claims: content is a controlled draft/sample tied to the file inputs and remains editable/traceable by the responsible role.

PDF report notes intentionally hide internal implementation labels such as `Clause Pool basis` and template references. The user should see one clean `Conformity / Audit Evidence Note`, not repeated headings or internal metadata.

## Controlled Documents and PDF Design

All controlled certification documents use the same QSI visual family:

- QSI navy primary color;
- pale blue-gray table/header fill;
- restrained gold accent line;
- official QSI logo;
- clean official-form header with title and document-control cells;
- no redundant prepared-date footer/document-control footer.

Proposal and Contract are exceptions on page 1: they have a client-facing cover. The official controlled-document header starts on page 2. Their cover uses the QSI proposal direction, service badge images, prepared-for table, certification scope and centered contact information.

Document controls are now editable in Templates and visible from Certification and Surveillance workflow PDF sections:

| Document | Default control |
|---|---|
| Certification Application | F25 / Rev 1 / Issue 2 / 2024-11-01 |
| Application Review | F28 / Rev 4 / Issue 2 / 2025-02-01 |
| Proposal | F26 / Rev 2 / Issue 2 / 2022-05-15 |
| Contract | F27 / Rev 2 / Issue 2 / 2022-05-15 |
| Audit Program | F42 / Rev 2 / Issue 2 / 2022-05-15 |
| Auditor Appointment | F30_app / Rev 2 / Issue 2 / 2022-05-15 |
| Audit Plan | F31 / Rev 2 / Issue 2 / 2022-05-15 |
| Audit Reports | F32 / Rev 2 / Issue 2 / 2022-05-15 |
| NCR/CAPA | F33 / Rev 2 / Issue 2 / 2022-05-15 |
| Technical Review | F34 / Rev 2 / Issue 2 / 2022-05-15 |
| Decision | F35 / Rev 2 / Issue 2 / 2022-05-15 |
| Feedback | F36 / Rev 2 / Issue 2 / 2022-05-15 |

These defaults are initial controlled values and may be changed by authorized users through Templates. Confirm any uncertain QSI form number with Ahmad before treating it as the official production issue.

## Certificates

Each approved standard receives a separate certificate. Certificate issue produces:

- branded PDF for electronic issue;
- Word DOCX for printing on hard-copy certificate stock.

Certificate outputs use the supplied QSI visual template, QSI logo, dynamic client name, optional uploaded client logo, standard, scope, cycle date matrix, QR/validity code and approval/print signatures.

Do not add a digital seal: QSI uses a physical embossed seal on hard copies. Supplied signature assets are controlled and already integrated. Long organization names and scopes scale down to fit.

## Major Implemented Modules

- Session login, password change, RBAC, multi-role assignments and audit logging.
- Role-specific dashboards and sidebar visibility.
- Dashboard detail cards and Finance dashboard.
- Client, site, process, standard and personnel masters.
- IAF/NACE/Food/Medical references.
- Clause Library and Clause Pool import/export.
- Dynamic application and detailed application review.
- Proposal, contract and finance records.
- Three-year audit programme.
- Stage-specific appointments and detailed audit plans.
- Full audit execution, report sections, autosave and confirmation.
- NCR/CAPA closure and evidence.
- Detailed Technical Review and Decision checklists.
- Certificate PDF/DOCX and public verification route.
- Surveillance 1/2 and recertification separation/locking.
- Client feedback.
- Super User Cycle Builder with preview, single creation and CSV/XLSX batch upload.
- In-app notifications and SMTP-ready appointment email.
- Reminder command: `php spark ams:process-reminders`.
- Operations Readiness dashboard.
- User administration screens.
- Compliance Audit Viewer.
- Website leads database foundation.
- Controlled PDF template/document register in workflow.

## Key Code Locations

- Routes: `app/Config/Routes.php`
- Workflow UI/controller: `app/Controllers/Workflow/*`, `app/Views/workflow/*`
- Workflow status logic: `app/Services/CertificationWorkflowService.php`
- Workflow role/conflict policy: `app/Services/WorkflowRoleService.php`
- Audit duration: `app/Services/AuditDurationService.php`
- Smart report/CAPA content: `app/Services/SmartAuditContentEngine.php`
- Narrative/evidence generation: `app/Services/AuditReportNarrativeService.php`
- Clause Pool matching: `app/Services/ClauseContentPoolService.php`
- Proposal/contract wording: `app/Services/CommercialTermsService.php`
- PDF/DOCX output: `app/Services/DocumentGeneratorService.php`
- Cycle Builder: `app/Services/CycleAutomationService.php`
- Dashboard roles/queues: `app/Services/DashboardService.php`
- Notifications: `app/Services/NotificationService.php`
- Migrations: `app/Database/Migrations`
- Seeders: `app/Database/Seeds`
- Reference-only database exports: `database/schema.sql`, `database/seed-data.sql`
- Unit tests: `tests/Unit`

## Current State and Immediate Next Work

The latest completed change made form number, revision, issue number and document date editable through Templates and visible inside Certification/Surveillance workflow PDF sections. This is important for compliance audits and must remain system-wide, not client-specific.

The immediate continuation sequence should be:

1. Pull the latest `main` and run migrations/tests.
2. Start the app and verify the workflow document-control register in the browser.
3. Verify that changing a template control updates subsequent workflow/PDF output without modifying historical records unexpectedly.
4. Browser-test one HACCP file, one ISO 9001 file and one multi-standard file.
5. Continue production-readiness work: Website Leads screen/conversion, controlled historic-data import, SMTP/reminder scheduler, deployment/TLS/secrets, backup/restore test and management reporting.
6. Expand database-backed feature tests for due-date locks, role conflicts, audit completion, Technical Review, Decision and document routes.

## Known Gaps and Cautions

- The application is not yet production-hosted. Domain, TLS, production secrets, scheduler and backup/restore proof remain.
- SMTP is configurable but must be supplied and tested in the deployment environment.
- Reminder generation exists as a command; production scheduling still needs configuration.
- Website Leads has a database foundation but still needs a qualification/assignment/conversion UI and external feed connection.
- Historic client/certificate migration needs a staging and validation flow. Do not import personal/client audit data into Git.
- Client portal screens are not complete.
- Management/SAAC-ready reporting and exports need expansion.
- Current tests are mainly unit/wiring tests; broader database-backed feature tests are still needed.
- Audit duration logic is controlled but must be formally validated by QSI against licensed/current MD and scheme tables before production reliance.
- Do not expose internal words such as `fake`, `generated`, `automation`, `answer text`, or template-pool implementation details in client-facing documents. Use professional Certification Body language such as `Response`, `Information Required`, `Prepared`, `Controlled Record`, or the actual workflow action.
- Changes requested for one demo client normally represent a system rule for all applicable clients/standards unless Ahmad explicitly says otherwise.

## Verification Commands

```powershell
php spark migrate
vendor\bin\phpunit
php spark routes
php -l app\Services\DocumentGeneratorService.php
php -l app\Services\CertificationWorkflowService.php
php -l app\Controllers\Workflow\WorkflowActionController.php
git status --short
git ls-files .env vendor writable/cache writable/logs writable/session writable/uploads
```

The final `git ls-files` safety check must return no protected runtime/secret paths.

## Product Owner Preferences

Ahmad is the business owner and expects Codex to act as an experienced Certification Body system owner: inspect first, make coherent system-wide changes, preserve segregation and independence, and verify the result. He prefers practical implementation over repeated permission questions.

When a requirement is genuinely ambiguous or affects compliance logic (especially audit duration), discuss the rule before changing it. For ordinary implementation details, follow the established code/design patterns and complete the work end to end.
