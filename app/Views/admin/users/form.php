<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($action) ?>" class="panel">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="full_name">Full name</label>
            <input class="form-control" id="full_name" name="full_name" value="<?= esc(old('full_name', $user['full_name'])) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" type="email" name="email" value="<?= esc(old('email', $user['email'])) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" value="<?= esc(old('phone', $user['phone'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= old('status', $user['status']) === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="password"><?= $isNew ? 'Temporary password' : 'Reset password' ?></label>
            <input class="form-control" id="password" type="password" name="password" autocomplete="new-password" placeholder="<?= $isNew ? 'Default: Password123!' : 'Leave blank to keep current password' ?>">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="must_change_password" name="must_change_password" value="1" <?= (int) old('must_change_password', $user['must_change_password']) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="must_change_password">Require password change on next login</label>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <div class="mb-2 fw-semibold">Roles</div>
    <div class="row g-2">
        <?php foreach ($roles as $role): ?>
            <?php $roleId = (int) $role['id']; ?>
            <div class="col-md-4">
                <label class="form-check border rounded p-2 h-100">
                    <input class="form-check-input ms-0 me-2" type="checkbox" name="roles[]" value="<?= esc((string) $roleId) ?>" <?= in_array($roleId, $assignedRoleIds, true) ? 'checked' : '' ?>>
                    <span class="fw-semibold"><?= esc($role['name']) ?></span>
                    <?php if (! empty($role['description'])): ?>
                        <span class="d-block text-secondary small"><?= esc($role['description']) ?></span>
                    <?php endif; ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="<?= site_url('admin/users') ?>">Cancel</a>
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>
            Save user
        </button>
    </div>
</form>
<?= $this->endSection() ?>
