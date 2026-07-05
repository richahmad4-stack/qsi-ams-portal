<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $value = static fn (string $field) => old($field, $standard[$field] ?? ''); ?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="code">Code</label>
                <input id="code" name="code" class="form-control" value="<?= esc($value('code')) ?>" required maxlength="80">
            </div>
            <div class="col-md-5">
                <label class="form-label" for="name">Name</label>
                <input id="name" name="name" class="form-control" value="<?= esc($value('name')) ?>" required maxlength="180">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="version">Version</label>
                <input id="version" name="version" class="form-control" value="<?= esc($value('version')) ?>" maxlength="80">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="scheme_type">Scheme type</label>
                <input id="scheme_type" name="scheme_type" class="form-control" value="<?= esc($value('scheme_type')) ?>" maxlength="80">
            </div>
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
        <a class="btn btn-outline-secondary" href="<?= site_url('masters/standards') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
