<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-primary" href="<?= site_url('admin/users/new') ?>">
        <i class="fa-solid fa-user-plus me-2" aria-hidden="true"></i>
        New user
    </a>
</div>

<section class="panel">
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Password</th>
                <th>Last login</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($user['full_name']) ?></td>
                    <td><?= esc($user['email']) ?></td>
                    <td>
                        <?php foreach ($user['roles'] as $role): ?>
                            <span class="badge text-bg-light border me-1 mb-1"><?= esc($role['name']) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <span class="badge <?= $user['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= esc(ucfirst((string) $user['status'])) ?>
                        </span>
                    </td>
                    <td><?= (int) $user['must_change_password'] === 1 ? 'Must change' : 'Set' ?></td>
                    <td><?= esc($user['last_login_at'] ?? 'Never') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/users/' . $user['id'] . '/edit') ?>" title="Edit user">
                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                        <form method="post" action="<?= site_url('admin/users/' . $user['id'] . '/deactivate') ?>" class="d-inline" onsubmit="return confirm('Deactivate this user?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Deactivate user">
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
