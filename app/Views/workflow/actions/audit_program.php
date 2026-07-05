<?php
$value = static function (array $source, string $key, mixed $default = '') {
    return old($key, $source[$key] ?? $default);
};
$payloadValue = static function (string $key, mixed $default = '') use ($payload) {
    return old($key, $payload[$key] ?? $default);
};
$eventLabels = [
    'initial_stage1' => 'Stage 1',
    'initial_stage2' => 'Stage 2',
    'surveillance1' => 'Surveillance 1',
    'surveillance2' => 'Surveillance 2',
    'recertification' => 'Recertification',
];
$coverageRows = $payload['coverage'] ?? [];
$committeeRows = $payload['committee'] ?? [];
$ncRows = $payload['nc_summary'] ?? [];
?>

<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-program') ?>" class="panel">
    <?= csrf_field() ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Audit Program</div>
            <div class="text-secondary small">Three-year certification cycle with audit dates, clause coverage, audit committee and NC summary.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (! empty($program['id'])): ?>
                <a href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/audit_program') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
                    Generate PDF
                </a>
            <?php endif; ?>
            <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                Back
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label" for="program_number">Program number</label>
            <input class="form-control" id="program_number" value="<?= esc($program['program_number'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="document_number">Document no.</label>
            <input class="form-control" id="document_number" name="document_number" value="<?= esc($value($program, 'document_number', 'F 42')) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="revision_number">Revision no.</label>
            <input class="form-control" id="revision_number" name="revision_number" value="<?= esc($value($program, 'revision_number', '2')) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="issue_number">Issue no.</label>
            <input class="form-control" id="issue_number" name="issue_number" value="<?= esc($value($program, 'issue_number', '2')) ?>" required>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="document_date">Document date</label>
            <input class="form-control" type="date" id="document_date" name="document_date" value="<?= esc($value($program, 'document_date', '2022-05-15')) ?>" required>
        </div>
    </div>

    <h3 class="h6 mb-3">Client and audit information</h3>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label" for="client_reference">Client reference no.</label>
            <input class="form-control" id="client_reference" name="client_reference" value="<?= esc($payloadValue('client_reference')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="standards_text">Standard(s)</label>
            <input class="form-control" id="standards_text" name="standards_text" value="<?= esc($payloadValue('standards_text')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="category_subcategory"><?= esc($payloadValue('category_label', 'Category / sub-category')) ?></label>
            <input type="hidden" name="category_label" value="<?= esc($payloadValue('category_label', 'Category / sub-category')) ?>">
            <input class="form-control" id="category_subcategory" name="category_subcategory" value="<?= esc($payloadValue('category_subcategory')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="audit_language">Audit language</label>
            <input class="form-control" id="audit_language" name="audit_language" value="<?= esc($payloadValue('audit_language', 'English')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="audit_type">Audit type</label>
            <select class="form-select" id="audit_type" name="audit_type">
                <?php foreach (['Initial Certification', 'Surveillance-I', 'Surveillance-II', 'Recertification', 'Transition', 'Transfer', 'Single', 'Combined', 'Integrated'] as $type): ?>
                    <option value="<?= esc($type) ?>" <?= $payloadValue('audit_type', 'Initial Certification') === $type ? 'selected' : '' ?>><?= esc($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Program status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach (['planned', 'active', 'completed', 'cancelled'] as $status): ?>
                    <option value="<?= esc($status) ?>" <?= ($program['status'] ?? 'planned') === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="organization_name">Organization name</label>
            <input class="form-control" id="organization_name" name="organization_name" value="<?= esc($payloadValue('organization_name', $client['company'])) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="head_office_address">Head office address</label>
            <textarea class="form-control" id="head_office_address" name="head_office_address" rows="2"><?= esc($payloadValue('head_office_address', $client['address'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="site_addresses">Site address(es)</label>
            <textarea class="form-control" id="site_addresses" name="site_addresses" rows="2"><?= esc($payloadValue('site_addresses')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="scope">Scope of company</label>
            <textarea class="form-control" id="scope" name="scope" rows="2"><?= esc($payloadValue('scope', $client['scope'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="exclusions">Exclusion(s)</label>
            <input class="form-control" id="exclusions" name="exclusions" value="<?= esc($payloadValue('exclusions', 'None')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="employee_count">No. of employees</label>
            <input class="form-control" id="employee_count" name="employee_count" value="<?= esc($payloadValue('employee_count', $client['employee_count'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="shifts">No. of shifts</label>
            <input class="form-control" id="shifts" name="shifts" value="<?= esc($payloadValue('shifts', $client['shift_pattern'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="haccp_studies"><?= esc($payloadValue('process_label', 'Key audited processes')) ?></label>
            <input type="hidden" name="process_label" value="<?= esc($payloadValue('process_label', 'Key audited processes')) ?>">
            <input class="form-control" id="haccp_studies" name="haccp_studies" value="<?= esc($payloadValue('haccp_studies')) ?>">
        </div>
    </div>

    <h3 class="h6 mb-3">Certification cycle and audit duration</h3>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label" for="certificate_issue_date">Certificate issue date</label>
            <input class="form-control" type="date" id="certificate_issue_date" name="certificate_issue_date" value="<?= esc(old('certificate_issue_date', $program['certificate_issue_date'] ?? date('Y-m-d'))) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="surveillance_1_due_date">Surveillance 1 due date</label>
            <input class="form-control" type="date" id="surveillance_1_due_date" name="surveillance_1_due_date" value="<?= esc($payloadValue('surveillance_1_due_date', $program['surveillance_1_due_date'] ?? '')) ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="surveillance_2_due_date">Surveillance 2 due date</label>
            <input class="form-control" type="date" id="surveillance_2_due_date" name="surveillance_2_due_date" value="<?= esc($payloadValue('surveillance_2_due_date', $program['surveillance_2_due_date'] ?? '')) ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="certificate_expiry_date">Certificate expiry date</label>
            <input class="form-control" type="date" id="certificate_expiry_date" name="certificate_expiry_date" value="<?= esc($payloadValue('certificate_expiry_date', $program['certificate_expiry_date'] ?? '')) ?>" readonly>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="audit_duration_days">Total days</label>
            <input class="form-control" type="number" step="0.25" min="0" id="audit_duration_days" name="audit_duration_days" value="<?= esc($payloadValue('audit_duration_days')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="stage1_days">Stage 1 days</label>
            <input class="form-control" type="number" step="0.25" min="0" id="stage1_days" name="stage1_days" value="<?= esc($payloadValue('stage1_days', $program['stage1_days'] ?? '1.00')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="stage2_days">Stage 2 days</label>
            <input class="form-control" type="number" step="0.25" min="0" id="stage2_days" name="stage2_days" value="<?= esc($payloadValue('stage2_days', $program['stage2_days'] ?? '2.00')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="surveillance1_days">S1 days</label>
            <input class="form-control" type="number" step="0.25" min="0" id="surveillance1_days" name="surveillance1_days" value="<?= esc($payloadValue('surveillance1_days', '1.00')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="surveillance2_days">S2 days</label>
            <input class="form-control" type="number" step="0.25" min="0" id="surveillance2_days" name="surveillance2_days" value="<?= esc($payloadValue('surveillance2_days', '1.00')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="recertification_days">Recert days</label>
            <input class="form-control" type="number" step="0.25" min="0" id="recertification_days" name="recertification_days" value="<?= esc($payloadValue('recertification_days', '2.00')) ?>">
        </div>
    </div>

    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th>Audit</th>
                    <th>Audit no.</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Window start</th>
                    <th>Window end</th>
                    <th>Days</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $index => $event): ?>
                    <tr>
                        <td>
                            <?= esc($eventLabels[$event['event_type']] ?? $event['event_type']) ?>
                            <input type="hidden" name="event_id[<?= $index ?>]" value="<?= esc($event['id'] ?? '') ?>">
                            <input type="hidden" name="event_type[<?= $index ?>]" value="<?= esc($event['event_type']) ?>">
                        </td>
                        <td><input class="form-control form-control-sm" name="audit_number[<?= $index ?>]" value="<?= esc($event['audit_number'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" type="date" name="planned_start_date[<?= $index ?>]" value="<?= esc($event['planned_start_date'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" type="date" name="planned_end_date[<?= $index ?>]" value="<?= esc($event['planned_end_date'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" type="date" name="audit_window_start[<?= $index ?>]" value="<?= esc($event['audit_window_start'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" type="date" name="audit_window_end[<?= $index ?>]" value="<?= esc($event['audit_window_end'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" step="0.25" min="0" name="duration_days[<?= $index ?>]" value="<?= esc($event['duration_days'] ?? '') ?>"></td>
                        <td>
                            <select class="form-select form-select-sm" name="event_status[<?= $index ?>]">
                                <?php foreach (['planned', 'in_progress', 'completed', 'closed', 'cancelled'] as $status): ?>
                                    <option value="<?= esc($status) ?>" <?= ($event['status'] ?? 'planned') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="h6 mb-3">Processes / standard clauses</h3>
    <div class="table-responsive mb-4" style="max-height: 520px;">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 130px;">Standard</th>
                    <th style="min-width: 90px;">Clause no.</th>
                    <th style="min-width: 280px;">Clause title</th>
                    <th>Stage 1</th>
                    <th>Stage 2</th>
                    <th>S1</th>
                    <th>S2</th>
                    <th>Recert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coverageRows as $index => $row): ?>
                    <tr>
                        <td><input class="form-control form-control-sm" name="coverage_standard[<?= $index ?>]" value="<?= esc($row['standard'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" name="coverage_clause_number[<?= $index ?>]" value="<?= esc($row['clause_number'] ?? '') ?>"></td>
                        <td><input class="form-control form-control-sm" name="coverage_clause_title[<?= $index ?>]" value="<?= esc($row['clause_title'] ?? '') ?>"></td>
                        <?php foreach (['initial_stage1', 'initial_stage2', 'surveillance1', 'surveillance2', 'recertification'] as $stage): ?>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="coverage_<?= esc($stage) ?>[<?= $index ?>]" value="X" <?= ! empty($row[$stage]) ? 'checked' : '' ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="h6 mb-3">Audit committee</h3>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr><th>Role</th><th>Stage 1</th><th>Stage 2</th><th>S1</th><th>S2</th><th>Recert</th></tr>
            </thead>
            <tbody>
                <?php foreach ($committeeRows as $index => $row): ?>
                    <tr>
                        <td><input class="form-control form-control-sm" name="committee_role[<?= $index ?>]" value="<?= esc($row['role'] ?? '') ?>"></td>
                        <?php foreach (['initial_stage1', 'initial_stage2', 'surveillance1', 'surveillance2', 'recertification'] as $stage): ?>
                            <td><input class="form-control form-control-sm" name="committee_<?= esc($stage) ?>[<?= $index ?>]" value="<?= esc($row[$stage] ?? '') ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 class="h6 mb-3">Audit NC summary by stage</h3>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle">
            <thead class="table-light">
                <tr><th>Standard</th><th>Stage 1</th><th>Stage 2</th><th>S1</th><th>S2</th><th>Recert</th></tr>
            </thead>
            <tbody>
                <?php foreach ($ncRows as $index => $row): ?>
                    <tr>
                        <td><input class="form-control form-control-sm" name="nc_standard[<?= $index ?>]" value="<?= esc($row['standard'] ?? '') ?>"></td>
                        <?php foreach (['initial_stage1', 'initial_stage2', 'surveillance1', 'surveillance2', 'recertification'] as $stage): ?>
                            <td><input class="form-control form-control-sm" type="number" min="0" name="nc_<?= esc($stage) ?>[<?= $index ?>]" value="<?= esc($row[$stage] ?? 0) ?>"></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <label class="form-label" for="legend_notes">Legend / notes</label>
            <textarea class="form-control" id="legend_notes" name="legend_notes" rows="3"><?= esc($payloadValue('legend_notes')) ?></textarea>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="prepared_by_name">Prepared by</label>
            <input class="form-control" id="prepared_by_name" name="prepared_by_name" value="<?= esc($value($program, 'prepared_by_name')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="prepared_date">Prepared date</label>
            <input class="form-control" type="date" id="prepared_date" name="prepared_date" value="<?= esc($value($program, 'prepared_date', date('Y-m-d'))) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="approved_by_name">Approved by</label>
            <input class="form-control" id="approved_by_name" name="approved_by_name" value="<?= esc($value($program, 'approved_by_name')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="approved_date">Approved date</label>
            <input class="form-control" type="date" id="approved_date" name="approved_date" value="<?= esc($value($program, 'approved_date')) ?>">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <button class="btn btn-outline-secondary" type="button" onclick="window.print()">
            <i class="fa-solid fa-print me-1" aria-hidden="true"></i>
            Print
        </button>
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            <?= ! empty($program['id']) ? 'Save audit program' : 'Generate audit program' ?>
        </button>
    </div>
</form>

<script>
document.getElementById('certificate_issue_date')?.addEventListener('change', function () {
    const issue = new Date(this.value + 'T00:00:00');
    if (Number.isNaN(issue.getTime())) {
        return;
    }
    const formatDate = (date) => date.toISOString().slice(0, 10);
    const addYearsMinusDay = (years) => {
        const date = new Date(issue);
        date.setFullYear(date.getFullYear() + years);
        date.setDate(date.getDate() - 1);
        return formatDate(date);
    };
    document.getElementById('surveillance_1_due_date').value = addYearsMinusDay(1);
    document.getElementById('surveillance_2_due_date').value = addYearsMinusDay(2);
    document.getElementById('certificate_expiry_date').value = addYearsMinusDay(3);
});
</script>
<?= $this->endSection() ?>
