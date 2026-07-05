# QSI AMS Certification Workflow

This workflow is the operating path used by the AMS from client application to client feedback.

## Workflow Steps

1. Client submits an application and selects one or more applicable standards.
2. Technical Manager reviews the application for scope, competence, resources and capability, then approves or rejects it.
3. Quality Manager independently approves or rejects the Technical Manager-reviewed application.
4. Admin prepares and sends a proposal; the client accepts or rejects it.
5. Contract is generated after proposal acceptance, including scope, standards, audit duration, fees, terms and certification cycle requirements.
6. Three-year audit program is created for initial certification, Surveillance 1, Surveillance 2 and recertification.
7. Auditor appointment is completed with competence, impartiality and conflict-of-interest checks.
8. Auditor prepares and conducts Stage 1 audit.
9. Auditor prepares and conducts Stage 2 audit.
10. Client closes any nonconformities within the defined timeline.
11. Auditor submits the complete audit file; Technical Manager approves it or requests correction.
12. Decision Maker records the certification decision.
13. General Manager provides final approval.
14. Certificates are issued, with a separate certificate for each standard.
15. Client feedback is collected for satisfaction monitoring and continual improvement.

## Implementation Status

- The app now has a `Workflow` menu item at `/workflow/certification`.
- The workflow register lists clients, current step and completion progress.
- The client workflow page shows all 15 steps and reads status from the existing AMS records.
- Workflow pages show responsible names where records exist, including Technical Manager, Quality Manager, auditors, proposal owner, contract signer, Technical Reviewer and Decision Maker.
- The client workflow page includes action buttons for application review, proposal, contract and audit program creation.
- The audit program action generates initial Stage 1, initial Stage 2, Surveillance 1, Surveillance 2 and recertification audit events.
- Feedback is displayed as a workflow step; its data-entry module will be added with the feedback/continual-improvement area.

## PDF Generation Plan

Each workflow step should support on-demand PDF generation after the final question/template sets are loaded.

Planned PDF outputs include:

- Application review PDF with client application answers, selected standards, Technical Manager review and Quality Manager approval.
- Proposal PDF with fees, VAT, scope, selected standards and client acceptance status.
- Contract PDF with certification scope, standards, audit duration, fees, terms, signatures and certification cycle.
- Audit program PDF with the three-year audit schedule.
- Auditor appointment PDF with auditor names, competence check and conflict-of-interest declaration.
- Stage 1 and Stage 2 audit plan PDFs.
- Stage 1 and Stage 2 audit report PDFs.
- NCR/CAPA PDFs with closure evidence and verification.
- Technical review PDF with reviewer name and checklist answers.
- Certification decision PDF with decision maker name and decision rationale.
- Certificate PDF, one certificate per standard.
- Client feedback PDF with satisfaction questions and answers.

The implementation should load template questions from controlled template records, save the answered payloads, then render PDFs on demand from the saved record so every generated PDF is traceable and reproducible.
