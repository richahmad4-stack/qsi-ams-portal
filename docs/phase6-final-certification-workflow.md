# Phase 6 final certification workflow

Implemented final workflow records after audit execution:

- Technical file review with reviewer name, checklist confirmations, recommendation, status and notes.
- Certification decision with decision maker name, decision reason, signature and status.
- General Manager final approval recorded against the logged-in user with approval notes and timestamp.
- Certificate generation after GM-approved granted decision.
- One certificate record is generated for each selected client standard.
- Client certification status and certificate dates are updated after certificate issue.
- Client feedback capture with ratings, comments and improvement suggestions.

Workflow pages:

- `/workflow/certification/{clientId}/technical-review`
- `/workflow/certification/{clientId}/decision`
- `/workflow/certification/{clientId}/certificates`
- `/workflow/certification/{clientId}/feedback`

Database changes:

- Added General Manager approval fields to `certification_decisions`.
- Added `client_feedback`.

Validation completed:

- Migration applied successfully.
- Demo client tested through technical review, decision, GM approval, certificate issue and feedback.
- Full PHP syntax check passed for `app` and `public`.

Recommended next phase:

- PDF generation for proposal, contract, audit plan, audit report, NCR, technical review, decision, certificate and feedback.
- Template import from the certification body's approved documents and questionnaires.
- Public certificate verification route for QR payloads.
