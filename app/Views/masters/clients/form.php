<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$value = static fn (string $field) => old($field, $client[$field] ?? '');
$selectedStandards = array_map('intval', old('standard_ids', $selectedStandardIds ?? []));
$statuses = ['enquiry', 'application_review', 'proposal', 'contracted', 'planned', 'certified', 'suspended', 'withdrawn', 'expired'];
$risks = ['', 'low', 'medium', 'high'];
?>

<form method="post" action="<?= esc($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <section class="panel mb-3">
        <div class="panel-title">Company profile</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="company">Company</label>
                <input id="company" name="company" class="form-control" value="<?= esc($value('company')) ?>" required maxlength="220">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="legal_name">Legal name</label>
                <input id="legal_name" name="legal_name" class="form-control" value="<?= esc($value('legal_name')) ?>" maxlength="220">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="country">Country</label>
                <input id="country" name="country" class="form-control" value="<?= esc($value('country')) ?>" maxlength="120">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="city">City</label>
                <input id="city" name="city" class="form-control" value="<?= esc($value('city')) ?>" maxlength="120">
            </div>
            <div class="col-12">
                <label class="form-label" for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="2"><?= esc($value('address')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="scope">Scope</label>
                <textarea id="scope" name="scope" class="form-control" rows="3"><?= esc($value('scope')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="standard_ids">Requested standards</label>
                <select id="standard_ids" name="standard_ids[]" class="form-select" multiple size="6">
                    <?php foreach ($standards as $standard): ?>
                        <?php $selected = in_array((int) $standard['id'], $selectedStandards, true); ?>
                        <option value="<?= esc((string) $standard['id']) ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= esc($standard['code'] . ' - ' . $standard['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Hold Ctrl to select more than one standard.</div>
            </div>
        </div>
    </section>

    <section class="panel mb-3">
        <div class="panel-title">Contact and operations</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="contact_person">Contact person</label>
                <input id="contact_person" name="contact_person" class="form-control" value="<?= esc($value('contact_person')) ?>" maxlength="180">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="designation">Designation</label>
                <input id="designation" name="designation" class="form-control" value="<?= esc($value('designation')) ?>" maxlength="120">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email" class="form-control" value="<?= esc($value('email')) ?>" maxlength="190">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="phone">Phone</label>
                <input id="phone" name="phone" class="form-control" value="<?= esc($value('phone')) ?>" maxlength="50">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="website">Website</label>
                <input id="website" name="website" class="form-control" value="<?= esc($value('website')) ?>" maxlength="220">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="client_logo">Client logo for certificate</label>
                <input id="client_logo" name="client_logo" type="file" accept="image/png,image/jpeg" class="form-control">
                <div class="form-text">Optional PNG or JPG, up to 2 MB.</div>
                <?php if (! empty($client['id']) && ! empty($client['client_logo_path'])): ?>
                    <div class="mt-2">
                        <img src="<?= site_url('masters/clients/' . $client['id'] . '/logo') ?>" alt="Current client logo" style="max-width: 140px; max-height: 54px; object-fit: contain;">
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="number_of_sites">Number of sites</label>
                <input id="number_of_sites" name="number_of_sites" type="number" min="1" class="form-control" value="<?= esc((string) $value('number_of_sites')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="employee_count">Employees</label>
                <input id="employee_count" name="employee_count" type="number" min="0" class="form-control" value="<?= esc((string) $value('employee_count')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="permanent_employees">Permanent</label>
                <input id="permanent_employees" name="permanent_employees" type="number" min="0" class="form-control" value="<?= esc((string) $value('permanent_employees')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="temporary_employees">Temporary</label>
                <input id="temporary_employees" name="temporary_employees" type="number" min="0" class="form-control" value="<?= esc((string) $value('temporary_employees')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="shift_pattern">Shift pattern</label>
                <input id="shift_pattern" name="shift_pattern" class="form-control" value="<?= esc($value('shift_pattern')) ?>" maxlength="180">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="seasonal_operations">Seasonal operations</label>
                <input id="seasonal_operations" name="seasonal_operations" class="form-control" value="<?= esc($value('seasonal_operations')) ?>" maxlength="180">
            </div>
        </div>
    </section>

    <section class="panel mb-3">
        <div class="panel-title">Certification</div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="certification_status">Status</label>
                <select id="certification_status" name="certification_status" class="form-select">
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= esc($status) ?>" <?= $value('certification_status') === $status ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="risk_category">Risk category</label>
                <select id="risk_category" name="risk_category" class="form-select">
                    <?php foreach ($risks as $risk): ?>
                        <option value="<?= esc($risk) ?>" <?= $value('risk_category') === $risk ? 'selected' : '' ?>><?= esc($risk === '' ? 'Not set' : ucfirst($risk)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certificate_number">Certificate number</label>
                <input id="certificate_number" name="certificate_number" class="form-control" value="<?= esc($value('certificate_number')) ?>" maxlength="80">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="initial_certification_date">Initial date</label>
                <input id="initial_certification_date" name="initial_certification_date" type="date" class="form-control" value="<?= esc($value('initial_certification_date')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certificate_issue_date">Issue date</label>
                <input id="certificate_issue_date" name="certificate_issue_date" type="date" class="form-control" value="<?= esc($value('certificate_issue_date')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certificate_expiry_date">Expiry date</label>
                <input id="certificate_expiry_date" name="certificate_expiry_date" type="date" class="form-control" value="<?= esc($value('certificate_expiry_date')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?= esc($value('notes')) ?></textarea>
            </div>
        </div>
    </section>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>
            Save
        </button>
        <a class="btn btn-outline-secondary" href="<?= site_url('masters/clients') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
