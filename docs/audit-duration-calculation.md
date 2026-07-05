# Audit Duration Calculation

This module is the first audit-time calculation layer for QSI AMS.

## Current Rule

- Initial certification defaults to 3 audit days.
- Stage 1 defaults to 1 working day.
- Stage 2 defaults to 2 working days.
- Generated audit events use Saudi working days, skipping Friday and Saturday.
- Stage 2 planned end date is calculated from the Stage 2 duration instead of always using the start date.

## Standards Basis

- Global ACI lists IAF MD 5:2023 as the document for determining audit time for QMS, EMS and OH&S management systems.
- Global ACI notes that IAF MD 5 supports consistent application of ISO/IEC 17021-1 and does not replace ISO/IEC 17021-1 requirements.
- Global ACI also notes that IAF/ILAC documents remain valid until equivalent Global ACI documents are adopted.

## Next Calculator Upgrade

When the final template/question sets are provided, add controlled tables and factors for:

- employee count bands,
- effective personnel,
- number of sites,
- scheme or standard type,
- integrated management system reduction,
- complexity/risk category,
- exclusions or reductions with justification,
- surveillance and recertification duration rules,
- food, medical and other scheme-specific mandatory documents.

All automatic reductions should store the reason and approver so the audit-time decision remains traceable.
