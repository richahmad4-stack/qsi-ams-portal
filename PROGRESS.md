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
- Multiple roles per user are supported, with `super_admin` as the only full-access tenant owner role. Operational roles are normalized, and workflow actions now enforce per-stage roles plus assignment/conflict rules for auditors, reviewers, decision makers, GM approval, certificate issue, and feedback.
- Role-specific dashboards are now active: global management roles keep the management dashboard, while auditors/lead auditors see their assigned audit queue, technical reviewers see assigned reviews, decision makers see assigned decisions, and finance users see finance work.
- Sidebar visibility is now role-aware so task users only see relevant navigation such as My Audits, My Reviews, My Decisions, Finance, or Clause Library as applicable.
- Auditor appointment saves now create in-app notifications and can send appointment emails when SMTP/email notifications are configured.
- Super User Cycle Generator automation is now wired: Super User/Admin can preview and generate a complete three-year certification lifecycle from basic client, standard, scope, issue-date, risk, and status inputs. It creates application, review, proposal, contract, invoice/payment records, audit programme, stage/surveillance/recertification audit events, appointments, plans, reports, NCR/CAPA samples, technical reviews, decisions, certificates, feedback, reminders, notifications, audit logs, and an automation run history record.
- Cycle Generator has been hardened after PDF review: default generation is now a controlled draft/import preparation mode. It no longer auto-confirms audit report clauses, closes NCR/CAPA, approves Technical Review/Decision/GM approval, issues certificates, or submits feedback unless historical completed mode is selected and real evidence, technical review notes, and decision basis are supplied.
- Safe database reproducibility files: `database/schema.sql` and reference-only `database/seed-data.sql`.

## Current Focus

Project-owner compliance hardening and workflow validation: core gates are now in place for audit duration basis, multi-standard competence coverage, full-file Technical Review/Decision readiness, surveillance locks, auditor confirmation of generated report clauses, role-based workflow authority, first-pass role-specific dashboards, and controlled Super User lifecycle automation.

## Next

- Browser-test the new Cycle Generator with one demo file for HACCP, ISO 9001, and a multi-standard case, then review the generated workflow screens and PDFs.
- Convert the user-supplied official forms into richer controlled HTML/PDF sections for application, review, proposal, contract, audit programme, appointment, plan, report, NCR/CAPA, technical review, decision, certificate and feedback.
- Expand tests from wiring/smoke tests into database-backed feature tests for surveillance locks, appointment gates, audit completion, Technical Review, Decision, PDF routes, and audit duration edge cases.
- Build a user/role management screen so Super Users can assign multiple roles from the portal instead of relying on seed/migration data.
- Review official licensed MD/scheme tables with the Certification Body and tune the controlled audit-duration rule set where needed.
- Add richer controlled checklist/question banks per standard without copying licensed standard text.
- Continue refining controlled PDF templates against the user-provided document formats, especially certificate issuance and client feedback.
- Continue checking multi-standard client files such as HACCP + ISO 22000 + ISO 9001 for stage-specific workflow, competence matching, and PDF consistency.
- Continue generating/reviewing sample PDFs for application review, proposal, contract, audit programme, audit plans, audit reports, technical review, decision, certificate, and feedback.
- Continue polish across certification, surveillance, and recertification workflow screens where the user finds unclear grouping.
- Configure real SMTP settings for live email delivery, then test appointment emails outside the local demo environment.
