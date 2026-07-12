<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$p = static fn (string $field): string => (string) old($field, $payload[$field] ?? '');
$proposalValue = static fn (string $field, string $default = ''): string => (string) old($field, $proposal[$field] ?? $default);
$section = static fn (string $title): string => '<div class="col-12"><div class="border-top pt-3 mt-2 fw-semibold">' . esc($title) . '</div></div>';
$input = static function (string $field, string $label, string $class = 'col-md-4', string $type = 'text') use ($p): string {
    return '<div class="' . esc($class, 'attr') . '"><label class="form-label" for="' . esc($field, 'attr') . '">' . esc($label) . '</label><input class="form-control" type="' . esc($type, 'attr') . '" id="' . esc($field, 'attr') . '" name="' . esc($field, 'attr') . '" value="' . esc($p($field), 'attr') . '"></div>';
};
$textarea = static function (string $field, string $label, int $rows = 3, string $class = 'col-12') use ($p): string {
    return '<div class="' . esc($class, 'attr') . '"><label class="form-label" for="' . esc($field, 'attr') . '">' . esc($label) . '</label><textarea class="form-control" id="' . esc($field, 'attr') . '" name="' . esc($field, 'attr') . '" rows="' . $rows . '">' . esc($p($field)) . '</textarea></div>';
};
?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/proposal') ?>" class="panel">
    <?= csrf_field() ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Proposal</div>
            <div class="text-secondary small">Commercial offer, scope, audit scheme, fees and terms.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/proposal') ?>" class="btn btn-outline-danger btn-sm">
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
        <?= $section('Proposal control') ?>
        <div class="col-md-3">
            <label class="form-label" for="proposal_number">Proposal number</label>
            <input class="form-control" id="proposal_number" value="<?= esc($proposal['proposal_number'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="proposal_date">Proposal date</label>
            <input class="form-control" type="date" id="proposal_date" name="proposal_date" value="<?= esc($proposalValue('proposal_date', date('Y-m-d'))) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="client_reference">Client reference</label>
            <input class="form-control" id="client_reference" name="client_reference" value="<?= esc($proposalValue('client_reference')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['draft' => 'Draft', 'sent' => 'Sent to client', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'approved' => 'Approved'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($proposal['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?= $section('Client detail') ?>
        <div class="col-md-6">
            <label class="form-label">Name of company or organisation</label>
            <input class="form-control" value="<?= esc($client['company']) ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label">Address</label>
            <input class="form-control" value="<?= esc($client['address'] ?? '') ?>" readonly>
        </div>
        <?= $input('legal_documentation', 'Legal documentation / CR / licence') ?>
        <?= $input('management_representative', 'Management representative') ?>
        <?= $input('phone_fax', 'Phone / Fax') ?>
        <div class="col-12">
            <label class="form-label">Scope of certification</label>
            <textarea class="form-control" rows="2" readonly><?= esc($client['scope'] ?? '') ?></textarea>
        </div>
        <div class="col-md-3">
            <label class="form-label">Employees</label>
            <input class="form-control" value="<?= esc($client['employee_count'] ?? '') ?>" readonly>
        </div>
        <?= $input('number_of_locations', 'Number of locations / sites', 'col-md-3', 'number') ?>
        <div class="col-md-3">
            <label class="form-label" for="currency">Currency</label>
            <input class="form-control" maxlength="3" id="currency" name="currency" value="<?= esc($proposalValue('currency', 'SAR')) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="valid_until">Valid until</label>
            <input class="form-control" type="date" id="valid_until" name="valid_until" value="<?= esc($proposalValue('valid_until')) ?>">
        </div>
        <?= $textarea('intro_message', 'Introductory message', 3) ?>

        <?= $section('Audit scheme and duration') ?>
        <?= $input('standards_text', 'Standard(s)') ?>
        <?= $input('certification_route', 'Certification route', 'col-md-3') ?>
        <?= $input('accreditation_body', 'Accreditation body', 'col-md-3') ?>
        <?= $input('initial_audit_type', 'Initial audit type') ?>
        <?php foreach ([
            'total_audit_days' => 'Total audit days',
            'stage1_days' => 'Stage 1',
            'stage2_days' => 'Stage 2',
            'surveillance1_days' => 'Surveillance 1',
            'surveillance2_days' => 'Surveillance 2',
            'recertification_days' => 'Recertification',
        ] as $field => $label): ?>
            <?= $input($field, $label, 'col-md-2', 'number') ?>
        <?php endforeach; ?>

        <?= $section('Fees detail') ?>
        <?php foreach ([
            'certification_fee' => 'Initial certification audit',
            'surveillance1_fee' => 'Surveillance audit 1',
            'surveillance2_fee' => 'Surveillance audit 2',
            'training_fee' => 'Additional services',
            'travel_fee' => 'Travel costs',
            'accommodation_fee' => 'Accommodation costs',
            'discount_amount' => 'Discount',
            'vat_percent' => 'VAT %',
        ] as $field => $label): ?>
            <div class="col-md-3">
                <label class="form-label" for="<?= esc($field) ?>"><?= esc($label) ?></label>
                <input class="form-control money-field" type="number" step="0.01" min="0" id="<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc(old($field, $proposal[$field] ?? '0.00')) ?>">
            </div>
        <?php endforeach; ?>
        <div class="col-md-3">
            <label class="form-label">VAT amount</label>
            <input class="form-control" value="<?= esc(number_format((float) ($proposal['vat_amount'] ?? 0), 2)) ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">Grand total</label>
            <input class="form-control" value="<?= esc(number_format((float) ($proposal['grand_total'] ?? 0), 2)) ?>" readonly>
        </div>

        <?= $section('Terms and included services') ?>
        <?= $textarea('certification_process_obligations', 'Certification process and obligations', 5) ?>
        <?= $textarea('payment_terms', 'Payment terms', 5) ?>
        <?= $textarea('certification_audit_includes', 'Certification audit includes', 4, 'col-md-6') ?>
        <?= $textarea('surveillance_audit_includes', 'Surveillance audit includes', 4, 'col-md-6') ?>

        <?= $section('Additional service costs and audit activities') ?>
        <?= $input('additional_a4_copy_fee', 'Additional A4 copy') ?>
        <?= $input('certificate_reissue_fee', 'Certificate reissue') ?>
        <?= $input('extraordinary_audit_1_fee', 'Extraordinary audit 1') ?>
        <?= $input('extraordinary_audit_2_fee', 'Extraordinary audit 2') ?>
        <?= $textarea('vat_invoice_terms', 'VAT and invoice terms', 4) ?>
        <?= $textarea('stage1_activity', 'Stage 1 activity', 4, 'col-md-6') ?>
        <?= $textarea('stage2_activity', 'Stage 2 activity', 4, 'col-md-6') ?>
        <?= $textarea('certificate_issuance', 'Issuance of certificate', 3, 'col-md-6') ?>
        <?= $textarea('surveillance_activity', 'Surveillance audit activity', 3, 'col-md-6') ?>
        <?= $textarea('audit_time_reference', 'Audit time reference', 3) ?>
        <?= $textarea('additional_services', 'Additional services / special notes', 3) ?>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save proposal
        </button>
    </div>
</form>
<?= $this->endSection() ?>
