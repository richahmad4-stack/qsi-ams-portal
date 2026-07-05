# Phases 7-10 documents, verification, templates and notifications

Implemented:

## Phase 7 - PDF generation

- Added reusable PDF generation service using Dompdf.
- Added generated document history table.
- Added on-demand PDF downloads from the client workflow:
  - Proposal
  - Contract
  - Audit plan
  - Audit report
  - Technical review
  - Certification decision
  - Client feedback
  - Certificate
- Generated PDFs are stored under `writable/uploads/documents`.

## Phase 8 - Certificate verification

- Certificates now have downloadable PDF output with QR code.
- QR code points to the public verification route:
  - `/certificates/verify/{public_slug}`
- Public verification page records lookup events in `certificate_public_events`.

## Phase 9 - Template foundation

- Added document template management screens:
  - `/masters/templates`
- Seeded starter template versions for the existing document templates.
- Template editing saves a new version and marks it as active.

The live PDF generator currently uses built-in starter layouts. Official certification body templates can be imported later and mapped into these template records.

## Phase 10 - Notifications and visibility

- Added generated document logging.
- Added dashboard notification panel.
- Certificate issue and feedback capture create dashboard notifications.

Validation completed:

- Database migrations applied successfully.
- Starter template versions seeded.
- Demo client PDFs generated for all supported document types.
- Certificate PDF rendered visually using Poppler and checked for readable layout and QR code.
- Public certificate verification route tested successfully.
- Full PHP syntax check passed for `app` and `public`.

Recommended next improvements:

- Replace starter PDF layouts with the certification body's official document templates.
- Add PDF preview buttons beside download buttons.
- Add certificate suspension/withdrawal workflow.
- Add automatic reminders for surveillance due dates, certificate expiry and NCR due dates.
