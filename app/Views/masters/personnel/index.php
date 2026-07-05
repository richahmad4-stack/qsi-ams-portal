<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$affiliationLabel = static function (array $person): string {
    return ($person['personnel_type'] ?? '') === 'client_representative' ? 'Client' : 'Certification Body';
};
$affiliationClass = static function (array $person): string {
    return ($person['personnel_type'] ?? '') === 'client_representative' ? 'text-bg-info' : 'text-bg-primary';
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Personnel affiliation filter">
        <a class="btn <?= ($affiliation ?? '') === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= site_url('masters/personnel') ?>">All</a>
        <a class="btn <?= ($affiliation ?? '') === 'certification_body' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= site_url('masters/personnel?affiliation=certification_body') ?>">Certification Body</a>
        <a class="btn <?= ($affiliation ?? '') === 'client' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= site_url('masters/personnel?affiliation=client') ?>">Client</a>
    </div>
    <a class="btn btn-primary" href="<?= site_url('masters/personnel/new') ?>">
        <i class="fa-solid fa-plus me-2" aria-hidden="true"></i>
        New personnel
    </a>
</div>

<section class="panel">
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Affiliation</th>
                <th>Linked client</th>
                <th>Type</th>
                <th>Approval</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($personnel as $person): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($person['full_name']) ?></td>
                    <td><?= esc($person['email'] ?? '') ?></td>
                    <td><?= esc($person['phone'] ?? '') ?></td>
                    <td><span class="badge <?= esc($affiliationClass($person)) ?>"><?= esc($affiliationLabel($person)) ?></span></td>
                    <td>
                        <?php if (($person['personnel_type'] ?? '') === 'client_representative'): ?>
                            <?= esc($person['linked_client_company'] ?? 'Not linked') ?>
                        <?php else: ?>
                            <span class="text-secondary">Not applicable</span>
                        <?php endif; ?>
                    </td>
                    <td><?= esc(str_replace('_', ' ', $person['personnel_type'])) ?></td>
                    <td><?= esc(str_replace('_', ' ', $person['approval_status'])) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('masters/personnel/' . $person['id']) ?>">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('masters/personnel/' . $person['id'] . '/edit') ?>">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="<?= site_url('masters/personnel/' . $person['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Delete this personnel profile?');">
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
