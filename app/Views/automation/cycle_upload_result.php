<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="panel mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <div class="panel-title mb-1">Batch upload result</div>
            <div class="text-secondary"><?= esc((string) $result['total']) ?> row(s) processed.</div>
        </div>
        <a class="btn btn-outline-secondary" href="<?= site_url('automation/cycle-generator') ?>">
            <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
            Back to Cycle Builder
        </a>
    </div>
</section>

<section class="panel mb-3">
    <div class="panel-title">Prepared files</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th>Row</th><th>Client</th><th>Client ID</th><th>Status</th><th class="text-end">Open</th></tr></thead>
            <tbody>
            <?php foreach ($result['prepared'] as $row): ?>
                <tr>
                    <td><?= esc((string) $row['row']) ?></td>
                    <td><?= esc($row['client_name']) ?></td>
                    <td><?= esc((string) $row['client_id']) ?></td>
                    <td><?= esc($row['message']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $row['client_id']) ?>">Open</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['prepared'] === []): ?>
                <tr><td colspan="5" class="text-secondary">No files were prepared.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-title">Rows requiring correction</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th>Row</th><th>Client</th><th>Message</th></tr></thead>
            <tbody>
            <?php foreach ($result['failed'] as $row): ?>
                <tr>
                    <td><?= esc((string) $row['row']) ?></td>
                    <td><?= esc($row['client_name']) ?></td>
                    <td><?= esc($row['message']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['failed'] === []): ?>
                <tr><td colspan="3" class="text-secondary">No correction required.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
