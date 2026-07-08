<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('masters/templates/' . $template['id']) ?>" class="panel">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1"><?= esc($template['name']) ?></div>
            <div class="text-secondary small"><?= esc($template['template_key']) ?> | Active version <?= esc($template['active_version'] ?? 'None') ?></div>
        </div>
        <a href="<?= site_url('masters/templates') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
            Back
        </a>
    </div>

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="name">Template name</label>
            <input class="form-control" id="name" name="name" value="<?= esc(old('name', $template['name'])) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach (['draft', 'approved', 'retired'] as $status): ?>
                    <option value="<?= esc($status) ?>" <?= $template['status'] === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="document_number">Document No.</label>
            <input class="form-control" id="document_number" name="document_number" value="<?= esc(old('document_number', $template['document_number'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="revision_number">Revision No.</label>
            <input class="form-control" id="revision_number" name="revision_number" value="<?= esc(old('revision_number', $template['revision_number'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="issue_number">Issue No.</label>
            <input class="form-control" id="issue_number" name="issue_number" value="<?= esc(old('issue_number', $template['issue_number'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="document_date">Document Date</label>
            <input class="form-control" id="document_date" name="document_date" type="date" value="<?= esc(old('document_date', $template['document_date'] ?? '')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="body_html">Body HTML</label>
            <textarea class="form-control font-monospace" id="body_html" name="body_html" rows="16" required><?= esc(old('body_html', $version['body_html'] ?? '<h2>{{document_title}}</h2><p>{{client_name}}</p>')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="header_html">Header HTML</label>
            <textarea class="form-control font-monospace" id="header_html" name="header_html" rows="5"><?= esc(old('header_html', $version['header_html'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="footer_html">Footer HTML</label>
            <textarea class="form-control font-monospace" id="footer_html" name="footer_html" rows="5"><?= esc(old('footer_html', $version['footer_html'] ?? '')) ?></textarea>
        </div>
    </div>

    <div class="alert alert-info mt-3">
        This saves a new template version. Your official Word/PDF templates can be converted into this HTML structure later.
    </div>

    <div class="d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save version
        </button>
    </div>
</form>
<?= $this->endSection() ?>
