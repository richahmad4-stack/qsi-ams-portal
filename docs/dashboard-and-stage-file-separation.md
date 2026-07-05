# Dashboard drilldown and audit-stage separation

Implemented changes:

- Dashboard metric cards are clickable.
- Each dashboard card opens a related record list.
- Dashboard detail records include View, Edit, Print and Generate PDF actions where the record supports them.
- Client certification files now include tabbed document sections for application, proposal, contract, audit program, audit stages, certificates and feedback.
- Each audit event has its own stage file page:
  - Stage 1
  - Stage 2
  - Surveillance 1
  - Surveillance 2
  - Recertification
- Each stage file has separate tabs for:
  - Auditor appointment
  - Audit plan
  - Audit report/checklist
  - NCR / CAPA
  - Technical review
  - Decision
  - PDFs
- Stage-specific PDFs are available for:
  - Audit plan
  - Audit report
  - Technical review
  - Decision

Certification cycle logic:

- Surveillance 1 due date = certificate issue date + 1 year - 1 day.
- Surveillance 2 due date = certificate issue date + 2 years - 1 day.
- Certificate expiry date = certificate issue date + 3 years - 1 day.

For the demo certificate issue date `2026-07-04`, the system now calculates:

- Surveillance 1: `2027-07-03`
- Surveillance 2: `2028-07-03`
- Expiry: `2029-07-03`

Audit report checklist behavior:

- Every audit stage gets its own report draft.
- Every audit stage gets its own checklist/conformity notes.
- Opening the client file or generating an event audit report PDF ensures the checklist exists for all audit stages.

Validation completed:

- Dashboard drilldown routes tested.
- Stage 1, Stage 2, Surveillance 1, Surveillance 2 and Recertification file pages tested.
- Stage-specific PDF generation tested.
- All demo audit stages confirmed with checklist rows.
- Full PHP syntax check passed for `app` and `public`.
