# Clause library seeding

The system is seeded with audit-ready placeholder clause records for every active standard.

This starter library includes:

- Clause and subclause identifiers used by the audit workflow.
- Generic requirement placeholders.
- Predefined conformity notes.
- Positive finding wording.
- Opportunity for improvement wording.
- Minor and major nonconformity wording.
- Evidence examples.
- Auditor guidance.
- Stage applicability for Stage 1, Stage 2, surveillance and recertification audits.

The seeded requirement text is intentionally generic. It is not official ISO, FSSC, HACCP or scheme-owner wording. Official standard text, scheme requirements and the certification body's approved checklist questions must be added only from licensed or internally approved templates.

Run the seeder again any time a new active standard is added:

```bash
php spark db:seed ClauseLibraryPlaceholderSeeder
```

The seeder is repeat-safe. It will not create a duplicate clause for the same tenant, standard and clause number.
