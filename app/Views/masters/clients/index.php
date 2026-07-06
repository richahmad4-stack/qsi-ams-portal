<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-primary" href="<?= site_url('masters/clients/new') ?>">
        <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>
        New client
    </a>
</div>

<section class="panel">
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Company</th>
                <th>Requested standards</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Status</th>
                <th>Certificate</th>
                <th>Expiry</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= esc($client['company']) ?></div>
                        <div class="text-secondary small"><?= esc(trim(($client['city'] ?? '') . ' ' . ($client['country'] ?? ''))) ?></div>
                    </td>
                    <td>
                        <?php if (! empty($client['requested_standards'])): ?>
                            <?php foreach (explode(', ', $client['requested_standards']) as $standard): ?>
                                <span class="badge text-bg-light border me-1 mb-1"><?= esc($standard) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-secondary small">No standards linked</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($client['contact_person'] ?? '') ?></td>
                    <td><?= esc($client['email'] ?? '') ?></td>
                    <td><?= esc(str_replace('_', ' ', $client['certification_status'])) ?></td>
                    <td><?= esc($client['certificate_number'] ?? '') ?></td>
                    <td><?= esc($client['certificate_expiry_date'] ?? '') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('masters/clients/' . $client['id']) ?>">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('masters/clients/' . $client['id'] . '/edit') ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="<?= site_url('masters/clients/' . $client['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Delete this client?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
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
