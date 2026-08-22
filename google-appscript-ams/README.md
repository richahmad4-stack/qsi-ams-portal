# QSI AMS for Google Apps Script

This folder contains the Google Apps Script implementation package for the QSI Audit Management System workflow. The active `Code.gs` is the no-paid deployment version backed by Google Sheets and Google Drive/Docs document generation. `Code_cloudsql.gs` preserves the Cloud SQL/JDBC version for a future paid production deployment.

The current Apps Script version is a working starter/demo package, not full parity with the CodeIgniter AMS. The full parity roadmap is controlled in `../docs/google-appscript-full-ams-parity-plan.md`.

The target operating model is to mirror the current AMS:

- Google Workspace login and role-based permissions.
- Tenant, user, role, permission, standards, personnel, and client masters.
- Application, application review, proposal, contract, audit programme, appointment, audit plan, audit execution, report response, NCR/CAPA, technical review, decision, certificate, surveillance, recertification, feedback, finance, document, notification, and audit-log records.
- Controlled document generation to Google Docs and PDF in Drive.
- Public certificate verification route.
- Admin seed/bootstrap actions for roles, permissions, standards, and document templates.
- One client workflow file with tabs for application, commercial, Stage 1, Stage 2, Surveillance 1, Surveillance 2, Recertification, NCR/CAPA, Technical Review, Decision, Certificates, Documents, Finance, Feedback, and Audit Log.
- Super Admin-only Cycle Builder for controlled complete-cycle digitalization.
- Clause Builder for controlled QSI audit requirements and standard/clause mappings.
- NCR Builder for creating linked NCR/CAPA packages from audit responses and findings.

## Project Files

- `appsscript.json` - Apps Script manifest.
- `Code.gs` - no-paid server-side Apps Script application using Google Sheets as the data store.
- `Code_cloudsql.gs` - optional Cloud SQL/JDBC backend for a future paid production deployment.
- `Index.html` - complete browser UI, styles, and client-side calls.
- `sql/schema_cloudsql.sql` - Cloud SQL schema for the Apps Script AMS.
- `docs/deployment.md` - deployment, properties, and operating notes.

## No-Paid Script Properties

Set these in Apps Script Project Settings > Script properties:

- `AMS_DATA_SPREADSHEET_ID` - created automatically by `setupNoPaidStorage`.
- `AMS_ROOT_FOLDER_ID` - created automatically by `setupNoPaidStorage`.
- `AMS_PUBLIC_BASE_URL` - deployed web app URL, used for certificate verification links.
- `AMS_DEV_USER_EMAIL` - optional fallback email for local/test deployments where active-user email is blank.

## Install

1. Create a new standalone Apps Script project.
2. Copy `appsscript.json`, `Code.gs`, and `Index.html` into the project.
3. Deploy as Web app, execute as `User accessing the web app`.
4. Open the deployed URL and run `Seed baseline / create first admin`.
5. The app creates `QSI AMS Data` in Google Sheets and `QSI AMS Generated Documents` in Drive.
6. Add users with their Google Workspace emails and roles.

## Operating Boundary

This is intentionally Google-native. It does not reuse the PHP runtime, CodeIgniter sessions, or local MariaDB. The no-paid version uses Google Sheets as the active data store. For stronger production controls, use the preserved Cloud SQL version when paid infrastructure is approved.

## Current Parity Status

The no-paid Apps Script package now includes the first working Clause Builder and NCR Builder modules, the one-client workflow tab structure, and demo stage shells for Stage 1, Stage 2, Surveillance 1, Surveillance 2, and Recertification. It still requires further work before full parity: richer Cycle Builder UI/import, stronger role/conflict gates, complete document templates, client portal depth, and browser/PDF verification.
