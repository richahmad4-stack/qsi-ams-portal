<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-primary" href="<?= site_url('masters/references/' . $type . '/new') ?>">
        <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>
        New reference
    </a>
</div>

<section class="panel">
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Code</th>
                <th>Title</th>
                <?php if (in_array('risk_level', $config['fields'], true)): ?><th>Risk</th><?php endif; ?>
                <?php if (in_array('description', $config['fields'], true)): ?><th>Description</th><?php endif; ?>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($record['code']) ?></td>
                    <td><?= esc($record['title']) ?></td>
                    <?php if (in_array('risk_level', $config['fields'], true)): ?><td><?= esc($record['risk_level'] ?? '') ?></td><?php endif; ?>
                    <?php if (in_array('description', $config['fields'], true)): ?><td><?= esc($record['description'] ?? '') ?></td><?php endif; ?>
                    <td><?= (int) $record['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('masters/references/' . $type . '/' . $record['id'] . '/edit') ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="<?= site_url('masters/references/' . $type . '/' . $record['id'] . '/deactivate') ?>" class="d-inline" onsubmit="return confirm('Deactivate this reference?');">
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
