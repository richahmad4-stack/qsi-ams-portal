<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$value = static fn (string $name, array $source = null): string => (string) old($name, ($source ?? $payload)[$name] ?? '');
$reviewValue = static fn (string $name): string => (string) old($name, $review[$name] ?? '');
$input = static function (string $name, string $label, string $type = 'text', string $class = 'col-md-4') use ($value): string {
    return '<div class="' . esc($class, 'attr') . '"><label class="form-label" for="' . esc($name, 'attr') . '">' . esc($label) . '</label><input class="form-control" type="' . esc($type, 'attr') . '" id="' . esc($name, 'attr') . '" name="' . esc($name, 'attr') . '" value="' . esc($value($name), 'attr') . '"></div>';
};
$textarea = static function (string $name, string $label, int $rows = 3, string $class = 'col-12') use ($value): string {
    return '<div class="' . esc($class, 'attr') . '"><label class="form-label" for="' . esc($name, 'attr') . '">' . esc($label) . '</label><textarea class="form-control" id="' . esc($name, 'attr') . '" name="' . esc($name, 'attr') . '" rows="' . $rows . '">' . esc($value($name)) . '</textarea></div>';
};
$select = static function (string $name, string $label, array $options, string $class = 'col-md-4') use ($value): string {
    $html = '<div class="' . esc($class, 'attr') . '"><label class="form-label" for="' . esc($name, 'attr') . '">' . esc($label) . '</label><select class="form-select" id="' . esc($name, 'attr') . '" name="' . esc($name, 'attr') . '">';
    $current = $value($name);
    foreach ($options as $optionValue => $optionLabel) {
        $html .= '<option value="' . esc((string) $optionValue, 'attr') . '"' . ($current === (string) $optionValue ? ' selected' : '') . '>' . esc((string) $optionLabel) . '</option>';
    }
    return $html . '</select></div>';
};
$section = static function (string $title): string {
    return '<div class="col-12"><div class="border-top pt-3 mt-2"><div class="fw-semibold">' . esc($title) . '</div></div></div>';
};
?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/review') ?>" class="panel">
    <?= csrf_field() ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Application Review Checklist Report</div>
            <div class="text-secondary small">F 28 review information before proposal, contract and audit program.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/application_review') ?>" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
                PDF
            </a>
            <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                Back
            </a>
        </div>
    </div>

    <div class="row g-3">
        <?= $section('Document control') ?>
        <div class="col-md-3">
            <label class="form-label" for="document_number">Document No.</label>
            <input class="form-control" id="document_number" name="document_number" value="<?= esc($reviewValue('document_number') ?: 'F 28') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="revision_number">Revision No.</label>
            <input class="form-control" id="revision_number" name="revision_number" value="<?= esc($reviewValue('revision_number') ?: '4') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="issue_number">Issue No.</label>
            <input class="form-control" id="issue_number" name="issue_number" value="<?= esc($reviewValue('issue_number') ?: '2') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="document_date">Date</label>
            <input class="form-control" type="date" id="document_date" name="document_date" value="<?= esc($reviewValue('document_date') ?: '2025-02-01') ?>">
        </div>

        <?= $section('1. Client Detail') ?>
        <?= $input('application_id', 'Application ID') ?>
        <div class="col-md-8">
            <label class="form-label">Client</label>
            <input class="form-control" value="<?= esc($client['company']) ?>" disabled>
        </div>
        <div class="col-12">
            <label class="form-label">Scope (Site, Organizational Units, Activities & Processes)</label>
            <textarea class="form-control" rows="2" disabled><?= esc($client['scope'] ?? '') ?></textarea>
        </div>
        <?= $input('communication_language', 'Communication Language') ?>
        <?= $input('client_type', 'Client Type') ?>
        <?= $input('effective_employees', 'Effective Number of Employees', 'number') ?>
        <?= $textarea('management_system_complexity', 'Complexity of Client Management System', 2) ?>
        <?= $input('haccp_plans_processes', 'Number of HACCP Plans / Processes', 'number') ?>
        <?= $input('shifts_auditing', 'Shifts Auditing') ?>
        <?= $select('seasonal_activity', 'Any Seasonal Activity', ['No' => 'No', 'Yes' => 'Yes']) ?>
        <?= $textarea('legal_requirements', 'Applicable Legal and Regulatory Requirement') ?>
        <?= $textarea('product_process_risks', 'Risks associated with products, processes or activities') ?>
        <?= $select('risk_classification', 'Risk Classification', ['' => 'Select', 'Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High']) ?>

        <?= $section('2. Description of the activities of the client scope') ?>
        <?= $textarea('technical_issues', '1a. Analysis of technical issues arising from the scope') ?>
        <?= $textarea('safety_requirements', '1b. Safety condition requirements') ?>
        <?= $textarea('technological_regulatory_context', '1c. Technological and regulatory context') ?>

        <?= $section('3. Checklist to define client scope') ?>
        <?= $select('design_development', '2a. Any design or development undertaken', ['No' => 'No', 'Yes' => 'Yes']) ?>
        <?= $select('installation_commissioning', '2b. Any installation, commissioning or onsite activities', ['No' => 'No', 'Yes' => 'Yes']) ?>
        <?= $select('standard_exclusions', '2c. Is the company disclaiming any parts of the standard?', ['No' => 'No', 'Yes' => 'Yes']) ?>
        <?= $textarea('outsourced_activity_details', '2d. Outsourced activity details', 2) ?>

        <?= $section('4. Recertification') ?>
        <?= $input('incident', '3d. Incident') ?>
        <?= $select('scope_change', '3e. Change in the scope', ['No' => 'No', 'Yes' => 'Yes']) ?>
        <?= $select('employee_change', '3f. Change in number of effective employees', ['No' => 'No', 'Yes' => 'Yes']) ?>

        <?= $section('5. Multiple or Temporary Sites') ?>
        <?= $select('common_management_system', 'Common management system on multiple or temporary sites', ['No' => 'No', 'Yes' => 'Yes']) ?>

        <?= $section('6. Effective No. of Employees and 7. Accounts') ?>
        <?= $textarea('employee_justification', 'Effective No. of Employees justification', 2, 'col-md-8') ?>
        <?= $select('invoice_established', 'Invoice date and amount established', ['No' => 'No', 'Yes' => 'Yes'], 'col-md-4') ?>

        <?= $section('8. Audit Scheme and 9. Competence Requirement') ?>
        <?= $input('standards_text', 'Standards') ?>
        <?= $select('certification_route', 'Certification Route', ['unaccredited' => 'Unaccredited', 'accredited' => 'Accredited'], 'col-md-4') ?>
        <?= $select('accreditation_body', 'Accreditation Body (if accredited)', ['' => 'Select', 'IAS' => 'IAS', 'SAAC' => 'SAAC'], 'col-md-4') ?>
        <?= $input('initial_audit_type', 'Initial Audit Type') ?>
        <?= $textarea('audit_category', 'IAF / Food Chain Category matched to scope', 2, 'col-12') ?>
        <?= $textarea('competence_requirements', 'Competence Requirements for Standard', 2) ?>

        <?= $section('10. Audit Man Days Calculation') ?>
        <?php foreach ([
            'days_allotted' => 'No. of Days Allotted',
            'stage1_days' => 'Stage 1 Document Review',
            'stage2_days' => 'Stage 2 On-site Implementation',
            'surveillance1_days' => 'Surveillance 1',
            'surveillance2_days' => 'Surveillance 2',
            'recertification_days' => 'Recertification',
        ] as $field => $label): ?>
            <div class="col-md-2">
                <label class="form-label" for="<?= esc($field) ?>"><?= esc($label) ?></label>
                <input class="form-control bg-light" type="number" step="0.25" id="<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc($value($field)) ?>" readonly>
            </div>
        <?php endforeach; ?>
        <?= $input('reduction_days_allotted', 'Reduction: Days Allotted', 'number', 'col-md-2') ?>
        <?= $input('reduction_stage1_days', 'Reduction: Stage 1', 'number', 'col-md-2') ?>
        <?= $input('reduction_stage2_days', 'Reduction: Stage 2', 'number', 'col-md-2') ?>
        <?= $input('reduction_surveillance1_days', 'Reduction: S1', 'number', 'col-md-2') ?>
        <?= $input('reduction_surveillance2_days', 'Reduction: S2', 'number', 'col-md-2') ?>
        <?= $input('reduction_recertification_days', 'Reduction: Recertification', 'number', 'col-md-2') ?>
        <?= $input('reduction_percentage', 'Reduction Percentage', 'number') ?>
        <div class="col-12">
            <label class="form-label" for="calculation_basis">Calculation basis</label>
            <textarea class="form-control bg-light" id="calculation_basis" name="calculation_basis" rows="3" readonly><?= esc($value('calculation_basis')) ?></textarea>
        </div>

        <?= $section('11. Reduction') ?>
        <?= $input('no_design', 'No design') ?>
        <?= $input('single_activity_process', 'Single activity process') ?>
        <?= $input('prior_knowledge', 'Prior knowledge of organization') ?>
        <?= $input('shift_work', 'Shift work') ?>
        <?= $input('maturity_of_system', 'Maturity of system') ?>
        <?= $input('very_small_site', 'Very small site for no. of employees') ?>
        <?= $input('registered_scheme', 'Client registered with another 3rd party scheme') ?>
        <?= $input('repetitive_work', 'Repetitive work') ?>
        <?= $input('low_risk_product', 'Low risk product') ?>
        <?= $input('others_reduction', 'Others') ?>
        <?= $input('no_offsite_work', 'No offsite work') ?>

        <?= $section('12. Reviewer Comments and Application Status') ?>
        <div class="col-md-4">
            <label class="form-label" for="completeness_status">Completeness</label>
            <select class="form-select" id="completeness_status" name="completeness_status" required>
                <?php foreach (['pending' => 'Pending', 'complete' => 'Complete', 'incomplete' => 'Incomplete'] as $optionValue => $optionLabel): ?>
                    <option value="<?= esc($optionValue) ?>" <?= $reviewValue('completeness_status') === $optionValue ? 'selected' : '' ?>><?= esc($optionLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?= $select('application_status', 'Application Status', ['Accepted' => 'Accepted', 'Rejected' => 'Rejected', 'On Hold' => 'On Hold'], 'col-md-4') ?>
        <div class="col-md-4">
            <label class="form-label" for="status">Workflow decision</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['draft' => 'Draft / pending', 'tm_approved' => 'Technical Manager approved', 'tm_rejected' => 'Technical Manager rejected', 'qm_approved' => 'Quality Manager approved', 'qm_rejected' => 'Quality Manager rejected'] as $optionValue => $optionLabel): ?>
                    <option value="<?= esc($optionValue) ?>" <?= $reviewValue('status') === $optionValue ? 'selected' : '' ?>><?= esc($optionLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?= $textarea('reviewer_comments', 'Reviewer Comments / Remarks') ?>
        <div class="col-md-6">
            <label class="form-label" for="technical_reviewer_name">Technical Reviewer Name</label>
            <input class="form-control" id="technical_reviewer_name" name="technical_reviewer_name" value="<?= esc($reviewValue('technical_reviewer_name')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="technical_review_date">Technical Review Date</label>
            <input class="form-control" type="date" id="technical_review_date" name="technical_review_date" value="<?= esc($reviewValue('technical_review_date') ?: date('Y-m-d')) ?>">
        </div>
        <input type="hidden" name="review_notes" value="<?= esc($value('reviewer_comments'), 'attr') ?>">
        <input type="hidden" name="recommendation" value="<?= esc($value('application_status'), 'attr') ?>">

        <?= $section('13. Quality Manager Comments and Application Status') ?>
        <div class="col-md-4">
            <label class="form-label" for="quality_manager_status">Application Approval Status</label>
            <select class="form-select" id="quality_manager_status" name="quality_manager_status">
                <?php foreach (['' => 'Select', 'Approved' => 'Approved', 'Rejected' => 'Rejected', 'On Hold' => 'On Hold'] as $optionValue => $optionLabel): ?>
                    <option value="<?= esc($optionValue) ?>" <?= $reviewValue('quality_manager_status') === $optionValue ? 'selected' : '' ?>><?= esc($optionLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="quality_manager_name">Quality Manager Name</label>
            <input class="form-control" id="quality_manager_name" name="quality_manager_name" value="<?= esc($reviewValue('quality_manager_name')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="quality_manager_date">Quality Manager Date</label>
            <input class="form-control" type="date" id="quality_manager_date" name="quality_manager_date" value="<?= esc($reviewValue('quality_manager_date')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="quality_manager_comments">Quality Manager Comments / Remarks</label>
            <textarea class="form-control" id="quality_manager_comments" name="quality_manager_comments" rows="3"><?= esc($reviewValue('quality_manager_comments')) ?></textarea>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save application review
        </button>
    </div>
</form>
<script>
(() => {
    const managementBands = [
        [5, 1.5], [10, 2], [15, 2.5], [25, 3], [45, 4], [65, 5], [85, 6],
        [125, 7], [175, 8], [275, 9], [425, 10], [625, 11], [875, 12],
        [1175, 13], [1550, 14], [2025, 15], [2675, 16], [3450, 17],
        [4350, 18], [5450, 19], [6800, 20], [8500, 21], [10700, 22],
    ];
    const standardsFromDb = <?= json_encode(array_values(array_map(static fn (array $row): string => (string) ($row['standard_code'] ?? ''), $standards ?? [])), JSON_THROW_ON_ERROR) ?>;
    const siteCount = <?= (int) ($client['number_of_sites'] ?? 1) ?>;
    const field = (name) => document.querySelector(`[name="${name}"]`);
    const numberValue = (name) => {
        const value = parseFloat(field(name)?.value || '0');
        return Number.isFinite(value) ? value : 0;
    };
    const roundHalf = (value) => Math.round(value * 2) / 2;
    const managementBase = (employees) => {
        for (const [upper, days] of managementBands) {
            if (employees <= upper) return days;
        }
        return 22 + Math.ceil((employees - 10700) / 2500);
    };
    const foodBase = (employees, haccpPlans) => {
        let days = 2.5;
        if (employees <= 20) days = 2.5;
        else if (employees <= 50) days = 3;
        else if (employees <= 100) days = 3.5;
        else if (employees <= 250) days = 4;
        else if (employees <= 500) days = 4.5;
        else days = 5 + Math.ceil((employees - 500) / 500) * 0.5;
        if (haccpPlans > 5) days += Math.min(2, (haccpPlans - 5) * 0.25);
        return roundHalf(days);
    };
    const setValue = (name, value) => {
        const input = field(name);
        if (input) input.value = Number(value).toFixed(2);
    };
    const calculate = () => {
        const employees = Math.max(1, Math.round(numberValue('effective_employees')));
        const haccpPlans = Math.max(0, Math.round(numberValue('haccp_plans_processes')));
        const textStandards = (field('standards_text')?.value || '').split(/[,;]+/).map((item) => item.trim()).filter(Boolean);
        const sourceStandards = standardsFromDb.length ? standardsFromDb : textStandards;
        const standards = [...new Set(sourceStandards.map((item) => item.toUpperCase()).filter(Boolean))];
        const standardDays = standards.length ? standards.map((standard) => standard.includes('HACCP') || standard.includes('ISO 22000') ? foodBase(employees, haccpPlans) : managementBase(employees)) : [managementBase(employees)];
        standardDays.sort((a, b) => b - a);
        const base = standardDays[0] || 3;
        const integratedAddition = standardDays.slice(1).reduce((sum, days) => sum + days * 0.20, 0);
        let factor = 1;
        const factors = [];
        const risk = (field('risk_classification')?.value || '').toLowerCase();
        if (risk === 'high') {
            factor += 0.10;
            factors.push('High risk +10%');
        } else if (risk === 'low') {
            factor -= 0.10;
            factors.push('Low risk -10%');
        }
        const shifts = (field('shifts_auditing')?.value || '').trim().toLowerCase();
        if (shifts && !['one', '1', 'single', 'no', 'n/a', 'na'].includes(shifts)) {
            factor += 0.10;
            factors.push('Multiple shifts +10%');
        }
        if (siteCount > 1) {
            const siteFactor = Math.min(0.30, (siteCount - 1) * 0.05);
            factor += siteFactor;
            factors.push(`Multiple sites +${Math.round(siteFactor * 100)}%`);
        }
        const reduction = Math.min(30, Math.max(0, numberValue('reduction_percentage')));
        if (reduction > 0) {
            factor -= reduction / 100;
            factors.push(`Approved reduction -${reduction.toFixed(2)}%`);
        }
        const total = roundHalf(Math.max(1, (base + integratedAddition) * Math.max(0.70, factor)));
        const stage1 = total <= 3 ? 1 : roundHalf(Math.min(2, Math.max(1, total * 0.25)));
        const stage2 = roundHalf(Math.max(0.5, total - stage1));
        const surveillance = roundHalf(Math.max(1, total / 3));
        const recertification = roundHalf(Math.max(1, total * 2 / 3));
        setValue('days_allotted', total);
        setValue('stage1_days', stage1);
        setValue('stage2_days', stage2);
        setValue('surveillance1_days', surveillance);
        setValue('surveillance2_days', surveillance);
        setValue('recertification_days', recertification);
        const basis = field('calculation_basis');
        if (basis) {
            basis.value = `Controlled QSI audit-duration rule set aligned with ISO/IEC 17021-1 competence/impartiality controls and IAF MD 5 / IAF MD 11 audit-time principles. Food safety values use scheme-specific complexity factors and must be verified against the current licensed scheme rules before accreditation use.\nEffective employees: ${employees}\nStandard basis: ${standardDays.map((days, index) => `${standards[index] || 'GENERAL'}: ${days.toFixed(2)} days`).join('; ')}\nAdjustments: ${factors.length ? factors.join('; ') : 'none'}\nFormula reference: Initial audit days = round to nearest 0.5 day of [(highest selected standard base days + 20% of each additional standard base days) x adjustment factor]. Stage 1 = 1 day when total <= 3 days, otherwise 25% of total capped at 2 days. Stage 2 = total initial audit days - Stage 1. Surveillance = one-third of initial audit days unless scheme rules require more. Recertification = two-thirds of initial audit days unless scheme rules require more.\nCalculated result: Initial audit ${total.toFixed(2)} days, split Stage 1 ${stage1.toFixed(2)} / Stage 2 ${stage2.toFixed(2)}; Surveillance 1 ${surveillance.toFixed(2)}; Surveillance 2 ${surveillance.toFixed(2)}; Recertification ${recertification.toFixed(2)}.`;
        }
    };

    ['effective_employees', 'haccp_plans_processes', 'shifts_auditing', 'risk_classification', 'standards_text', 'reduction_percentage'].forEach((name) => {
        field(name)?.addEventListener('input', calculate);
        field(name)?.addEventListener('change', calculate);
    });
    const route = field('certification_route');
    const body = field('accreditation_body');
    const syncAccreditationBody = () => {
        if (!route || !body) return;
        const haccpOnly = standardsFromDb.length === 1 && String(standardsFromDb[0]).toUpperCase().includes('HACCP');
        if (haccpOnly) {
            route.value = 'unaccredited';
        }
        if (haccpOnly || route.value !== 'accredited') {
            body.value = '';
            body.disabled = true;
            return;
        }
        body.disabled = false;
    };
    route?.addEventListener('change', syncAccreditationBody);
    syncAccreditationBody();
    calculate();
})();
</script>
<?= $this->endSection() ?>
