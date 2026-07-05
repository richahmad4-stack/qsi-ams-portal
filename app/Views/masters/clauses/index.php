<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-primary" href="<?= site_url('masters/clauses/new') ?>">
        <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>
        New clause
    </a>
</div>

<section class="panel">
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Standard</th>
                <th>Clause</th>
                <th>Title</th>
                <th>Risk</th>
                <th>Stage</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($clauses as $clause): ?>
                <tr>
                    <td><?= esc($clause['standard_code']) ?></td>
                    <td class="fw-semibold"><?= esc($clause['clause_number']) ?></td>
                    <td><?= esc($clause['clause_title']) ?></td>
                    <td><?= esc($clause['risk_rating'] ?? '') ?></td>
                    <td><?= esc($clause['stage_applicability'] ?? '') ?></td>
                    <td><?= (int) $clause['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('masters/clauses/' . $clause['id'] . '/edit') ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="<?= site_url('masters/clauses/' . $clause['id'] . '/deactivate') ?>" class="d-inline" onsubmit="return confirm('Deactivate this clause?');">
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
