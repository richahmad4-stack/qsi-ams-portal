<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $hideEditActions = in_array('compliance_auditor', (array) session()->get('role_codes'), true); ?>
<section class="panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1"><?= esc($pageTitle) ?></div>
            <div class="text-secondary small"><?= esc(count($rows)) ?> record(s)</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()">
                <i class="fa-solid fa-print me-1" aria-hidden="true"></i>
                Print
            </button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('dashboard') ?>">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                Dashboard
            </a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <?php foreach ($columns as $column): ?>
                    <th><?= esc($column) ?></th>
                <?php endforeach; ?>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row['cells'] as $cell): ?>
                        <td><?= esc((string) $cell) ?></td>
                    <?php endforeach; ?>
                    <td class="text-end">
                        <?php if (! empty($row['view'])): ?>
                            <a class="btn btn-outline-primary btn-sm" href="<?= esc($row['view']) ?>">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                View
                            </a>
                        <?php endif; ?>
                        <?php if (! $hideEditActions && ! empty($row['edit'])): ?>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= esc($row['edit']) ?>">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                Edit
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()">
                            <i class="fa-solid fa-print" aria-hidden="true"></i>
                            Print
                        </button>
                        <?php if (! empty($row['pdf'])): ?>
                            <a class="btn btn-outline-danger btn-sm" href="<?= esc($row['pdf']) ?>">
                                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                                PDF
                            </a>
                        <?php endif; ?>
                        <?php if (! empty($row['printable_pdf'])): ?>
                            <a class="btn btn-primary btn-sm" href="<?= esc($row['printable_pdf']) ?>">
                                <i class="fa-solid fa-print" aria-hidden="true"></i>
                                Printable PDF
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= esc(count($columns) + 1) ?>" class="text-secondary">No records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
