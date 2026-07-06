<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <form method="get" class="d-flex flex-wrap gap-2">
        <select class="form-select" name="standard_id">
            <option value="0">All standards</option>
            <?php foreach ($standards as $standard): ?>
                <option value="<?= esc($standard['id']) ?>" <?= (int) $filters['standard_id'] === (int) $standard['id'] ? 'selected' : '' ?>>
                    <?= esc($standard['code']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="content_type">
            <?php foreach (['' => 'All content', 'conformity_answer' => 'Conformity answers', 'objective_evidence' => 'Objective evidence', 'minor_nc' => 'Minor NC', 'major_nc' => 'Major NC', 'capa' => 'CAPA', 'auditor_comment' => 'Auditor comments', 'ofi' => 'OFI', 'positive_observation' => 'Positive observation'] as $value => $label): ?>
                <option value="<?= esc($value) ?>" <?= $filters['content_type'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="form-control" name="scope" value="<?= esc($filters['scope']) ?>" placeholder="Scope keyword">
        <select class="form-select" name="active">
            <option value="">All status</option>
            <option value="1" <?= $filters['active'] === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= $filters['active'] === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button class="btn btn-outline-primary" type="submit">
            <i class="fa-solid fa-filter me-1" aria-hidden="true"></i>
            Filter
        </button>
    </form>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= site_url('masters/clause-pool/export') ?>">
            <i class="fa-solid fa-file-export me-1" aria-hidden="true"></i>
            Export CSV
        </a>
        <a class="btn btn-primary" href="<?= site_url('masters/clause-pool/new') ?>">
            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>
            New template
        </a>
    </div>
</div>

<section class="panel mb-3">
    <form method="post" action="<?= site_url('masters/clause-pool/import') ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-7">
            <label class="form-label" for="pool_file">Import CSV</label>
            <input class="form-control" type="file" id="pool_file" name="pool_file" accept=".csv,text/csv">
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-primary" type="submit">
                <i class="fa-solid fa-file-import me-1" aria-hidden="true"></i>
                Import / update
            </button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Code</th>
                <th>Standard</th>
                <th>Clause</th>
                <th>Type</th>
                <th>Scope</th>
                <th>Stage</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($row['template_code']) ?></td>
                    <td><?= esc($row['standard_code'] ?? 'Any') ?></td>
                    <td><?= esc(trim((string) ($row['clause_number'] ?? '') . ' ' . (string) ($row['clause_title'] ?? ''))) ?></td>
                    <td><?= esc(str_replace('_', ' ', $row['content_type'])) ?></td>
                    <td><?= esc($row['scope_keyword'] ?? 'all') ?></td>
                    <td><?= esc($row['audit_stage'] ?? 'all') ?></td>
                    <td><?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('masters/clause-pool/' . $row['id'] . '/edit') ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="<?= site_url('masters/clause-pool/' . $row['id'] . '/deactivate') ?>" class="d-inline" onsubmit="return confirm('Deactivate this template?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
