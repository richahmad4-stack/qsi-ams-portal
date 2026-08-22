# QSI AMS for Google Apps Script

This folder is a complete Google Apps Script implementation package for the QSI Audit Management System workflow. The active `Code.gs` is the no-paid deployment version backed by Google Sheets and Google Drive/Docs document generation. `Code_cloudsql.gs` preserves the Cloud SQL/JDBC version for a future paid production deployment.

It mirrors the current AMS operating model:

- Google Workspace login and role-based permissions.
- Tenant, user, role, permission, standards, personnel, and client masters.
- Application, application review, proposal, contract, audit programme, appointment, audit plan, audit execution, report response, NCR/CAPA, technical review, decision, certificate, surveillance, recertification, feedback, finance, document, notification, and audit-log records.
- Controlled document generation to Google Docs and PDF in Drive.
- Public certificate verification route.
- Admin seed/bootstrap actions for roles, permissions, standards, and document templates.

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
