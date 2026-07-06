<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$payload = isset($review['checklist_payload']) ? json_decode((string) $review['checklist_payload'], true) : [];
if (! is_array($payload)) {
    $payload = [];
}

$storedTechnicalRows = [];
foreach (($payload['checklist_rows'] ?? []) as $row) {
    if (is_array($row) && isset($row['key'])) {
        $storedTechnicalRows[(string) $row['key']] = $row;
    }
}

$payloadValue = static fn (string $key, string $default = ''): string => (string) old($key, $payload[$key] ?? $default);
$rowValue = static fn (string $key, string $field, string $default = ''): string => (string) old(
    'technical_checklist.' . $key . '.' . $field,
    $storedTechnicalRows[$key][$field] ?? $default
);

$technicalChecklist = [
    'Initial / administration and technical review' => [
        ['key' => 'application_form', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Questionnaire / application form is available and complete.', 'evidence' => 'Application file checked.'],
        ['key' => 'application_review', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Application review or recertification planning review is complete and authorised.', 'evidence' => 'Application review record checked.'],
        ['key' => 'quotation', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Quotation / proposal is available and accepted.', 'evidence' => 'Proposal and acceptance checked.'],
        ['key' => 'purchase_order', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Purchase order, job order or contract reference is available where applicable.', 'evidence' => 'Commercial file checked.'],
        ['key' => 'fees_status', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Fees status is checked before certificate release.', 'evidence' => 'Fee status checked.'],
        ['key' => 'iaf_code_match', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'IAF / category code matches assigned auditor competence.', 'evidence' => 'Auditor competence and scope checked.'],
        ['key' => 'risk_category', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Risk levels, product categories or scheme categories are correctly identified.', 'evidence' => 'Risk/category basis reviewed.'],
        ['key' => 'stage1_report_complete', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Stage 1 report is available, complete, reviewed and authorised, including changes where applicable.', 'evidence' => 'Stage 1 report reviewed.'],
    ],
    'Audit delivery and report review' => [
        ['key' => 'audit_duration', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Audit duration is recorded and agrees with application review / audit programme.', 'evidence' => 'Audit duration checked.'],
        ['key' => 'stage1_discrepancies_closed', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'All discrepancies from Stage 1 or previous visit are closed or justified.', 'evidence' => 'Previous action position checked.'],
        ['key' => 'team_notes_available', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Audit team notes and audit checklist are available.', 'evidence' => 'Audit notes/checklist checked.'],
        ['key' => 'audit_delivery_method', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Audit delivery method is identified, including on-site, remote or ICT approach where applicable.', 'evidence' => 'Audit delivery method checked.'],
        ['key' => 'normative_references', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Applicable normative references and sector scheme requirements are included in the audit report.', 'evidence' => 'Normative reference section reviewed.'],
        ['key' => 'stakeholder_influence', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Relevant stakeholder / interested party influence is addressed where required by the standard.', 'evidence' => 'Audit report context reviewed.'],
    ],
    'Audit report completeness' => [
        ['key' => 'scope_information', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Scope information, client information and applicable locations are complete.', 'evidence' => 'Scope and client information checked.'],
        ['key' => 'technical_expert_functions', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Technical expert coverage of concerned functions is recorded where applicable.', 'evidence' => 'Technical expert record checked where applicable.'],
        ['key' => 'valid_exclusions', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Exclusions or non-applicable requirements are valid and justified.', 'evidence' => 'Exclusion justification checked.'],
        ['key' => 'attendance_plan_program', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Attendance register, audit plan and audit programme are available.', 'evidence' => 'Attendance and plan records checked.'],
        ['key' => 'standard_elements', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Audit checklists show the standard elements covered.', 'evidence' => 'Checklist coverage reviewed.'],
        ['key' => 'previous_findings', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Actions for previous findings are addressed.', 'evidence' => 'Previous findings reviewed.'],
        ['key' => 'logo_control', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Control of accreditation and certification logo use is reviewed.', 'evidence' => 'Logo/mark use reviewed.'],
        ['key' => 'report_quality', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Audit report quality, summary of findings and nonconformity classification are acceptable.', 'evidence' => 'Report content quality reviewed.'],
    ],
    'Scope, scheduling and competence controls' => [
        ['key' => 'scope_change_expertise', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Where scope changed significantly, auditor expertise and IAF/category competence were checked.', 'evidence' => 'Scope change and competence check recorded.'],
        ['key' => 'location_scope_details', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Details of locations, trading names and scope are available and correctly worded.', 'evidence' => 'Location and scope wording checked.'],
        ['key' => 'temporary_sites', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Temporary sites visited are identified on the report where applicable.', 'evidence' => 'Temporary site details checked.'],
        ['key' => 'surveillance_schedule', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Surveillance visits and schedule match the approved review / programme.', 'evidence' => 'Surveillance schedule checked.'],
        ['key' => 'auditor_code_surveillance', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Auditor allocated to surveillance holds the required IAF/category competence.', 'evidence' => 'Surveillance auditor competence checked.'],
        ['key' => 'next_visit_booked', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Next visit is booked or recorded in the system where applicable.', 'evidence' => 'Next visit record checked.'],
    ],
    'Nonconformance review' => [
        ['key' => 'ca_reviewed_by_competent_auditor', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Corrective action is reviewed by an auditor with relevant standard / sector competence.', 'evidence' => 'CAPA reviewer competence checked.'],
        ['key' => 'signed_records', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'NCR/CAPA records are signed by auditor, client and expert where applicable.', 'evidence' => 'Signatures checked where applicable.'],
        ['key' => 'ncr_validity_sentencing', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Nonconformities are valid, clearly sentenced and correctly classified.', 'evidence' => 'NCR wording and classification reviewed.'],
        ['key' => 'close_out_method', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Close-out method is specified.', 'evidence' => 'Close-out method checked.'],
        ['key' => 'ca_verified_closed', 'ref' => 'A', 'action_by' => 'Administration', 'requirement' => 'Corrective action is received and verified as closed where applicable.', 'evidence' => 'CAPA closure evidence checked.'],
        ['key' => 'admin_items', 'ref' => 'T', 'action_by' => 'Technical Reviewer', 'requirement' => 'Any items raised by administration reviewer are addressed.', 'evidence' => 'Administration items reviewed.'],
    ],
];
?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/technical-review') ?>" class="panel">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Technical file review</div>
            <div class="text-secondary small">Confirm the audit file is complete before certification decision.</div>
        </div>
        <div class="d-flex gap-2">
            <?php if (! empty($selectedEvent['id'])): ?>
                <a href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $selectedEvent['id'] . '/documents/technical_review') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
                    PDF
                </a>
            <?php endif; ?>
            <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                Back
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label" for="audit_event_id">Audit file</label>
            <select class="form-select" id="audit_event_id" name="audit_event_id" required>
                <?php foreach ($events as $event): ?>
                    <option value="<?= esc($event['id']) ?>" <?= (int) ($selectedEvent['id'] ?? 0) === (int) $event['id'] ? 'selected' : '' ?>>
                        <?= esc($event['audit_number'] . ' - ' . str_replace('_', ' ', $event['event_type']) . ' - ' . $event['status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="reviewer_personnel_id">Technical reviewer</label>
            <select class="form-select" id="reviewer_personnel_id" name="reviewer_personnel_id" required>
                <?php foreach ($reviewers as $reviewer): ?>
                    <option value="<?= esc($reviewer['id']) ?>" <?= (int) ($review['reviewer_personnel_id'] ?? 0) === (int) $reviewer['id'] ? 'selected' : '' ?>>
                        <?= esc($reviewer['full_name'] . ' - ' . str_replace('_', ' ', $reviewer['personnel_type'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Review status</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned for correction', 'rejected' => 'Rejected'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($review['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Audit reports</div>
                <div class="metric-value"><?= esc($reportCount) ?></div>
                <div class="text-secondary small">Submitted/draft report record(s)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Open NCRs</div>
                <div class="metric-value"><?= esc($openNcrCount) ?></div>
                <div class="text-secondary small"><?= esc(max(0, (int) $totalNcrCount - (int) $openNcrCount)) ?> closed of <?= esc($totalNcrCount) ?> total</div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="recommendation">Recommendation</label>
            <select class="form-select" id="recommendation" name="recommendation">
                <?php foreach (['' => 'Not selected', 'recommend_certification' => 'Recommend certification', 'return_for_correction' => 'Return for correction', 'do_not_recommend' => 'Do not recommend'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($review['recommendation'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">File basis</div>
                <table class="table table-sm mb-0">
                    <tbody>
                    <tr><th>Audit stage</th><td><?= esc(str_replace('_', ' ', (string) ($selectedEvent['event_type'] ?? 'Not selected'))) ?></td></tr>
                    <tr><th>Audit number</th><td><?= esc($selectedEvent['audit_number'] ?? '') ?></td></tr>
                    <tr><th>Planned dates</th><td><?= esc(trim((string) (($selectedEvent['planned_start_date'] ?? '') . ' to ' . ($selectedEvent['planned_end_date'] ?? '')), ' to')) ?></td></tr>
                    <tr><th>Standards</th><td><?= esc(implode(', ', array_filter(array_column($standards, 'standard_code')))) ?></td></tr>
                    <tr><th>Reviewed at</th><td><?= esc($review['reviewed_at'] ?? 'Not recorded') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">Audit team</div>
                <?php if ($auditTeam === []): ?>
                    <div class="text-secondary small">No auditor appointment found for this stage.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($auditTeam as $member): ?>
                                <tr>
                                    <td><?= esc($member['full_name']) ?></td>
                                    <td><?= esc(str_replace('_', ' ', (string) $member['appointment_role'])) ?></td>
                                    <td><?= esc($member['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">Audit report submission</div>
                <?php if ($reportRows === []): ?>
                    <div class="text-secondary small">No report record found for this stage.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Report</th><th>Status</th><th>Submitted</th></tr></thead>
                            <tbody>
                            <?php foreach ($reportRows as $reportRow): ?>
                                <tr>
                                    <td><?= esc($reportRow['audit_number'] . ' - v' . $reportRow['version_number']) ?></td>
                                    <td><?= esc($reportRow['status']) ?></td>
                                    <td><?= esc($reportRow['submitted_at'] ?? 'Not submitted') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">NCR / CAPA position</div>
                <?php if ($ncrRows === []): ?>
                    <div class="text-secondary small">No nonconformities recorded for this stage.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>NCR</th><th>Clause</th><th>Class</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($ncrRows as $ncr): ?>
                                <tr>
                                    <td><?= esc($ncr['ncr_number']) ?></td>
                                    <td><?= esc(trim((string) (($ncr['standard_code'] ?? '') . ' ' . ($ncr['clause_number'] ?? '')))) ?></td>
                                    <td><?= esc($ncr['classification']) ?></td>
                                    <td><?= esc($ncr['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="fw-semibold mb-2">Technical review additional information</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="audit_category_nace">Audit category / NACE code</label>
                <input class="form-control" id="audit_category_nace" name="audit_category_nace" value="<?= esc($payloadValue('audit_category_nace', implode(', ', array_filter(array_column($standards, 'nace_code'))))) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="transfer_status">Transfer</label>
                <select class="form-select" id="transfer_status" name="transfer_status">
                    <?php foreach (['No' => 'No', 'Yes' => 'Yes', 'Not applicable' => 'Not applicable'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $payloadValue('transfer_status', 'No') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="audit_result">Audit result</label>
                <input class="form-control" id="audit_result" name="audit_result" value="<?= esc($payloadValue('audit_result', 'Recommended for certification decision after review of audit file.')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="accredited_scope_ias_saac">Accredited scope held with IAS/SAAC?</label>
                <select class="form-select" id="accredited_scope_ias_saac" name="accredited_scope_ias_saac">
                    <?php foreach (['Yes' => 'Yes', 'No' => 'No', 'Not applicable' => 'Not applicable'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $payloadValue('accredited_scope_ias_saac', 'Yes') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="accredited_scope_fssc">Accredited scope held with FSSC?</label>
                <select class="form-select" id="accredited_scope_fssc" name="accredited_scope_fssc">
                    <?php foreach (['Not applicable' => 'Not applicable', 'Yes' => 'Yes', 'No' => 'No'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $payloadValue('accredited_scope_fssc', 'Not applicable') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ias_saac_registration_required">IAS/SAAC registration required?</label>
                <select class="form-select" id="ias_saac_registration_required" name="ias_saac_registration_required">
                    <?php foreach (['Yes' => 'Yes', 'No' => 'No', 'Not applicable' => 'Not applicable'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $payloadValue('ias_saac_registration_required', 'Yes') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="complaints_received">Any complaint received?</label>
                <select class="form-select" id="complaints_received" name="complaints_received">
                    <?php foreach (['No' => 'No', 'Yes' => 'Yes'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $payloadValue('complaints_received', 'No') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="certificate_authorization">Certificate authorization decision</label>
                <select class="form-select" id="certificate_authorization" name="certificate_authorization">
                    <?php foreach (['Release of certificate authorised' => 'Release of certificate authorised', 'Hold certificate release' => 'Hold certificate release', 'Return for correction' => 'Return for correction'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>" <?= $payloadValue('certificate_authorization', 'Release of certificate authorised') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="authorization_date">Authorization date</label>
                <input class="form-control" type="date" id="authorization_date" name="authorization_date" value="<?= esc($payloadValue('authorization_date', date('Y-m-d'))) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="client_management_system_review">Review of client management system</label>
                <textarea class="form-control" id="client_management_system_review" name="client_management_system_review" rows="3"><?= esc($payloadValue('client_management_system_review', 'Audit file reviewed for conformity with applicable standard, scope, audit objectives and certification requirements.')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="outstanding_items">Outstanding items</label>
                <textarea class="form-control" id="outstanding_items" name="outstanding_items" rows="3"><?= esc($payloadValue('outstanding_items', 'No outstanding items preventing certification decision.')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <?php foreach ([
            'competency_confirmed' => 'Auditor competence confirmed',
            'duration_confirmed' => 'Audit duration confirmed',
            'application_confirmed' => 'Application and contract scope confirmed',
            'reports_confirmed' => 'Audit reports and evidence reviewed',
            'ncr_capa_confirmed' => 'NCR/CAPA closure confirmed',
            'scope_dates_confirmed' => 'Scope, issue date and expiry date confirmed',
            'impartiality_confirmed' => 'Impartiality and conflict check confirmed',
        ] as $field => $label): ?>
            <div class="col-md-6">
                <label class="border rounded p-2 w-100">
                    <input type="checkbox" name="<?= esc($field) ?>" value="1" <?= (int) ($review[$field] ?? 0) === 1 ? 'checked' : '' ?>>
                    <?= esc($label) ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="fw-semibold mb-2">Detailed technical review checklist</div>
        <?php foreach ($technicalChecklist as $group => $rows): ?>
            <div class="text-secondary small fw-semibold mt-3 mb-2"><?= esc($group) ?></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th style="width: 72px;">Ref</th>
                        <th style="width: 145px;">Action by</th>
                        <th>Requirement</th>
                        <th style="width: 130px;">Result</th>
                        <th style="width: 280px;">Evidence / remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $key = $row['key'];
                        $defaultResult = $key === 'admin_items' ? 'No' : 'Yes';
                        ?>
                        <tr>
                            <td>
                                <?= esc($row['ref']) ?>
                                <input type="hidden" name="technical_checklist[<?= esc($key, 'attr') ?>][group]" value="<?= esc($group, 'attr') ?>">
                                <input type="hidden" name="technical_checklist[<?= esc($key, 'attr') ?>][ref]" value="<?= esc($row['ref'], 'attr') ?>">
                                <input type="hidden" name="technical_checklist[<?= esc($key, 'attr') ?>][action_by]" value="<?= esc($row['action_by'], 'attr') ?>">
                                <input type="hidden" name="technical_checklist[<?= esc($key, 'attr') ?>][requirement]" value="<?= esc($row['requirement'], 'attr') ?>">
                            </td>
                            <td><?= esc($row['action_by']) ?></td>
                            <td><?= esc($row['requirement']) ?></td>
                            <td>
                                <select class="form-select form-select-sm" name="technical_checklist[<?= esc($key, 'attr') ?>][result]">
                                    <?php foreach (['Yes', 'No', 'N/A', 'Pending'] as $result): ?>
                                        <option value="<?= esc($result) ?>" <?= $rowValue($key, 'result', $defaultResult) === $result ? 'selected' : '' ?>><?= esc($result) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <textarea class="form-control form-control-sm" name="technical_checklist[<?= esc($key, 'attr') ?>][evidence]" rows="2"><?= esc($rowValue($key, 'evidence', $row['evidence'])) ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mb-3">
        <label class="form-label" for="review_notes">Review notes</label>
        <textarea class="form-control" id="review_notes" name="review_notes" rows="4"><?= esc($payloadValue('review_notes', 'Technical file reviewed against application, contract, audit programme, audit report, NCR/CAPA closure and applicable accreditation requirements.')) ?></textarea>
    </div>

    <div class="d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save technical review
        </button>
    </div>
</form>
<?= $this->endSection() ?>
