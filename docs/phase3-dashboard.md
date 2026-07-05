# QSI AMS Phase 3 Dashboard

Phase 3 adds the authenticated tenant dashboard using live database queries.

## Scope Completed

- Total clients.
- Legacy clients.
- Active certificates.
- Expired certificates.
- Certificates expiring within 90 days.
- Open NCRs.
- Open CAPAs.
- Pending technical reviews.
- Pending certification decisions.
- Upcoming audits within 30 days.
- Upcoming surveillance audits within 90 days.
- Total revenue from recorded payments.
- Monthly revenue from recorded payments.
- Proposal pipeline by status.
- Certification fee summary.
- Surveillance 1 fee summary.
- Surveillance 2 fee summary.
- Auditor workload by active appointment count.
- Audit calendar for the next 90 days.
- Audit status chart.
- Recent activities from the audit trail.

## Files

- `app/Services/DashboardService.php`
- `app/Controllers/Dashboard/DashboardController.php`
- `app/Views/dashboard/index.php`

## Data Source Notes

- Dashboard data is calculated from operational tables at request time.
- No duplicate dashboard totals are stored.
- Revenue uses `payments` joined to `invoices`.
- Audit metrics use `audit_events` joined to `audit_programs`.
- Auditor workload uses active `auditor_appointments`.
- Recent activities use `audit_logs`.

## Environment Note

The dashboard requires a working MySQL connection and migrated schema. The local command checks boot and route configuration successfully, but database execution needs the MySQL service to be running and `.env` credentials to point to the QSI AMS database.
