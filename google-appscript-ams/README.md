# QSI AMS for Google Apps Script

This folder is a complete Google Apps Script implementation package for the QSI Audit Management System workflow. It is designed as a standalone Apps Script web app backed by Google Cloud SQL MySQL and Google Drive/Docs document generation.

It mirrors the current AMS operating model:

- Google Workspace login and role-based permissions.
- Tenant, user, role, permission, standards, personnel, and client masters.
- Application, application review, proposal, contract, audit programme, appointment, audit plan, audit execution, report response, NCR/CAPA, technical review, decision, certificate, surveillance, recertification, feedback, finance, document, notification, and audit-log records.
- Controlled document generation to Google Docs and PDF in Drive.
- Public certificate verification route.
- Admin seed/bootstrap actions for roles, permissions, standards, and document templates.

## Project Files

- `appsscript.json` - Apps Script manifest.
- `Code.gs` - server-side Apps Script application, router, data access, RBAC, workflow, documents, finance, admin, and API dispatcher.
- `Index.html` - complete browser UI, styles, and client-side calls.
- `sql/schema_cloudsql.sql` - Cloud SQL schema for the Apps Script AMS.
- `docs/deployment.md` - deployment, properties, and operating notes.

## Required Script Properties

Set these in Apps Script Project Settings > Script properties:

- `CLOUD_SQL_CONNECTION_NAME` - Cloud SQL instance connection name, for example `project:region:instance`.
- `DB_NAME` - database name, for example `qsi_ams`.
- `DB_USER` - database user.
- `DB_PASSWORD` - database password.
- `AMS_ROOT_FOLDER_ID` - Google Drive folder for generated AMS documents.
- `AMS_PUBLIC_BASE_URL` - deployed web app URL, used for certificate verification links.
- `AMS_DEV_USER_EMAIL` - optional fallback email for local/test deployments where active-user email is blank.

## Install

1. Create a Google Cloud SQL MySQL database.
2. Run `sql/schema_cloudsql.sql`.
3. Create a new standalone Apps Script project.
4. Copy `appsscript.json`, `Code.gs`, and `Index.html` into the project.
5. Add the required Script Properties.
6. Deploy as Web app, execute as `User accessing the web app`, access restricted to your Workspace domain.
7. Open the deployed URL and run `Admin > Seed baseline`.
8. Add users with their Google Workspace emails and roles.

## Operating Boundary

This is intentionally Google-native. It does not reuse the PHP runtime, CodeIgniter sessions, or local MariaDB. It keeps accreditation-critical records in Cloud SQL, not Google Sheets.

