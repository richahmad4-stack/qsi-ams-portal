<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="panel mb-3">
    <div class="panel-title">Upload legacy clients</div>
    <form method="post" action="<?= site_url('masters/imports') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-8">
            <label class="form-label" for="legacy_file">CSV file</label>
            <input id="legacy_file" name="legacy_file" type="file" accept=".csv,text/csv" class="form-control" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-magnifying-glass-chart me-2" aria-hidden="true"></i>
                Preview import
            </button>
        </div>
    </form>
    <div class="text-secondary small mt-2">Export Excel files as CSV before import. The preview checks required fields and existing duplicates before committing.</div>
</section>

<section class="panel">
    <div class="panel-title">Import history</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>File</th>
                <th>Status</th>
                <th>Total</th>
                <th>Valid</th>
                <th>Invalid</th>
                <th>Duplicates</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($batches as $batch): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($batch['original_filename']) ?></td>
                    <td><?= esc($batch['status']) ?></td>
                    <td><?= esc((string) $batch['total_rows']) ?></td>
                    <td><?= esc((string) $batch['valid_rows']) ?></td>
                    <td><?= esc((string) $batch['invalid_rows']) ?></td>
                    <td><?= esc((string) $batch['duplicate_rows']) ?></td>
                    <td><?= esc($batch['created_at']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('masters/imports/' . $batch['id']) ?>">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
