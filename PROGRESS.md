# AMS Project Progress

QSI AMS is a CodeIgniter 4 / PHP / MySQL Audit Management System for a certification body. It manages certification applications, standards, client files, audit programs, auditor appointments, audit planning, audit execution, NCR/CAPA, technical reviews, decisions, certificate issuance, surveillance audits, feedback, reference data, and controlled PDF outputs.

## Complete / Working

- Tenant login, roles, permissions, password change, and audit logging.
- Client master, personnel master, standards selection, IAF/NACE/food/medical reference data.
- Dynamic certification application and application review with audit man-day calculation logic.
- Proposal, contract, audit program, auditor appointment, audit plan, audit report, NCR/CAPA, technical review, decision, certificate issue, and feedback screens.
- Technical Review now includes detailed administration/technical review rows from the supplied `F 34` format, with audit information, accreditation scope checks, report completeness, NCR/CAPA review, authorization decision, and PDF output.
- Decision Making now includes pre-issue accreditation, scope, audit time, mark/statement checks, declaration/sign-off fields, and PDF output based on the supplied decision checklist.
- Dashboard/sidebar layout has been restored to the earlier simple AMS structure.
- Finance is kept as its own separate sidebar dashboard/module for proposals, invoices, payments, revenue, outstanding payments, and finance reports.
- Generic NCR/CAPA PDF output now uses stacked record tables so long root-cause, correction, corrective-action, evidence, verification, and comments wrap inside page margins.
- Separate workflow areas for Certification Audit, Surveillance Audit #01, Surveillance Audit #02, and Recertification/Expiry.
- Separate event PDFs for Stage 1, Stage 2, Surveillance 1, Surveillance 2, and recertification plans/reports.
- Dashboard cards with linked detail sections and PDF actions.
- Compliance hardening pass added backend gates for auditor appointment competence/impartiality, surveillance due-date locking, audit completion readiness, Technical Review approval, and Certification Decision independence.
- Audit conformity notes are now clearly marked as system/AI drafts requiring auditor confirmation, and placeholder clause requirement wording is replaced with internal checklist-question language.
- Audit duration calculation now uses a controlled scheme-aware rule set with separate management-system, HACCP, food-safety, and medical-device bases, plus auditable calculation basis text.
- Multi-standard competence controls now require full selected-standard/scope coverage for the audit team, Technical Reviewer, and Decision Maker while still allowing multiple auditors to share coverage.
- Technical Review and Certification Decision approval now include file-level readiness checks for application review, accepted proposal, signed/approved contract, audit programme, required audit events, submitted reports, closed NCR/CAPA, and confirmed report clauses.
- Audit report conformity sections now track source type, auditor confirmation, confirmation date/user, and confirmation notes. Audit completion requires every conformity section to be confirmed.
- Finance dashboard route and sidebar visibility now use the `finance:view` permission instead of proposal access.
- PHPUnit smoke tests cover the audit-duration service and critical workflow gate wiring.
- Safe database reproducibility files: `database/schema.sql` and reference-only `database/seed-data.sql`.

## Current Focus

Project-owner compliance hardening and workflow validation: core gates are now in place for audit duration basis, multi-standard competence coverage, full-file Technical Review/Decision readiness, surveillance locks, and auditor confirmation of generated report clauses.

## Next

- Expand tests from wiring/smoke tests into database-backed feature tests for surveillance locks, appointment gates, audit completion, Technical Review, Decision, PDF routes, and audit duration edge cases.
- Review official licensed MD/scheme tables with the Certification Body and tune the controlled audit-duration rule set where needed.
- Add richer controlled checklist/question banks per standard without copying licensed standard text.
- Continue refining controlled PDF templates against the user-provided document formats, especially certificate issuance and client feedback.
- Continue checking multi-standard client files such as HACCP + ISO 22000 + ISO 9001 for stage-specific workflow, competence matching, and PDF consistency.
- Continue generating/reviewing sample PDFs for application review, proposal, contract, audit programme, audit plans, audit reports, technical review, decision, certificate, and feedback.
- Continue polish across certification, surveillance, and recertification workflow screens where the user finds unclear grouping.
- Add stronger user-login/personnel-client separation and role-specific access flows.
