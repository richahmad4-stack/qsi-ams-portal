# Local Run Notes

The project has been configured for local testing with a project-local MariaDB data folder because the normal XAMPP data folder was not writable from this environment.

## Local Admin Login

- Tenant code: `QSI`
- Email: `admin@qsi.local`
- Password: set locally in `.env` as `AMS_ADMIN_PASSWORD`

Change this password after first login.

## Start The App

From `C:\Users\PCD\Documents\AMS`, run:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\start-local.ps1
```

Then open:

```text
http://localhost:8080/login
```

## What The Script Starts

- Local MariaDB on `127.0.0.1:3307`
- QSI AMS on `http://127.0.0.1:8080`

The local database files are stored in `.mysql-data/`, which is ignored by Git.

The application launcher also loads `scripts/php-local.ini` for the PHP built-in server. This enables the required `intl` extension and prevents startup warnings from sending response headers before CodeIgniter initializes its database-backed session.
