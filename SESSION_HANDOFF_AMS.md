# AMS Session Handoff

## Project

Audit Management System for a Certification Body, located at:

`C:\Users\PCD\Documents\AMS`

Framework: CodeIgniter 4 / PHP / MySQL.

Local run command:

```powershell
cd C:\Users\PCD\Documents\AMS
php spark serve --host 127.0.0.1 --port 8080
```

Login:

- Tenant: `QSI`
- Email: `admin@qsi.local`
- Password: set locally in `.env` as `AMS_ADMIN_PASSWORD`

Database:

- MySQL/XAMPP
- Database: `qsi_ams`
- Port currently configured: `3306`

## Main User Goal

Build a complete Certification Body Audit Management System with real workflow logic for certification, surveillance, audit planning, audit execution, NCR/CAPA, reviews, decisions, certificates, client feedback, reference data, dynamic certification application forms, and controlled PDFs.

## Completed Major Features

- Login and tenant setup.
- Main workflow dashboard.
- Clickable workflow cards.
- Client management.
- Standards selection on clients.
- Certification workflow:
  - Client application
  - Technical Manager review
  - Quality Manager approval
  - Proposal
  - Contract
  - Three-year audit program
  - Auditor appointment
  - Stage 1 plan/report
  - Stage 2 plan/report
  - NCR/CAPA
  - Technical file review
  - Certification decision
  - General Manager approval
  - Certificate issue
  - Client feedback
- Separate audit plans and reports for Stage 1, Stage 2, Surveillance 1, Surveillance 2, and recertification.
- Surveillance Audit #01 and #02 workflow sections.
- Surveillance lock logic:
  - Surveillance 1 due = issue date + 1 year - 1 day
  - Surveillance 2 due = issue date + 2 years - 1 day
  - Expiry = issue date + 3 years - 1 day
- Separate CAPA records for certification and surveillance events.
- Audit report execution screen with checklist, predefined conformity notes, and NCR/CAPA handling.
- Conformity notes are prefilled/editable; only NC/CAPA needs explicit auditor save.
- Controlled PDF generation for workflow documents.
- Certificate cycle date calculation.

## Seeded Reference Data

Seeder added and run for:

- IAF Codes
- NACE Codes
- Food Categories
- Medical Categories
- Clause library placeholders and predefined notes/NCR references
- Dynamic application question library

Recent question library count:

- 182 active questions

Question groups include:

- Common questions
- ISO 9001 questions
- ISO 45001 questions
- HACCP questions

## Dynamic Certification Application Module

Implemented from the user-provided certification application questionnaire PDF/request.

New tables:

- `question_library`
- `certification_applications`
- `application_selected_standards`
- `application_questions`
- `application_answers`
- `application_attachments`

Key behavior:

- Application form is dynamic based on selected standards.
- Common questions plus standard-specific questions.
- Duplicate questions handled by `question_key`.
- Selected standards saved with application.
- Answers saved separately.
- Attachments supported.
- Client master data updated from application where relevant.
- Future standards can be added by updating the question library.
- PDF button added for Certification Application PDF.

Routes:

- `/workflow/certification/{clientId}/application`
- `/workflow/certification/{clientId}/documents/certification_application`

## Current Latest Work: Application Review F 28

User provided `Application Review Report.pdf` and clarified:

> This information is needed inside Application Review.

Implemented:

- Application Review screen now contains the F 28 Application Review Checklist Report fields:
  - Document control
  - Client detail
  - Scope/activity description
  - Client scope checklist
  - Recertification
  - Multiple/temporary sites
  - Employee justification
  - Accounts
  - Audit scheme
  - Competence requirement
  - Audit man-day calculation
  - Reduction
  - Reviewer comments/status
  - Quality Manager comments/status
  - General Manager comments/status
- Detailed review data is saved in `application_reviews.review_payload`.
- Added structured columns for document control and approvals.
- Added new PDF document type:
  - `application_review`
- Added PDF button in workflow document area and Application Review page.

New migration:

- `app/Database/Migrations/2026-07-05-000004_AddDetailedApplicationReviewFields.php`

Updated files:

- `app/Controllers/Workflow/WorkflowActionController.php`
- `app/Views/workflow/actions/review.php`
- `app/Models/ApplicationReviewModel.php`
- `app/Services/DocumentGeneratorService.php`
- `app/Controllers/Workflow/WorkflowDocumentController.php`
- `app/Views/workflow/show.php`

Tested:

- PHP syntax checks passed.
- Migration ran successfully.
- `/workflow/certification/1/review` returns 200.
- Application Review PDF returns 200 and generates bytes.
- Save test worked and stored JSON review details plus QM/GM statuses.

## Useful Verification Commands

```powershell
php spark migrate
php -l app\Controllers\Workflow\WorkflowActionController.php
php -l app\Views\workflow\actions\review.php
php -l app\Services\DocumentGeneratorService.php
```

Check database:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root -D qsi_ams -e "DESCRIBE application_reviews;"
```

## Suggested Next Steps

1. User review of the new Application Review screen.
2. Improve visual layout of the Application Review PDF if the user wants it closer to the uploaded sample.
3. Add role-specific logins and permissions for:
   - Admin
   - Technical Manager
   - Quality Manager
   - Auditor
   - Decision Maker
   - General Manager
   - Client portal user
4. Continue refining templates when user provides actual CB templates.
5. Add more exact ISO/IAF/MD audit duration calculation logic.
6. Add PDF rendering checks for final template polishing.
