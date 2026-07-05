# QSI Audit Management System

QSI AMS is a production-oriented certification body audit management system built for PHP 8.3, CodeIgniter 4, MySQL 8, Bootstrap 5, DataTables, PHPWord, DomPDF, Endroid QRCode, and Chart.js.

This repository is being generated in the requested phases. Phase 1 contains the multi-tenant database foundation, migrations, seed data, and schema documentation. Phase 2 adds session authentication and role based access control enforcement. Phase 3 adds the tenant dashboard with live operational metrics. Phase 4 adds the first working master-data screens.

## Phase Files

- `app/Database/Migrations/2026-07-04-000001_CreateAmsCoreSchema.php`
- `app/Database/Migrations/2026-07-04-000002_CreateAuthenticationTables.php`
- `app/Database/Seeds/InitialAmsSeeder.php`
- `app/Database/Seeds/InitialAdminSeeder.php`
- `app/Controllers/Auth/AuthController.php`
- `app/Controllers/Account/PasswordController.php`
- `app/Controllers/Dashboard/DashboardController.php`
- `app/Filters/AuthFilter.php`
- `app/Filters/PermissionFilter.php`
- `app/Services/AuthService.php`
- `app/Services/PermissionService.php`
- `app/Services/AuditLogger.php`
- `app/Services/DashboardService.php`
- `app/Views/dashboard/index.php`
- `app/Views/layouts/main.php`
- `app/Controllers/Masters/*`
- `app/Models/ClientModel.php`
- `app/Models/StandardModel.php`
- `app/Models/PersonnelModel.php`
- `app/Models/ClauseLibraryModel.php`
- `app/Views/masters/**`
- `app/Config/Database.php`
- `app/Config/Routes.php`
- `app/Config/Filters.php`
- `env.example`
- `docs/phase1-database-design.md`
- `docs/phase2-authentication-rbac.md`
- `docs/phase3-dashboard.md`
- `docs/phase4-masters.md`
- `docs/local-run.md`

## Local Setup

1. Install Composer dependencies.
2. Copy `env.example` to `.env`.
3. Configure the database credentials for a MySQL 8 database.
4. Set `AMS_ADMIN_EMAIL` and a strong `AMS_ADMIN_PASSWORD`.
5. Run migrations and seeders:

```bash
php spark migrate
php spark db:seed InitialAmsSeeder
php spark db:seed InitialAdminSeeder
```

For the local workspace startup command and temporary admin login, see `docs/local-run.md`.
