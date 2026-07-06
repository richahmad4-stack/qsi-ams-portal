# AMS Project Progress

QSI AMS is a CodeIgniter 4 / PHP / MySQL Audit Management System for a certification body. It manages certification applications, standards, client files, audit programs, auditor appointments, audit planning, audit execution, NCR/CAPA, technical reviews, decisions, certificate issuance, surveillance audits, feedback, reference data, and controlled PDF outputs.

## Complete / Working

- Tenant login, roles, permissions, password change, and audit logging.
- Client master, personnel master, standards selection, IAF/NACE/food/medical reference data.
- Dynamic certification application and application review with audit man-day calculation logic.
- Proposal, contract, audit program, auditor appointment, audit plan, audit report, NCR/CAPA, technical review, decision, certificate issue, and feedback screens.
- Separate workflow areas for Certification Audit, Surveillance Audit #01, Surveillance Audit #02, and Recertification/Expiry.
- Separate event PDFs for Stage 1, Stage 2, Surveillance 1, Surveillance 2, and recertification plans/reports.
- Dashboard cards with linked detail sections and PDF actions.
- Safe database reproducibility files: `database/schema.sql` and reference-only `database/seed-data.sql`.

## Current Focus

Refining the client workflow screen so Certification Audit, Surveillance Audit #01, Surveillance Audit #02, PDFs, file tabs, and execution records are grouped audit-by-audit without duplicated workflow sections.

## Next

- Continue refining controlled PDF templates against the user-provided document formats.
- Continue polish across certification, surveillance, and recertification workflow screens where the user finds unclear grouping.
- Add stronger user-login/personnel-client separation and role-specific access flows.
