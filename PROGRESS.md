# AMS Project Progress

QSI AMS is a CodeIgniter 4 / PHP / MySQL Audit Management System for a certification body. It manages certification applications, standards, client files, audit programs, auditor appointments, audit planning, audit execution, NCR/CAPA, technical reviews, decisions, certificate issuance, surveillance audits, feedback, reference data, and controlled PDF outputs.

## Complete / Working

- Tenant login, roles, permissions, password change, and audit logging.
- Client master, personnel master, standards selection, IAF/NACE/food/medical reference data.
- Dynamic certification application and application review with audit man-day calculation logic.
- Proposal, contract, audit program, auditor appointment, audit plan, audit report, NCR/CAPA, technical review, decision, certificate issue, and feedback screens.
- Technical Review now includes detailed administration/technical review rows from the supplied `F 34` format, with audit information, accreditation scope checks, report completeness, NCR/CAPA review, authorization decision, and PDF output.
- Decision Making now includes pre-issue accreditation, scope, audit time, mark/statement checks, declaration/sign-off fields, and PDF output based on the supplied decision checklist.
- Separate workflow areas for Certification Audit, Surveillance Audit #01, Surveillance Audit #02, and Recertification/Expiry.
- Separate event PDFs for Stage 1, Stage 2, Surveillance 1, Surveillance 2, and recertification plans/reports.
- Dashboard cards with linked detail sections and PDF actions.
- Safe database reproducibility files: `database/schema.sql` and reference-only `database/seed-data.sql`.

## Current Focus

Refining the final certification file controls and fixing workflow loading issues found during multi-standard client testing.

## Next

- Continue refining controlled PDF templates against the user-provided document formats, especially certificate issuance and client feedback.
- Continue checking multi-standard client files such as HACCP + ISO 22000 + ISO 9001 for stage-specific workflow and PDF consistency.
- Continue polish across certification, surveillance, and recertification workflow screens where the user finds unclear grouping.
- Add stronger user-login/personnel-client separation and role-specific access flows.
