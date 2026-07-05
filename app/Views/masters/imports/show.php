<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="<?= site_url('masters/imports') ?>">
        <i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>
        Import history
    </a>
    <?php if ($batch['status'] === 'preview' && (int) $batch['valid_rows'] > 0): ?>
        <form method="post" action="<?= site_url('masters/imports/' . $batch['id'] . '/commit') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-check me-2" aria-hidden="true"></i>
                Commit valid rows
            </button>
        </form>
    <?php endif; ?>
    <?php if ($batch['status'] === 'imported'): ?>
        <form method="post" action="<?= site_url('masters/imports/' . $batch['id'] . '/rollback') ?>" onsubmit="return confirm('Rollback imported clients from this batch?');">
            <?= csrf_field() ?>
            <button class="btn btn-outline-danger" type="submit">
                <i class="fa-solid fa-rotate-left me-2" aria-hidden="true"></i>
                Rollback
            </button>
        </form>
    <?php endif; ?>
</div>

<section class="panel mb-3">
    <div class="row g-3">
        <div class="col-md-2"><div class="text-secondary small">Status</div><div class="fw-semibold"><?= esc($batch['status']) ?></div></div>
        <div class="col-md-2"><div class="text-secondary small">Total</div><div class="fw-semibold"><?= esc((string) $batch['total_rows']) ?></div></div>
        <div class="col-md-2"><div class="text-secondary small">Valid</div><div class="fw-semibold"><?= esc((string) $batch['valid_rows']) ?></div></div>
        <div class="col-md-2"><div class="text-secondary small">Invalid</div><div class="fw-semibold"><?= esc((string) $batch['invalid_rows']) ?></div></div>
        <div class="col-md-2"><div class="text-secondary small">Duplicates</div><div class="fw-semibold"><?= esc((string) $batch['duplicate_rows']) ?></div></div>
        <div class="col-md-2"><div class="text-secondary small">Imported at</div><div class="fw-semibold"><?= esc($batch['imported_at'] ?? '') ?></div></div>
    </div>
</section>

<section class="panel mb-3">
    <div class="panel-title">Detected column mapping</div>
    <div class="row g-2">
        <?php foreach ($mapping as $field => $column): ?>
            <div class="col-md-3"><span class="text-secondary"><?= esc($field) ?></span>: <strong><?= esc($column) ?></strong></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-title">Preview rows</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Row</th>
                <th>Status</th>
                <th>Company</th>
                <th>Email</th>
                <th>Certificate</th>
                <th>Duplicate</th>
                <th>Errors</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $normalized = json_decode($row['normalized_payload'] ?? '[]', true) ?: [];
                $errors = json_decode($row['validation_errors'] ?? '[]', true) ?: [];
                ?>
                <tr>
                    <td><?= esc((string) $row['row_number']) ?></td>
                    <td><?= esc($row['status']) ?></td>
                    <td><?= esc($normalized['company'] ?? '') ?></td>
                    <td><?= esc($normalized['email'] ?? '') ?></td>
                    <td><?= esc($normalized['certificate_number'] ?? '') ?></td>
                    <td><?= esc($row['duplicate_key'] ?? '') ?></td>
                    <td><?= esc(implode(' ', $errors)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
