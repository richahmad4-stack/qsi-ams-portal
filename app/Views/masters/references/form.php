<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $value = static fn (string $field) => old($field, $record[$field] ?? ''); ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="code">Code</label>
                <input id="code" name="code" class="form-control" value="<?= esc($value('code')) ?>" required maxlength="30">
            </div>
            <div class="col-md-5">
                <label class="form-label" for="title">Title</label>
                <input id="title" name="title" class="form-control" value="<?= esc($value('title')) ?>" required maxlength="220">
            </div>
            <?php if (in_array('risk_level', $config['fields'], true)): ?>
                <div class="col-md-4">
                    <label class="form-label" for="risk_level">Risk level</label>
                    <select id="risk_level" name="risk_level" class="form-select">
                        <?php foreach (['', 'low', 'medium', 'high'] as $risk): ?>
                            <option value="<?= esc($risk) ?>" <?= $value('risk_level') === $risk ? 'selected' : '' ?>><?= esc($risk === '' ? 'Not set' : ucfirst($risk)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if (in_array('description', $config['fields'], true)): ?>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?= esc($value('description')) ?></textarea>
                </div>
            <?php endif; ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="active" name="active" <?= (int) $value('active') === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active">Active</label>
                </div>
            </div>
        </div>
    </section>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>
            Save
        </button>
        <a class="btn btn-outline-secondary" href="<?= site_url('masters/references/' . $type) ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
