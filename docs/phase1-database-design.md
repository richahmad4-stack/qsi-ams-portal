# QSI AMS Phase 1 Database Design

Phase 1 establishes the production database foundation for the QSI Audit Management System as a multi-tenant SaaS application.

## Scope Completed

- Multi-tenant certification body model through `tenants`.
- Session authentication storage with `ci_sessions`.
- Role based access control with roles, permissions, role permissions, and multi-role user assignments.
- Full client master data, sites, standards, processes, attachments, and legacy import tracking.
- Unlimited standards and reference classification tables for IAF, NACE, food chain, and medical device categories.
- Dynamic questionnaires with versioned question sets, conditional logic, and responses.
- Application review records for technical and quality manager review, risk rating, recommendation, and audit duration calculations.
- Proposal, proposal versioning, approval workflow, contract, and finance tables.
- Audit program, audit events, reminders, plans, plan timetable items, and auditor appointments.
- Personnel master, competency matrix, documents, witness audits, and availability calendar.
- Clause library for predefined conformity notes, findings, nonconformities, evidence examples, guidance, risk, and stage applicability.
- Dynamic report drafts and sections, storing generated/editable report data but not permanent Word/PDF files.
- NCR and CAPA modules with evidence, ageing-ready due dates, closure fields, and automatic NCR-to-CAPA linking.
- Internal audit and management review modules with findings, actions, and CAPA links.
- Technical review, certification decision, certificate generation, QR/public verification data, and public lookup logging.
- Document template builder storage with allowed placeholders.
- Notification rules and notification delivery records.
- Audit trail and full-text global search index.

## Initial Seed Data

The `InitialAmsSeeder` creates:

- The first tenant: QSI.
- Required roles: Administrator, Quality Manager, Technical Manager, Proposal Officer, Auditor, Lead Auditor, Technical Reviewer, Certification Decision Maker, Finance, and Viewer.
- Standard module permissions for view, create, edit, delete, approve, reject, download, and print.
- Initial standards: ISO 9001:2015, ISO 14001:2015, ISO 45001:2018, ISO 22000:2018, ISO 13485:2016, HACCP, FSSC 22000 Version 6, ISO 17021, and ISO 17065.
- Document template keys for proposals, contracts, audit plans, reports, decision records, and certificates.
- Notification rules for certificate expiry, CAPA due, NCR due, surveillance due, and competency expiry.

## Run Order

After installing dependencies and configuring `.env`, run:

```bash
php spark migrate
php spark db:seed InitialAmsSeeder
```

## Design Notes

- Every operational table includes `tenant_id` directly or through a required parent record.
- Generated report files are intentionally not stored permanently. The schema stores report draft data and editable sections so Word/PDF output can be generated on demand.
- Uploaded evidence and client/personnel attachments are stored as file metadata with paths and checksums; the binary files belong in controlled storage.
- Conflict rules for auditor, technical reviewer, and decision maker separation are supported by appointment and review tables and will be enforced in Phase 6 service logic.
- Financial dashboard data can be calculated from proposals, invoices, and payments without duplicating totals in reporting tables.
