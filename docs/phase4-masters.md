# QSI AMS Phase 4 Masters

Phase 4 adds the first working master-data screens behind authentication and RBAC.

## Scope Completed

- Shared authenticated application layout with sidebar navigation.
- Dashboard moved into the shared layout.
- Client Master list, create, edit, and soft delete.
- Client detail workspace for standards, IAF/NACE/category classification, sites, processes, and evidence metadata.
- Standards list, create, edit, and deactivate.
- IAF Codes list, create, edit, and deactivate.
- NACE Codes list, create, edit, and deactivate.
- Food Chain Categories list, create, edit, and deactivate.
- Medical Device Categories list, create, edit, and deactivate.
- Personnel Master list, create, edit, and soft delete.
- Personnel competency matrix with standard/category approvals and validity windows.
- Clause Library list, create, edit, and deactivate.
- Legacy client CSV import with preview, validation, duplicate detection, commit, and rollback.
- DataTables-enabled list screens.
- Audit log records for create, update, delete/deactivate actions.
- Tenant enforcement for tenant-owned records.

## Files Added

- `app/Views/layouts/main.php`
- `app/Controllers/Masters/ClientController.php`
- `app/Controllers/Masters/StandardController.php`
- `app/Controllers/Masters/ReferenceController.php`
- `app/Controllers/Masters/PersonnelController.php`
- `app/Controllers/Masters/ClauseLibraryController.php`
- `app/Controllers/Masters/LegacyImportController.php`
- `app/Models/ClientModel.php`
- `app/Models/ClientStandardModel.php`
- `app/Models/ClientSiteModel.php`
- `app/Models/ClientProcessModel.php`
- `app/Models/ClientAttachmentModel.php`
- `app/Models/StandardModel.php`
- `app/Models/IafCodeModel.php`
- `app/Models/NaceCodeModel.php`
- `app/Models/FoodChainCategoryModel.php`
- `app/Models/MedicalDeviceCategoryModel.php`
- `app/Models/PersonnelModel.php`
- `app/Models/PersonnelCompetencyModel.php`
- `app/Models/LegacyImportBatchModel.php`
- `app/Models/LegacyImportRowModel.php`
- `app/Models/ClauseLibraryModel.php`
- `app/Views/masters/**`

## Route Areas

- `/masters/clients`
- `/masters/standards`
- `/masters/references/iaf`
- `/masters/references/nace`
- `/masters/references/food`
- `/masters/references/medical`
- `/masters/personnel`
- `/masters/personnel/{id}`
- `/masters/clauses`
- `/masters/imports`

## Notes

- Client attachments are stored as controlled evidence metadata records in this phase. Physical file upload/storage can be connected later to the same records.
- Legacy import currently accepts CSV files. Excel workbooks should be saved/exported as CSV before import.
- Clause Library records added here are the foundation for later auto-generated audit reports.
- Import rollback soft-deletes clients created from that batch and marks the import rows as rolled back.
