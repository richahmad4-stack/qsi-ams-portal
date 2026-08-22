# Google Apps Script Full AMS Parity Plan

Updated: 2026-08-22

## Purpose

Rebuild the Google Apps Script AMS so it follows the full QSI AMS operating model as closely as possible on free Google Workspace tools: Apps Script, Google Sheets, Drive, Docs, and PDF export.

The target is not a separate simplified app. Each client must have one controlled client workflow file, with tabs for every certification-cycle area. Cycle Builder must prepare that same workflow file; normal users then continue through role-controlled tabs.

## Non-Negotiable Model

- One client equals one workflow file.
- The client workflow contains tabs, not disconnected pages.
- Stage 1, Stage 2, Surveillance 1, Surveillance 2, and Recertification are separate audit stage records inside the same client workflow.
- Each audit stage has its own appointment, plan, timetable, checklist, evidence, findings, NCR/CAPA, audit report, technical review status, and decision status.
- Cycle Builder is a required Super Admin-only controlled digitalization module.
- The UI must be designed as a professional certification-body workspace, not a basic form dump.
- Generated documents must come from saved workflow records, not a PDF-only data path.

## Client Workflow Tabs

Every opened client file must show these tabs:

1. Summary
2. Client Master
3. Sites and Processes
4. Application
5. Application Review
6. Proposal
7. Contract
8. Audit Program
9. Stage 1
10. Stage 2
11. Technical Review
12. Decision and GM Approval
13. Certificates
14. Surveillance 1
15. Surveillance 2
16. Recertification
17. NCR/CAPA
18. Documents
19. Finance
20. Feedback
21. Audit Log

## Audit Stage Tab Structure

Each audit-stage tab must include:

- Stage header: type, status, due state, dates, audit number, standard coverage.
- Appointment: lead auditor, auditors, technical expert, appointment status, conflict check, competence check.
- Audit plan: objective, scope, criteria, method, sites, processes, duration basis.
- Timetable: date, time, process, clause/requirement, auditor, method.
- Opening meeting record.
- Requirement checklist: QSI requirement, mapped standards/clauses, response, evidence, finding.
- Objective evidence register.
- Findings and NCR linkage.
- CAPA linkage and closure/verification.
- Closing meeting record.
- Audit report: summary, conclusion, recommendation, lead auditor confirmation.
- Technical review status for that stage.
- Decision or maintain-certification status for that stage.
- Document buttons for appointment, plan, report, NCR/CAPA, review, and decision.

## Cycle Builder

Cycle Builder must be a main module for Super Admin only. It must support manual entry and batch import.

Cycle Builder must provide:

- Controlled input form.
- CSV/XLSX import template.
- Preview before generation.
- Server-side validation before saving.
- Duplicate client/cycle warnings.
- No overwrite unless explicitly selected.
- Error download.
- Resume/retry for failed batch rows.
- Generated record summary.
- Audit log for every created record.

Cycle Builder must generate:

- Client master, contacts, sites, processes, standards.
- Application and application answers.
- Application review.
- Proposal and contract.
- Audit program.
- Stage 1 and Stage 2 events.
- Surveillance 1, Surveillance 2, and Recertification events.
- Appointment, plan, timetable, requirement responses, audit report, and NCR/CAPA shell for each stage.
- Technical review and decision shells where applicable.
- Certificate shell after approved decision path.
- Finance records.
- Generated document register entries where documents are created.

## Data Structure Phase

Expand the no-paid Sheets backend to include parity tables for:

- tenants, users, roles, permissions
- clients, client_contacts, client_sites, client_processes, client_standards
- personnel, personnel_competencies, conflict_checks
- standards, clause_library, integrated_audit_requirements, integrated_requirement_clauses
- certification_applications, application_answers, application_reviews
- proposals, contracts, audit_programs
- audit_events, auditor_appointments, audit_plans, audit_plan_items
- audit_reports, audit_requirement_responses, audit_response_clause_snapshots
- ncrs, capas, capa_evidence
- technical_reviews, certification_decisions, gm_approvals
- certificates, certificate_public_events
- client_feedback, invoices, payments
- document_templates, generated_documents, notifications, audit_logs
- cycle_builder_runs, cycle_builder_rows

## UI Design Phase

The Apps Script UI must use a dense, work-focused layout:

- Fixed left navigation.
- Top bar with tenant, user, role, and global search.
- Client list opens one client workflow workspace.
- Workflow tabs are always visible inside the client file.
- Stage tabs show structured panels and tables.
- Locked actions show the reason.
- Status badges are visible on cards, rows, and tabs.
- Long client names, scopes, and findings wrap without overlap.
- Tables remain usable on narrow screens.
- No blank screens after authorization or loading.

Primary modules:

- Dashboard
- Cycle Builder
- Clients
- Client Workflow
- Masters
- Finance
- Client Portal
- Admin
- Audit Trail

## Workflow Gate Phase

Implement gates equivalent to the full AMS:

- Application review requires submitted application.
- Proposal requires accepted application review.
- Contract requires proposal accepted/sent as applicable.
- Audit program requires signed contract.
- Stage 2 cannot proceed until Stage 1 is complete or formally allowed.
- Technical review cannot proceed until the relevant audit report is complete.
- Decision cannot proceed until technical review is complete and required CAPA is closed or dispositioned.
- Certificate cannot issue until decision and GM approval are complete.
- Surveillance 1 and Surveillance 2 are separate due-controlled events.
- Recertification is separate from surveillance.
- Auditor, technical reviewer, decision maker, and GM roles remain traceable and conflict controlled.

## Document Phase

Generate Google Docs and PDFs from saved workflow records for:

- Application
- Application Review
- Proposal
- Contract
- Audit Program
- Appointment
- Audit Plan
- Stage 1 Audit Report
- Stage 2 Audit Report
- Surveillance 1 Audit Report
- Surveillance 2 Audit Report
- Recertification Audit Report
- NCR/CAPA
- Technical Review
- Decision
- Certificate
- Feedback

Each generated document record must store document key, title, Drive file ID, PDF file ID, version, hash, generated by, generated at, source table, and source ID.

## Demo And Verification Phase

Seed three complete demonstration clients:

1. HACCP-only catering client with two HACCP plans.
2. ISO 22000:2018-only dairy client.
3. HACCP plus ISO 22000:2018 ready-meals client.

Each demo must show a complete client workflow with Stage 1, Stage 2, Surveillance 1, Surveillance 2, Recertification, audit reports, NCR/CAPA, technical review, decision, certificates, finance, generated documents, and audit log.

Before reporting completion:

- Deploy a new Apps Script version.
- Open the deployed web app.
- Seed demo data.
- Open each demo client workflow.
- Confirm tabs render and are not empty.
- Confirm Stage 1 and Stage 2 reports show real report content.
- Confirm surveillance and recertification tabs exist and contain their own stage files.
- Generate at least representative PDFs.
- Test certificate verification.
- Confirm role/lock messages appear.
- Confirm Sheets records were created.

## Build Order

1. Update Sheets schema and baseline seeding.
2. Build one-client workflow shell with all tabs.
3. Build Cycle Builder preview/generate/run records.
4. Build stage tab data model and UI sections.
5. Build Stage 1 and Stage 2 audit reports.
6. Build Surveillance 1, Surveillance 2, and Recertification files.
7. Add workflow gates and role/conflict controls.
8. Add document generation and generated document register.
9. Add client portal views.
10. Seed complete demo clients.
11. Deploy and verify in browser.

