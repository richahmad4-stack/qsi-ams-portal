<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="panel mb-3">
    <div class="panel-title">Batch upload</div>
    <form method="post" action="<?= site_url('automation/cycle-generator/upload') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row g-3 align-items-end">
            <div class="col-md-7">
                <label class="form-label" for="cycle_file">CSV or Excel file</label>
                <input class="form-control" id="cycle_file" name="cycle_file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">
                    <i class="fa-solid fa-file-arrow-up me-1" aria-hidden="true"></i>
                    Upload batch
                </button>
                <a class="btn btn-outline-secondary" href="<?= site_url('automation/cycle-generator/template') ?>">
                    <i class="fa-solid fa-table me-1" aria-hidden="true"></i>
                    Download template
                </a>
            </div>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-title">Basic client and cycle information</div>
    <form method="post" action="<?= site_url('automation/cycle-generator/preview') ?>">
        <?= csrf_field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="client_name">Client name</label>
                <input class="form-control" id="client_name" name="client_name" required maxlength="180" value="<?= old('client_name') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contact_person">Contact person</label>
                <input class="form-control" id="contact_person" name="contact_person" maxlength="180" value="<?= old('contact_person') ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="client_address">Client address</label>
                <textarea class="form-control" id="client_address" name="client_address" rows="2"><?= old('client_address') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="designation">Designation</label>
                <input class="form-control" id="designation" name="designation" maxlength="180" value="<?= old('designation', 'Management Representative') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" maxlength="190" value="<?= old('email') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" maxlength="50" value="<?= old('phone') ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="scope">Certification scope</label>
                <textarea class="form-control" id="scope" name="scope" rows="3" required><?= old('scope') ?></textarea>
            </div>
        </div>

        <hr>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="standard_ids">Standard(s)</label>
                <select class="form-select" id="standard_ids" name="standard_ids[]" multiple required size="7">
                    <?php foreach ($standards as $standard): ?>
                        <option value="<?= esc($standard['id']) ?>"><?= esc($standard['code'] . ' - ' . $standard['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="iaf_code_id">IAF code</label>
                        <select class="form-select" id="iaf_code_id" name="iaf_code_id">
                            <option value="">Not applicable</option>
                            <?php foreach ($iafCodes as $row): ?>
                                <option value="<?= esc($row['id']) ?>"><?= esc($row['code'] . ' - ' . $row['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="food_category_id">Food category</label>
                        <select class="form-select" id="food_category_id" name="food_category_id">
                            <option value="">Not applicable</option>
                            <?php foreach ($foodCategories as $row): ?>
                                <option value="<?= esc($row['id']) ?>"><?= esc($row['code'] . ' - ' . $row['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="medical_category_id">Medical category</label>
                        <select class="form-select" id="medical_category_id" name="medical_category_id">
                            <option value="">Not applicable</option>
                            <?php foreach ($medicalCategories as $row): ?>
                                <option value="<?= esc($row['id']) ?>"><?= esc($row['code'] . ' - ' . $row['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="employee_count">Employees</label>
                        <input class="form-control" id="employee_count" name="employee_count" type="number" min="1" value="<?= old('employee_count', '30') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="number_of_sites">Sites</label>
                        <input class="form-control" id="number_of_sites" name="number_of_sites" type="number" min="1" value="<?= old('number_of_sites', '1') ?>">
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="generation_mode">File preparation mode</label>
                <select class="form-select" id="generation_mode" name="generation_mode">
                    <option value="standard">Prepare complete workflow pack</option>
                    <option value="historical_confirmed">Prepare completed historical file from supplied records</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certificate_issue_date">Certificate issue date</label>
                <input class="form-control" id="certificate_issue_date" name="certificate_issue_date" type="date" required value="<?= old('certificate_issue_date', date('Y-m-d')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certificate_expiry_date">Certificate expiry date</label>
                <input class="form-control" id="certificate_expiry_date" name="certificate_expiry_date" type="date" value="<?= old('certificate_expiry_date') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="certification_status">Certification status</label>
                <select class="form-select" id="certification_status" name="certification_status">
                    <?php foreach (['certified', 'active', 'suspended', 'withdrawn', 'expired'] as $status): ?>
                        <option value="<?= esc($status) ?>"><?= esc(ucwords($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="risk_category">Risk</label>
                <select class="form-select" id="risk_category" name="risk_category">
                    <?php foreach (['low', 'medium', 'high'] as $risk): ?>
                        <option value="<?= esc($risk) ?>" <?= $risk === 'medium' ? 'selected' : '' ?>><?= esc(ucwords($risk)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="current_cycle_stage">Current cycle stage</label>
                <select class="form-select" id="current_cycle_stage" name="current_cycle_stage">
                    <?php foreach (['auto', 'initial certification', 'surveillance 1', 'surveillance 2', 'recertification', 'expired'] as $stage): ?>
                        <option value="<?= esc($stage) ?>"><?= esc(ucwords($stage)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ncr_mode">NCR/CAPA setup</label>
                <select class="form-select" id="ncr_mode" name="ncr_mode">
                    <option value="sample_minor">Prepare 4 minor NCR/CAPA records</option>
                    <option value="none">No NCRs</option>
                    <option value="major">Include major NCR sample</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="special_notes">Special notes</label>
                <textarea class="form-control" id="special_notes" name="special_notes" rows="1"><?= old('special_notes') ?></textarea>
            </div>
        </div>

        <hr>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="application_review_notes">Application review basis</label>
                <textarea class="form-control" id="application_review_notes" name="application_review_notes" rows="3" placeholder="Scope accepted, resources/competence available, exclusions, sites, shifts, legal/regulatory notes"><?= old('application_review_notes') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="audit_plan_notes">Audit plan / process notes</label>
                <textarea class="form-control" id="audit_plan_notes" name="audit_plan_notes" rows="3" placeholder="Processes/units, shifts, site constraints, lunch timing, parallel auditor coverage"><?= old('audit_plan_notes') ?></textarea>
            </div>
            <div class="col-md-12">
                <label class="form-label" for="audit_evidence_summary">Actual audit evidence summary</label>
                <textarea class="form-control" id="audit_evidence_summary" name="audit_evidence_summary" rows="4" placeholder="Enter sampled records/interviews/observations when importing an already completed file. Leave blank when the auditor will complete evidence during execution."><?= old('audit_evidence_summary') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="technical_review_notes">Technical review notes</label>
                <textarea class="form-control" id="technical_review_notes" name="technical_review_notes" rows="3" placeholder="Actual technical reviewer conclusion, file completeness, NCR/CAPA closure, audit duration/scope confirmation"><?= old('technical_review_notes') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="decision_basis">Decision basis</label>
                <textarea class="form-control" id="decision_basis" name="decision_basis" rows="3" placeholder="Actual certification decision basis and GM/final approval basis"><?= old('decision_basis') ?></textarea>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>
                Preview cycle
            </button>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
