<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="<?= site_url('masters/personnel') ?>">
        <i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>
        Personnel
    </a>
    <a class="btn btn-primary" href="<?= site_url('masters/personnel/' . $person['id'] . '/edit') ?>">
        <i class="fa-solid fa-pen me-2" aria-hidden="true"></i>
        Edit profile
    </a>
</div>

<section class="panel mb-3">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="text-secondary small">Affiliation</div>
            <div class="fw-semibold">
                <span class="badge <?= ($person['personnel_type'] ?? '') === 'client_representative' ? 'text-bg-info' : 'text-bg-primary' ?>">
                    <?= ($person['personnel_type'] ?? '') === 'client_representative' ? 'Client' : 'Certification Body' ?>
                </span>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Type</div>
            <div class="fw-semibold"><?= esc(str_replace('_', ' ', $person['personnel_type'])) ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Linked client</div>
            <div class="fw-semibold">
                <?php if (($person['personnel_type'] ?? '') === 'client_representative'): ?>
                    <?= esc($person['linked_client_company'] ?? 'Not linked') ?>
                <?php else: ?>
                    <span class="text-secondary">Not applicable</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Approval</div>
            <div class="fw-semibold"><?= esc(str_replace('_', ' ', $person['approval_status'])) ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Email</div>
            <div class="fw-semibold"><?= esc($person['email'] ?? '') ?></div>
        </div>
        <div class="col-md-3">
            <div class="text-secondary small">Phone</div>
            <div class="fw-semibold"><?= esc($person['phone'] ?? '') ?></div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-title">Competency matrix</div>
    <form method="post" action="<?= site_url('masters/personnel/' . $person['id'] . '/competencies') ?>" class="row g-2 mb-3">
        <?= csrf_field() ?>
        <div class="col-md-3">
            <select name="standard_id" class="form-select">
                <option value="">Standard</option>
                <?php foreach ($standards as $standard): ?>
                    <option value="<?= esc((string) $standard['id']) ?>"><?= esc($standard['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="iaf_code_id" class="form-select">
                <option value="">IAF code</option>
                <?php foreach ($iafCodes as $code): ?>
                    <option value="<?= esc((string) $code['id']) ?>"><?= esc($code['code']) ?> - <?= esc($code['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="food_chain_category_id" class="form-select">
                <option value="">Food category</option>
                <?php foreach ($foodCategories as $category): ?>
                    <option value="<?= esc((string) $category['id']) ?>"><?= esc($category['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="medical_device_category_id" class="form-select">
                <option value="">Medical category</option>
                <?php foreach ($medicalCategories as $category): ?>
                    <option value="<?= esc((string) $category['id']) ?>"><?= esc($category['code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input name="competency_type" class="form-control" placeholder="Competency type" required maxlength="80">
        </div>
        <div class="col-md-3">
            <select name="approval_status" class="form-select" required>
                <?php foreach (['pending', 'approved', 'suspended', 'expired'] as $status): ?>
                    <option value="<?= esc($status) ?>"><?= esc(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input name="valid_from" type="date" class="form-control">
        </div>
        <div class="col-md-3">
            <input name="valid_until" type="date" class="form-control">
        </div>
        <div class="col-12">
            <textarea name="evidence_notes" class="form-control" rows="2" placeholder="Evidence notes"></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-sm btn-primary" type="submit">
                <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>
                Add competency
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Standard</th>
                <th>Codes</th>
                <th>Type</th>
                <th>Status</th>
                <th>Valid from</th>
                <th>Valid until</th>
                <th>Evidence</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($competencies as $competency): ?>
                <tr>
                    <td><?= esc($competency['standard_code'] ?? '') ?></td>
                    <td><?= esc(trim(($competency['iaf_code'] ?? '') . ' ' . ($competency['food_code'] ?? '') . ' ' . ($competency['medical_code'] ?? ''))) ?></td>
                    <td><?= esc($competency['competency_type']) ?></td>
                    <td><?= esc($competency['approval_status']) ?></td>
                    <td><?= esc($competency['valid_from'] ?? '') ?></td>
                    <td><?= esc($competency['valid_until'] ?? '') ?></td>
                    <td><?= esc($competency['evidence_notes'] ?? '') ?></td>
                    <td class="text-end">
                        <form method="post" action="<?= site_url('masters/personnel/' . $person['id'] . '/competencies/' . $competency['id'] . '/delete') ?>" onsubmit="return confirm('Remove this competency?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
