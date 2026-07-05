<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-primary" href="<?= site_url('masters/standards/new') ?>">
        <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>
        New standard
    </a>
</div>

<section class="panel">
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Version</th>
                <th>Scheme type</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($standards as $standard): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($standard['code']) ?></td>
                    <td><?= esc($standard['name']) ?></td>
                    <td><?= esc($standard['version'] ?? '') ?></td>
                    <td><?= esc($standard['scheme_type'] ?? '') ?></td>
                    <td><?= (int) $standard['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('masters/standards/' . $standard['id'] . '/edit') ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="<?= site_url('masters/standards/' . $standard['id'] . '/deactivate') ?>" class="d-inline" onsubmit="return confirm('Deactivate this standard?');">
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
