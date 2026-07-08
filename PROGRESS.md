# AMS Project Progress

QSI AMS is a CodeIgniter 4 / PHP / MySQL Audit Management System for a certification body. It manages certification applications, standards, client files, audit programs, auditor appointments, audit planning, audit execution, NCR/CAPA, technical reviews, decisions, certificate issuance, surveillance audits, feedback, reference data, and controlled PDF outputs.

## Complete / Working

- Tenant login, roles, permissions, password change, and audit logging.
- Client master, personnel master, standards selection, IAF/NACE/food/medical reference data.
- Dynamic certification application and application review with audit man-day calculation logic.
- Proposal, contract, audit program, auditor appointment, audit plan, audit report, NCR/CAPA, technical review, decision, certificate issue, and feedback screens.
- Technical Review now includes detailed administration/technical review rows from the supplied `F 34` format, with audit information, accreditation scope checks, report completeness, NCR/CAPA review, authorization decision, and PDF output.
- Decision Making now includes pre-issue accreditation, scope, audit time, mark/statement checks, declaration/sign-off fields, and PDF output based on the supplied decision checklist.
- Dashboard/sidebar layout has been restored to the earlier simple AMS structure.
- Finance is kept as its own separate sidebar dashboard/module for proposals, invoices, payments, revenue, outstanding payments, and finance reports.
- Generic NCR/CAPA PDF output now uses stacked record tables so long root-cause, correction, corrective-action, evidence, verification, and comments wrap inside page margins.
- Separate workflow areas for Certification Audit, Surveillance Audit #01, Surveillance Audit #02, and Recertification/Expiry.
- Separate event PDFs for Stage 1, Stage 2, Surveillance 1, Surveillance 2, and recertification plans/reports.
- Dashboard cards with linked detail sections and PDF actions.
- Compliance hardening pass added backend gates for auditor appointment competence/impartiality, surveillance due-date locking, audit completion readiness, Technical Review approval, and Certification Decision independence.
- Audit conformity notes are now clearly marked as system/AI drafts requiring auditor confirmation, and placeholder clause requirement wording is replaced with internal checklist-question language.
- Audit duration calculation now uses a controlled scheme-aware rule set with separate management-system, HACCP, food-safety, and medical-device bases, plus auditable calculation basis text.
- Multi-standard competence controls now require full selected-standard/scope coverage for the audit team, Technical Reviewer, and Decision Maker while still allowing multiple auditors to share coverage.
- Technical Review and Certification Decision approval now include file-level readiness checks for application review, accepted proposal, signed/approved contract, audit programme, required audit events, submitted reports, closed NCR/CAPA, and confirmed report clauses.
- Audit report conformity sections now track source type, auditor confirmation, confirmation date/user, and confirmation notes. Audit completion requires every conformity section to be confirmed.
- Finance dashboard route and sidebar visibility now use the `finance:view` permission instead of proposal access.
- PHPUnit smoke tests cover the audit-duration service and critical workflow gate wiring.
- Multiple roles per user are supported, with `super_admin` as the only full-access tenant owner role. Operational roles are normalized, and workflow actions now enforce per-stage roles plus assignment/conflict rules for auditors, reviewers, decision makers, GM approval, certificate issue, and feedback.
- Role-specific dashboards are now active: global management roles keep the management dashboard, while auditors/lead auditors see their assigned audit queue, technical reviewers see assigned reviews, decision makers see assigned decisions, and finance users see finance work.
- Sidebar visibility is now role-aware so task users only see relevant navigation such as My Audits, My Reviews, My Decisions, Finance, or Clause Library as applicable.
- Auditor appointment saves now create in-app notifications and can send appointment emails when SMTP/email notifications are configured.
- Super User Cycle Builder is wired: Super User/Admin can preview and prepare a complete three-year certification lifecycle from client, standard, scope, issue-date, risk, and status inputs. It creates application, review, proposal, contract, invoice/payment records, audit programme, stage/surveillance/recertification audit events, appointments, plans, reports, NCR/CAPA samples, technical reviews, decisions, certificates, feedback, reminders, notifications, audit logs, and a preparation history record.
- Cycle Builder now supports CSV/XLSX batch upload with a downloadable template.
- Cycle Builder now prepares application responses from entered client/cycle data and uses proper PDF headings: "Information Required" and "Response".
- Actual QSI personnel from the supplied table have been added with multiple roles and all-standard competence where indicated; old demo users are inactive/hidden.
- Audit plans prepared by Cycle Builder now include more detailed 09:00-17:00 stage-specific timing, lunch break, process/unit coverage, and clause/criteria focus.
- Cycle Builder standard mode now prepares the full controlled workflow pack through application review, proposal acceptance, contract, audit reports, NCR/CAPA closure, Technical Review, Decision, GM approval, certificate issue, and feedback. Assignment preference keeps Eng. Mohammad Ahmad as Decision Maker, Ms. Rimsha Mahmoud as Technical Reviewer, Dr. Rana Amjad Hanif as GM, and a separate lead auditor where available.
- Prepared cycle files now auto-confirm system-prepared conformity notes under the assigned auditor, accept approved all-standard/all-scope competence for Food/IAF/Medical categories, and prepare 4 NCR/CAPA records by default for applicable audit stages.
- Content Library / Clause Pool has been added as a master-data module with approved reusable templates, CSV import/export, scope/standard/stage/content filters, and report-screen matching. Auto-filled conformity notes now use the Clause Pool first, remain editable, and are auto-confirmed under the assigned auditor when prepared by the system.
- The supplied QSI detailed HACCP/ISO 22000/ISO 9001/ISO 14001/ISO 45001 NC/CAPA workbook has been imported into Clause Pool: 5,076 controlled templates covering conformity answers, NCs, objective evidence, requirement criteria, root causes, correction, corrective action, preventive action, verification, acceptance criteria, CAPA bundles, CAPA options, and industry guidance. Cycle Builder NCR/CAPA creation now uses these pool templates.
- A shared Smart Audit Content Engine now drives report conformity notes and NCR/CAPA packages across audit execution and Cycle Builder. It combines approved Clause Pool material with clause-specific evidence references so each clause has aligned evidence, document references, CAPA text, and auditor-confirmed status without duplicating existing report or NCR/CAPA rows.
- Cycle Builder preview wording now shows that application review, audit evidence, Technical Review, and Decision basis will be system-prepared from controlled inputs instead of saying those items are still pending manual completion.
- Legacy Import has been removed from the active product: routes, sidebar entry, controller, models, views, dashboard card, client-screen legacy flag, role seed permissions, and reference seed permissions are cleaned up. Historical database columns/tables remain only as safe dormant structure.
- Demo client data has been reset and rebuilt as one focused HACCP-only full-workflow demo client: Demo HACCP Catering Kitchen LLC.
- Application question selection is now strict by selected standard, and certification applications exclude all standard-specific question sections. Demo seeding and application PDFs only show the general application sections needed for the client file, preventing ISO 9001/ISO 45001/HACCP-specific question leakage into application forms.
- Controlled PDF styling has been upgraded with a cleaner certification-body layout: wider margins, dark-blue controlled-record headers, calmer table colors, better spacing, softer borders, improved footers, and harmonized official-form styling for application, review, appointment, contract, audit plan, and audit programme outputs.
- NCR/CAPA content generation now avoids repeated food-safety findings by rotating clause/stage-aware themes such as traceability, PRP verification, CCP/OPRP monitoring, supplier approval, release/allergen checks, and competence evidence. Fresh Valley Stage 2 demo NCRs now have unique findings, root causes and corrective actions.
- Added `START-AMS-LOCAL.ps1` to launch local XAMPP MySQL and the AMS development server in separate windows for easier local testing.
- Audit report PDFs now show checklist/conformity notes as full-width controlled audit note blocks instead of squeezing long notes into a narrow table column.
- The supplied QSI Canada Cert logo is now used in the portal login/sidebar and controlled PDF document headers, replacing text-only QSI marks in the main document pack.
- Audit report conformity note rendering now removes repeated internal labels and system metadata such as "Conformity note", "Clause Pool basis", and template-reference wording from PDF output while preserving the audit conclusion and objective evidence.
- Certificate PDFs now use the supplied QSI certificate visual style with the blue vertical certificate band, QSI background, dynamic client/standard/scope text, certification cycle date matrix, QR code, validity code, and approval/print areas.
- Certificate issue now provides two controlled outputs from the same certificate record: a branded PDF for electronic issue and a branded Word DOCX for printing hard-copy certificates.
- Certificate output no longer generates an artificial seal because hard-copy certificates use the physical embossed seal. The certificate lower area has been tightened so dates, signatures, QR code, and validity text do not overlap or clip.
- Certificate lower section has been reworked into controlled blocks: a fixed-width date matrix, balanced approval/print signature row, and separate QR/verification row for both PDF and Word outputs.
- Supplied signature images are now stored as controlled assets and rendered on the certificate approval/print signature lines in both PDF and Word certificate outputs.
- Certificate signature images are enlarged for readability, and long organization names now scale down automatically in both PDF and Word certificate outputs.
- Client logos can now be uploaded from the Client Master and are rendered as an optional controlled logo on certificate PDF and Word outputs.
- HACCP certification applications and application reviews now use strict HACCP defaults for legal/regulatory requirements, product/process risks, technical issues, safety requirements, technological context, audit language, readiness answers, scope/process fields, and removal of Preferred Audit Dates/Preferred Auditor from the application questionnaire.
- Certification Application PDFs no longer show the QR code block or controlled-document footer line; they now show date-only review/submission dates and simple page numbers.
- Application Review Checklist PDFs no longer show the document number/revision/issue/date footer line; they now keep document control in the report header and show simple centered page numbers.
- Proposal and contract PDFs now use fuller CB-style commercial wording for certification process obligations, VAT/invoice terms, Stage 1, Stage 2, certificate issuance, surveillance activity, annexures, acceptance/authorization, contact information, and certification assessment notes.
- Proposal and contract PDFs now start with a polished client-facing cover page, remove old page footers/page numbers, use the supplied QSI KSA stamp in the acceptance/authorization block, and render IAS/SAAC unannounced-visit headings as proper subheadings.
- Proposal and contract cover pages were redesigned again as full-page, low-clutter covers with one client-name placement, restrained QSI blue/gold branding, and no repeated client/title text.
- Proposal and contract workflow screens now use the same controlled commercial wording as the PDF generator, so old saved short notes are replaced on-screen as well as in exported documents.
- Proposal and contract cover pages were tightened again after visual review: smaller QSI blue side band, shorter gold accent, removed controlled-document label, framed certification scope, and lifted metadata block.
- Proposal and contract cover pages now follow the supplied QSI proposal-cover direction with a Riyadh skyline panel, QSI-CERT CO. heading, bold proposal/contract title, excellence/compliance tagline, service badge strip, prepared-for block, compact document detail line, and QSI contact footer.
- HACCP client applications now include a normal application question for `Number of HACCP Studies / Plans`, visible only for HACCP files and linked to application review audit-duration inputs.
- Application Review now uses the saved client application HACCP Studies/Plans count as the source of truth, preventing stale review payload values from showing a different count.
- Operations readiness has been added as a Super Admin dashboard for go-live status covering production environment, public URL/SSL, SMTP/email, reminder processor, schema exports, backup restore proof, website lead intake, and user administration readiness.
- User Administration is now available in the portal: Super Admin/Admin can create users, edit profile/status, assign multiple roles, reset passwords, and require password change without database/seed edits.
- A Compliance Audit Viewer role and login now exists for read-only compliance audit access. This user can see certification cycles, audit files, report PDFs and controlled document outputs, but cannot edit, execute, approve, close NCR/CAPA, issue certificates, or access workflow save actions.
- Reminder processing foundation is now active through `php spark ams:process-reminders`, creating upcoming/overdue audit reminders and certificate-expiry reminders with dashboard notifications for responsible admin roles.
- Website lead intake now has a local `website_leads` table and permissions, ready for the next step of connecting the website/Supabase leads feed to AMS screens.
- Certification workflow status now selects the Initial Stage 2 Technical Review for the certification file card, so future surveillance/recertification pending reviews no longer make an approved certification Technical Review appear in progress.
- Safe database reproducibility files: `database/schema.sql` and reference-only `database/seed-data.sql`.

## Current Focus

Project-owner compliance hardening and operational readiness: core workflow gates are in place, certificate PDF/DOCX outputs are active, and the current focus has moved to production readiness, reminder/email operations, website lead intake, controlled data migration, and management reporting.

## Next

- Build the Website Leads screen and conversion flow so new leads can be qualified, assigned, and converted into clients/applications.
- Add a controlled data migration/import screen for historic certificates and clients using staging/validation before anything becomes live.
- Configure real SMTP settings and test appointment/reminder email delivery outside the local demo environment.
- Add hosting/deployment documentation for domain, TLS, environment secrets, scheduler, queue/process manager, and backup/restore.
- Browser-test the Cycle Builder with one HACCP file, one ISO 9001 file, and one multi-standard file, then review the prepared workflow screens and PDFs.
- Review the single HACCP-only demo client in the browser and confirm the workflow list, client standards badge, certificate, surveillance states, PDFs, and role dashboards read correctly.
- Browser-test the new Smart Audit Content Engine on HACCP, ISO 9001, and multi-standard client files to confirm report clauses and NCR/CAPA records are varied, clause-aligned, and not duplicated.
- Continue refining Clause Pool matching and screen actions so auditors can prepare all clauses, refresh individual clause answers, and refresh NC/CAPA records from approved templates.
- Convert the user-supplied official forms into richer controlled HTML/PDF sections for application, review, proposal, contract, audit programme, appointment, plan, report, NCR/CAPA, technical review, decision, certificate and feedback.
- Expand tests from wiring/smoke tests into database-backed feature tests for surveillance locks, appointment gates, audit completion, Technical Review, Decision, PDF routes, and audit duration edge cases.
- Build a user/role management screen so Super Users can assign multiple roles from the portal instead of relying on seed/migration data.
- Review official licensed MD/scheme tables with the Certification Body and tune the controlled audit-duration rule set where needed.
- Add richer controlled checklist/question banks per standard without copying licensed standard text.
- Continue refining controlled PDF templates against the user-provided document formats, especially certificate issuance and client feedback.
- Continue checking multi-standard client files such as HACCP + ISO 22000 + ISO 9001 for stage-specific workflow, competence matching, and PDF consistency.
- Continue generating/reviewing sample PDFs for application review, proposal, contract, audit programme, audit plans, audit reports, technical review, decision, certificate, and feedback.
- Continue polish across certification, surveillance, and recertification workflow screens where the user finds unclear grouping.
