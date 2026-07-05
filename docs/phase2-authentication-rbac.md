# QSI AMS Phase 2 Authentication and RBAC

Phase 2 adds session authentication and permission enforcement on top of the Phase 1 database foundation.

## Scope Completed

- Tenant-aware login using tenant code, email, and password.
- Secure password verification with PHP password hashing.
- Session regeneration after successful login.
- Login attempt logging with throttling after repeated failures.
- Logout with audit trail record.
- First administrator creation through environment-driven seeding.
- Password change screen with strong password rules.
- Authentication filter for protected routes.
- Permission filter that checks the Phase 1 RBAC tables.
- Audit logging service for login, logout, password changes, and later module actions.
- Helper functions for permission checks and current user access.
- Initial secured dashboard route.

## Setup

Set these values in `.env` before seeding the first administrator:

```bash
AMS_ADMIN_EMAIL = admin@yourdomain.example
AMS_ADMIN_PASSWORD = "Use a unique strong password"
AMS_ADMIN_NAME = "QSI Administrator"
```

Then run:

```bash
php spark migrate
php spark db:seed InitialAmsSeeder
php spark db:seed InitialAdminSeeder
```

## Security Notes

- The system does not contain a hardcoded administrator password.
- Login attempts are recorded in `login_attempts`.
- Session records are stored in `ci_sessions`.
- Business permissions are stored in `permissions` and assigned through `role_permissions`.
- Every protected route should use both `auth` and a module permission filter.
- Public certificate verification routes will be added later without internal account access.

## Route Pattern

Use this pattern for protected module routes:

```php
$routes->get('clients', 'Clients\ClientController::index', [
    'filter' => 'permission:clients,view',
]);
```

The available actions are:

- view
- create
- edit
- delete
- approve
- reject
- download
- print
