<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$decisionPayload = ! empty($decision['decision_payload']) ? json_decode((string) $decision['decision_payload'], true) : [];
if (! is_array($decisionPayload)) {
    $decisionPayload = [];
}

$storedDecisionRows = [];
foreach (($decisionPayload['checklist_rows'] ?? []) as $row) {
    if (is_array($row) && isset($row['key'])) {
        $storedDecisionRows[(string) $row['key']] = $row;
    }
}

$payloadValue = static fn (string $key, string $default = ''): string => (string) old($key, $decisionPayload[$key] ?? $default);
$rowValue = static fn (string $key, string $field, string $default = ''): string => (string) old(
    'decision_checklist.' . $key . '.' . $field,
    $storedDecisionRows[$key][$field] ?? $default
);
$standardCodes = implode(', ', array_filter(array_column($standards, 'standard_code')));
$defaultDeclaration = 'I confirm that all applicable ISO, IAF MD, sector scheme, ISO/IEC 17021-1 and accreditation body specific certification requirements have been verified prior to certificate issuance.';

$decisionChecklist = [
    'General certification verification' => [
        ['key' => 'correct_standards', 'ref' => '2.1', 'requirement' => 'Correct standard(s) identified on certificate.', 'evidence' => 'Standards verified against application, audit report and certificate draft.'],
        ['key' => 'audit_type_correct', 'ref' => '2.2', 'requirement' => 'Audit type correctly stated.', 'evidence' => 'Audit event type verified.'],
        ['key' => 'issue_date_validity', 'ref' => '2.3', 'requirement' => 'Certificate issue date is within accreditation validity.', 'evidence' => 'Issue date checked against accreditation status.'],
    ],
    'Scope and organizational structure verification' => [
        ['key' => 'scope_consistent', 'ref' => '3.1', 'requirement' => 'Scope is consistent with audit report.', 'evidence' => 'Scope wording compared with approved audit report.'],
        ['key' => 'organization_structure', 'ref' => '3.2', 'requirement' => 'Organizational structure verified.', 'evidence' => 'Client structure and sites reviewed.'],
        ['key' => 'central_function', 'ref' => '3.3', 'requirement' => 'Central function audited where applicable.', 'evidence' => 'Central function coverage checked.'],
        ['key' => 'nace_verified', 'ref' => '3.4', 'requirement' => 'NACE / category code verified.', 'evidence' => 'NACE/category checked against scope and competence.'],
    ],
    'Risk classification and audit time verification' => [
        ['key' => 'sector_risk', 'ref' => '4.1', 'requirement' => 'Sector risk determined.', 'evidence' => 'Risk classification reviewed.'],
        ['key' => 'duration_calculated', 'ref' => '4.2', 'requirement' => 'Audit duration calculated.', 'evidence' => 'Audit man-day calculation reviewed.'],
        ['key' => 'sampling_justified', 'ref' => '4.3', 'requirement' => 'Sampling justified where applicable.', 'evidence' => 'Sampling basis checked.'],
        ['key' => 'audit_time_justified', 'ref' => '4.4', 'requirement' => 'Audit time justified.', 'evidence' => 'Audit time justification reviewed.'],
    ],
    'Accreditation coverage verification' => [
        ['key' => 'ab_coverage_valid', 'ref' => '5.1', 'requirement' => 'Accreditation body coverage is valid.', 'evidence' => 'Accreditation coverage checked.'],
        ['key' => 'accredited_scope_covers_sector', 'ref' => '5.2', 'requirement' => 'Accredited scope covers the sector.', 'evidence' => 'Accreditation schedule checked against scope.'],
        ['key' => 'no_suspension', 'ref' => '5.3', 'requirement' => 'No suspension impacts certificate issuance.', 'evidence' => 'Suspension status checked.'],
    ],
    'Accreditation mark and statement control' => [
        ['key' => 'correct_mark', 'ref' => '6.1', 'requirement' => 'Correct accreditation mark is used where applicable.', 'evidence' => 'Certificate mark checked.'],
        ['key' => 'mark_size_placement', 'ref' => '6.2', 'requirement' => 'Mark size and placement are compliant.', 'evidence' => 'Mark placement checked against control rules.'],
        ['key' => 'scope_statement', 'ref' => '6.3', 'requirement' => 'Scope statement is accurate.', 'evidence' => 'Certificate scope statement reviewed.'],
    ],
    'Pre-issue control checks' => [
        ['key' => 'scheme_standard', 'ref' => 'PI.1', 'requirement' => 'Scheme standard verified.', 'evidence' => 'Scheme and standard checked.'],
        ['key' => 'scope_verified', 'ref' => 'PI.2', 'requirement' => 'Scope verified.', 'evidence' => 'Scope checked against audit report and contract.'],
        ['key' => 'category_verified', 'ref' => 'PI.3', 'requirement' => 'NACE code / category verified.', 'evidence' => 'Code/category verified.'],
        ['key' => 'ab_scope', 'ref' => 'PI.4', 'requirement' => 'Accreditation body scope verified.', 'evidence' => 'AB scope checked.'],
        ['key' => 'audit_time', 'ref' => 'PI.5', 'requirement' => 'Audit time verified.', 'evidence' => 'Audit duration checked.'],
        ['key' => 'accreditation_mark', 'ref' => 'PI.6', 'requirement' => 'Accreditation mark / statement verified.', 'evidence' => 'Mark and statement checked.'],
        ['key' => 'technical_review', 'ref' => 'PI.7', 'requirement' => 'Technical review completed and acceptable.', 'evidence' => 'Technical review status checked.'],
        ['key' => 'decision_complete', 'ref' => 'PI.8', 'requirement' => 'Decision completed by competent independent decision maker.', 'evidence' => 'Decision maker and decision record checked.'],
        ['key' => 'certsearch', 'ref' => 'PI.9', 'requirement' => 'CertSearch / registry requirement addressed where applicable.', 'evidence' => 'Registry requirement checked.'],
    ],
];
?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/decision') ?>" class="panel">
    <?= csrf_field() ?>
    <?php if (! empty($eventId)): ?>
        <input type="hidden" name="event_id" value="<?= esc($eventId) ?>">
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Certification decision and GM approval</div>
            <div class="text-secondary small">Record the independent decision, then final management approval.</div>
        </div>
        <div class="d-flex gap-2">
            <?php if (! empty($technicalReview['audit_event_id'])): ?>
                <a href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $technicalReview['audit_event_id'] . '/documents/decision_report') ?>" class="btn btn-outline-danger btn-sm">
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

    <div class="alert alert-info">
        Technical review status: <strong><?= esc($technicalReview['status']) ?></strong>
        <?php if (($technicalReview['recommendation'] ?? '') !== ''): ?>
            | Recommendation: <strong><?= esc(str_replace('_', ' ', $technicalReview['recommendation'])) ?></strong>
        <?php endif; ?>
        | Open NCRs: <strong><?= esc($openNcrCount) ?></strong>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Audit stage</div>
                <div class="metric-value fs-5"><?= esc(str_replace('_', ' ', (string) ($reviewEvent['event_type'] ?? 'Audit'))) ?></div>
                <div class="text-secondary small"><?= esc($reviewEvent['audit_number'] ?? '') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">NCR closure</div>
                <div class="metric-value"><?= esc(max(0, (int) $totalNcrCount - (int) $openNcrCount)) ?>/<?= esc($totalNcrCount) ?></div>
                <div class="text-secondary small">Closed against total NCRs</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Standards</div>
                <div class="metric-value fs-5"><?= esc(implode(', ', array_filter(array_column($standards, 'standard_code')))) ?></div>
                <div class="text-secondary small">Separate certificates issued after approval</div>
            </div>
        </div>
    </div>

    <div class="border rounded p-3 mb-3">
        <div class="fw-semibold mb-2">Pre-issue general information</div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="application_id">Application ID</label>
                <input class="form-control" id="application_id" name="application_id" value="<?= esc($payloadValue('application_id', 'APP-' . str_pad((string) $client['id'], 3, '0', STR_PAD_LEFT))) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="standard_category_nace">Standard category / NACE</label>
                <input class="form-control" id="standard_category_nace" name="standard_category_nace" value="<?= esc($payloadValue('standard_category_nace', $standardCodes)) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certificate_number">Certificate no.</label>
                <input class="form-control" id="certificate_number" name="certificate_number" value="<?= esc($payloadValue('certificate_number', $certificates[0]['certificate_number'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certificate_decision_date">Certificate decision date</label>
                <input class="form-control" type="date" id="certificate_decision_date" name="certificate_decision_date" value="<?= esc($payloadValue('certificate_decision_date', date('Y-m-d'))) ?>">
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Client name</div>
                <div class="fw-semibold"><?= esc($client['company']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Audit type</div>
                <div class="fw-semibold"><?= esc(str_replace('_', ' ', (string) ($reviewEvent['event_type'] ?? 'Audit'))) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Audit dates</div>
                <div class="fw-semibold"><?= esc(trim((string) (($reviewEvent['planned_start_date'] ?? '') . ' to ' . ($reviewEvent['planned_end_date'] ?? '')), ' to')) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="decision_maker_personnel_id">Decision maker</label>
            <select class="form-select" id="decision_maker_personnel_id" name="decision_maker_personnel_id" required>
                <?php foreach ($decisionMakers as $person): ?>
                    <option value="<?= esc($person['id']) ?>" <?= (int) ($decision['decision_maker_personnel_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>>
                        <?= esc($person['full_name'] . ' - ' . str_replace('_', ' ', $person['personnel_type'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="decision">Decision</label>
            <select class="form-select" id="decision" name="decision" required>
                <?php foreach (['granted' => 'Certification granted', 'not_granted' => 'Certification not granted', 'deferred' => 'Deferred'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($decision['decision'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Decision status</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['pending' => 'Pending', 'decided' => 'Decided', 'approved' => 'Approved'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($decision['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="electronic_signature">Electronic signature</label>
            <input class="form-control" id="electronic_signature" name="electronic_signature" value="<?= esc(old('electronic_signature', $decision['electronic_signature'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">General Manager approval</label>
            <label class="border rounded p-2 w-100">
                <input type="checkbox" name="gm_approved" value="1" <?= ($decision['status'] ?? '') === 'gm_approved' ? 'checked' : '' ?>>
                Final approval granted by current logged-in user
            </label>
        </div>
        <div class="col-12">
            <label class="form-label" for="reason">Decision reason</label>
            <textarea class="form-control" id="reason" name="reason" rows="4"><?= esc(old('reason', $decision['reason'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="gm_approval_notes">GM approval notes</label>
            <textarea class="form-control" id="gm_approval_notes" name="gm_approval_notes" rows="3"><?= esc(old('gm_approval_notes', $decision['gm_approval_notes'] ?? '')) ?></textarea>
        </div>
    </div>

    <div class="border rounded p-3 mt-3">
        <div class="fw-semibold mb-2">Pre-issue accreditation and marks checklist</div>
        <?php foreach ($decisionChecklist as $group => $rows): ?>
            <div class="text-secondary small fw-semibold mt-3 mb-2"><?= esc($group) ?></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th style="width: 72px;">Ref</th>
                        <th>Requirement</th>
                        <th style="width: 130px;">Result</th>
                        <th style="width: 300px;">Evidence / remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $key = $row['key']; ?>
                        <tr>
                            <td>
                                <?= esc($row['ref']) ?>
                                <input type="hidden" name="decision_checklist[<?= esc($key, 'attr') ?>][group]" value="<?= esc($group, 'attr') ?>">
                                <input type="hidden" name="decision_checklist[<?= esc($key, 'attr') ?>][ref]" value="<?= esc($row['ref'], 'attr') ?>">
                                <input type="hidden" name="decision_checklist[<?= esc($key, 'attr') ?>][requirement]" value="<?= esc($row['requirement'], 'attr') ?>">
                            </td>
                            <td><?= esc($row['requirement']) ?></td>
                            <td>
                                <select class="form-select form-select-sm" name="decision_checklist[<?= esc($key, 'attr') ?>][result]">
                                    <?php foreach (['Verified', 'Not verified', 'N/A', 'Pending'] as $result): ?>
                                        <option value="<?= esc($result) ?>" <?= $rowValue($key, 'result', 'Verified') === $result ? 'selected' : '' ?>><?= esc($result) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <textarea class="form-control form-control-sm" name="decision_checklist[<?= esc($key, 'attr') ?>][evidence]" rows="2"><?= esc($rowValue($key, 'evidence', $row['evidence'])) ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="border rounded p-3 mt-3">
        <div class="fw-semibold mb-2">Certification decision declaration</div>
        <label class="border rounded p-2 w-100 mb-3">
            <input type="checkbox" name="declaration_confirmed" value="1" <?= (int) ($decisionPayload['declaration_confirmed'] ?? 1) === 1 ? 'checked' : '' ?>>
            Declaration confirmed by decision maker
        </label>
        <div class="mb-3">
            <label class="form-label" for="declaration_text">Declaration text</label>
            <textarea class="form-control" id="declaration_text" name="declaration_text" rows="3"><?= esc($payloadValue('declaration_text', $defaultDeclaration)) ?></textarea>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="technical_reviewer_name">Technical reviewer name</label>
                <input class="form-control" id="technical_reviewer_name" name="technical_reviewer_name" value="<?= esc($payloadValue('technical_reviewer_name', $technicalReview['reviewer_name'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="technical_reviewer_date">Technical reviewer date</label>
                <input class="form-control" type="date" id="technical_reviewer_date" name="technical_reviewer_date" value="<?= esc($payloadValue('technical_reviewer_date', substr((string) ($technicalReview['reviewed_at'] ?? date('Y-m-d')), 0, 10))) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certification_decision_maker_name">Decision maker name</label>
                <input class="form-control" id="certification_decision_maker_name" name="certification_decision_maker_name" value="<?= esc($payloadValue('certification_decision_maker_name', $decision['decision_maker_name'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certification_decision_maker_date">Decision maker date</label>
                <input class="form-control" type="date" id="certification_decision_maker_date" name="certification_decision_maker_date" value="<?= esc($payloadValue('certification_decision_maker_date', substr((string) ($decision['decided_at'] ?? date('Y-m-d')), 0, 10))) ?>">
            </div>
        </div>
    </div>

    <div class="border rounded p-3 mt-3">
        <div class="fw-semibold mb-2">Decision output</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-secondary small">Decision date</div>
                <div><?= esc($decision['decided_at'] ?? 'Not recorded') ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">GM approved at</div>
                <div><?= esc($decision['gm_approved_at'] ?? 'Not recorded') ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Certificates issued</div>
                <div><?= esc(count($certificates)) ?></div>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-stamp me-1" aria-hidden="true"></i>
            Save decision
        </button>
    </div>
</form>
<?= $this->endSection() ?>
