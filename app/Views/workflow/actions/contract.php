<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$p = static fn (string $field): string => (string) old($field, $payload[$field] ?? '');
$contractValue = static fn (string $field, string $default = ''): string => (string) old($field, $contract[$field] ?? $default);
$section = static fn (string $title): string => '<div class="col-12"><div class="border-top pt-3 mt-2 fw-semibold">' . esc($title) . '</div></div>';
$input = static function (string $field, string $label, string $class = 'col-md-4', string $type = 'text') use ($p): string {
    return '<div class="' . esc($class, 'attr') . '"><label class="form-label" for="' . esc($field, 'attr') . '">' . esc($label) . '</label><input class="form-control" type="' . esc($type, 'attr') . '" id="' . esc($field, 'attr') . '" name="' . esc($field, 'attr') . '" value="' . esc($p($field), 'attr') . '"></div>';
};
$textarea = static function (string $field, string $label, int $rows = 3, string $class = 'col-12') use ($p): string {
    return '<div class="' . esc($class, 'attr') . '"><label class="form-label" for="' . esc($field, 'attr') . '">' . esc($label) . '</label><textarea class="form-control" id="' . esc($field, 'attr') . '" name="' . esc($field, 'attr') . '" rows="' . $rows . '">' . esc($p($field)) . '</textarea></div>';
};
?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/contract') ?>" class="panel">
    <?= csrf_field() ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Contract</div>
            <div class="text-secondary small">Contract terms, certification cycle obligations and signatures against proposal <?= esc($proposal['proposal_number']) ?>.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/contract') ?>" class="btn btn-outline-danger btn-sm">
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
            <label class="form-label" for="contract_number">Contract number</label>
            <input class="form-control" id="contract_number" value="<?= esc($contract['contract_number'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="document_number">Document No.</label>
            <input class="form-control" id="document_number" name="document_number" value="<?= esc($contractValue('document_number', 'F 27')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="revision_number">Revision No.</label>
            <input class="form-control" id="revision_number" name="revision_number" value="<?= esc($contractValue('revision_number', '2')) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="issue_number">Issue No.</label>
            <input class="form-control" id="issue_number" name="issue_number" value="<?= esc($contractValue('issue_number', '2')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="document_date">Document date</label>
            <input class="form-control" type="date" id="document_date" name="document_date" value="<?= esc($contractValue('document_date', '2022-05-15')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['draft' => 'Draft', 'sent' => 'Sent to client', 'signed' => 'Signed', 'approved' => 'Approved', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($contract['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="signed_at">Signed at</label>
            <input class="form-control" type="datetime-local" id="signed_at" name="signed_at" value="<?= esc(old('signed_at', isset($contract['signed_at']) && $contract['signed_at'] ? str_replace(' ', 'T', substr($contract['signed_at'], 0, 16)) : '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="signed_by_name">Signed by</label>
            <input class="form-control" id="signed_by_name" name="signed_by_name" value="<?= esc(old('signed_by_name', $contract['signed_by_name'] ?? '')) ?>">
        </div>

        <?= $section('Client and certification details') ?>
        <div class="col-md-6">
            <label class="form-label">Company / organisation</label>
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
        <?= $input('number_of_locations', 'Number of locations / sites', 'col-md-3', 'number') ?>
        <div class="col-md-3">
            <label class="form-label">Employees</label>
            <input class="form-control" value="<?= esc($client['employee_count'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">Proposal total</label>
            <input class="form-control" value="<?= esc(($proposal['currency'] ?? 'SAR') . ' ' . number_format((float) ($proposal['grand_total'] ?? 0), 2)) ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">Proposal date</label>
            <input class="form-control" value="<?= esc($proposal['proposal_date'] ?? '') ?>" readonly>
        </div>

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

        <?= $section('Contract terms') ?>
        <?= $textarea('certification_process_obligations', 'Certification process and obligations', 5) ?>
        <?= $textarea('payment_terms', 'Payment terms', 5) ?>
        <?= $textarea('certification_audit_includes', 'Certification audit includes', 4, 'col-md-6') ?>
        <?= $textarea('surveillance_audit_includes', 'Surveillance audit includes', 4, 'col-md-6') ?>
        <?= $textarea('vat_invoice_terms', 'VAT and invoice terms', 4) ?>

        <?= $section('Audit activities and additional service costs') ?>
        <?= $input('additional_a4_copy_fee', 'Additional A4 copy') ?>
        <?= $input('certificate_reissue_fee', 'Certificate reissue') ?>
        <?= $input('extraordinary_audit_1_fee', 'Extraordinary audit 1') ?>
        <?= $input('extraordinary_audit_2_fee', 'Extraordinary audit 2') ?>
        <?= $textarea('stage1_activity', 'Stage 1 activity', 4, 'col-md-6') ?>
        <?= $textarea('stage2_activity', 'Stage 2 activity', 4, 'col-md-6') ?>
        <?= $textarea('certificate_issuance', 'Issuance of certificate', 3, 'col-md-6') ?>
        <?= $textarea('surveillance_activity', 'Surveillance audit activity', 3, 'col-md-6') ?>
        <?= $textarea('audit_time_reference', 'Audit time reference', 3) ?>
        <?= $textarea('important_note', 'Important note / acceptance terms', 5) ?>
        <?= $input('contact_line', 'Contact line') ?>

        <?= $section('Signatures') ?>
        <div class="col-md-3">
            <label class="form-label" for="qsi_signatory_name">On behalf of QSI-Cert</label>
            <input class="form-control" id="qsi_signatory_name" name="qsi_signatory_name" value="<?= esc($contractValue('qsi_signatory_name')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="qsi_signatory_date">QSI signature date</label>
            <input class="form-control" type="date" id="qsi_signatory_date" name="qsi_signatory_date" value="<?= esc($contractValue('qsi_signatory_date')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="client_signatory_name">On behalf of Client</label>
            <input class="form-control" id="client_signatory_name" name="client_signatory_name" value="<?= esc($contractValue('client_signatory_name')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="client_signatory_date">Client signature date</label>
            <input class="form-control" type="date" id="client_signatory_date" name="client_signatory_date" value="<?= esc($contractValue('client_signatory_date')) ?>">
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save contract
        </button>
    </div>
</form>
<?= $this->endSection() ?>
