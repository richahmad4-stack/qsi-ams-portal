# QSI AMS Phase 5 Audit Execution

Phase 5 starts the operational audit workflow after contract and audit program approval.

## Scope Completed

- Auditor appointment workflow per audit event.
- Appointment roles for lead auditor, auditor, technical expert and observer.
- Competence, impartiality and conflict-of-interest confirmation stored with each appointment.
- Audit plan creation per audit event.
- Audit plan status control: draft, prepared, approved and issued.
- Audit timetable items with date, time, activity, department, process, clauses, auditor and notes.
- Audit program generation now applies default audit duration logic: Stage 1 is 1 working day and Stage 2 is 2 working days.
- Stage 2 planned end date is calculated from duration using Saudi working days.
- Audit execution screen per audit event.
- Findings capture for conformity, positive findings, OFIs and observations.
- NCR creation from audit execution with classification, requirement, finding, evidence, responsible person and target date.
- NCR closure fields for correction, root cause, corrective action, verification and closure notes.
- Audit event completion action.
- Demo client seeded with Stage 1 and Stage 2 audit plans and timetable rows.

## Route Areas

- `/workflow/certification/{clientId}/appointments`
- `/workflow/certification/{clientId}/audit-plan`
- `/workflow/certification/{clientId}/audit-events/{eventId}/execute`

## Next Phase 5 Work

- Audit report submission to Technical Manager.
- NCR evidence upload workflow.
- PDF generation after templates/questions are loaded.
