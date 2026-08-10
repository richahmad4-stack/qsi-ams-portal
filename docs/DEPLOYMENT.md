# AMS Deployment Baseline

The current application is CodeIgniter 4 with MySQLi. The supported production baseline is PHP 8.3, Apache, MariaDB, Caddy, and persistent volumes. PostgreSQL is not enabled by this package and requires a separately tested data-access migration.

## Local Docker Validation

1. Copy `.env.docker.example` to `.env.docker` and replace both database passwords.
2. Start the stack:

   `docker compose --env-file .env.docker up -d --build`

3. Run controlled migrations after the database is healthy:

   `docker compose --env-file .env.docker exec app php spark migrate --all`

4. Open `http://localhost:8088`.

Do not set `AMS_RUN_MIGRATIONS=1` in multi-replica production deployments. Run migrations once as a controlled release step.

## Persistence And Backup

- MariaDB data: `db_data` volume.
- Generated documents, uploads, sessions, logs, and cache: `writable_data` volume.
- Caddy certificates/configuration: `caddy_data` and `caddy_config` volumes.

Database backup:

`docker compose --env-file .env.docker exec -T db mariadb-dump -u root -p qsi_ams > qsi_ams-backup.sql`

Uploads backup:

`docker run --rm -v qsi-ams-portal_writable_data:/data -v ${PWD}:/backup alpine tar czf /backup/ams-writable-backup.tgz -C /data .`

A backup is not accepted as proven until it has been restored into a clean test stack and record/document counts have been reconciled.

## Hostinger VPS

Point the production domain to the VPS, set `AMS_SITE_ADDRESS` and `AMS_BASE_URL` to the HTTPS domain, allow only ports 80/443 publicly, keep MariaDB private to the Compose network, and store `.env.docker` only on the VPS with restricted permissions. SMTP, scheduled reminders, off-site backups, and monitoring must be configured before production approval.
