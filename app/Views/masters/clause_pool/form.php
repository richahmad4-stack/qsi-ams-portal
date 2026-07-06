<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= esc($action) ?>" class="panel">
    <?= csrf_field() ?>

    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="template_code">Template code</label>
            <input class="form-control" id="template_code" name="template_code" value="<?= old('template_code', $row['template_code']) ?>" required maxlength="80">
        </div>
        <div class="col-md-5">
            <label class="form-label" for="template_title">Template title</label>
            <input class="form-control" id="template_title" name="template_title" value="<?= old('template_title', $row['template_title']) ?>" required maxlength="180">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="content_type">Content type</label>
            <select class="form-select" id="content_type" name="content_type" required>
                <?php foreach (['conformity_answer', 'objective_evidence', 'auditor_comment', 'positive_observation', 'ofi', 'minor_nc', 'major_nc', 'capa', 'root_cause', 'correction', 'corrective_action', 'preventive_action', 'recommendation'] as $type): ?>
                    <option value="<?= esc($type) ?>" <?= old('content_type', $row['content_type']) === $type ? 'selected' : '' ?>>
                        <?= esc(ucwords(str_replace('_', ' ', $type))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="severity">Severity</label>
            <select class="form-select" id="severity" name="severity">
                <?php foreach (['' => 'Not applicable', 'minor' => 'Minor', 'major' => 'Major'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= old('severity', $row['severity']) === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="standard_id">Standard</label>
            <select class="form-select" id="standard_id" name="standard_id">
                <option value="">Any standard</option>
                <?php foreach ($standards as $standard): ?>
                    <option value="<?= esc($standard['id']) ?>" <?= (string) old('standard_id', $row['standard_id']) === (string) $standard['id'] ? 'selected' : '' ?>>
                        <?= esc($standard['code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="clause_library_id">Clause</label>
            <select class="form-select" id="clause_library_id" name="clause_library_id">
                <option value="">Any clause</option>
                <?php foreach ($clauses as $clause): ?>
                    <option value="<?= esc($clause['id']) ?>" <?= (string) old('clause_library_id', $row['clause_library_id']) === (string) $clause['id'] ? 'selected' : '' ?>>
                        <?= esc($clause['standard_code'] . ' ' . $clause['clause_number'] . ' - ' . $clause['clause_title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="audit_stage">Audit stage</label>
            <select class="form-select" id="audit_stage" name="audit_stage">
                <?php foreach (['all', 'initial_stage1', 'initial_stage2', 'surveillance1', 'surveillance2', 'recertification'] as $stage): ?>
                    <option value="<?= esc($stage) ?>" <?= old('audit_stage', $row['audit_stage']) === $stage ? 'selected' : '' ?>>
                        <?= esc(ucwords(str_replace('_', ' ', $stage))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label" for="scope_keyword">Scope keywords</label>
            <input class="form-control" id="scope_keyword" name="scope_keyword" value="<?= old('scope_keyword', $row['scope_keyword']) ?>" placeholder="catering, bakery, meat, ISO 9001 service">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="industry_type">Industry type</label>
            <input class="form-control" id="industry_type" name="industry_type" value="<?= old('industry_type', $row['industry_type']) ?>" placeholder="food, environment, ohs, service">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tags">Tags</label>
            <input class="form-control" id="tags" name="tags" value="<?= old('tags', is_array(json_decode((string) ($row['tags'] ?? ''), true)) ? implode(', ', json_decode((string) $row['tags'], true)) : (string) ($row['tags'] ?? '')) ?>" placeholder="temperature, traceability, calibration">
        </div>

        <div class="col-12">
            <label class="form-label" for="content_text">Template content</label>
            <textarea class="form-control" id="content_text" name="content_text" rows="10" required><?= old('content_text', $row['content_text']) ?></textarea>
            <div class="form-text">Available tokens: {client}, {scope}, {stage}, {standard}, {clause}, {title}, {reference}</div>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="active" name="active" <?= (int) old('active', $row['active']) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="active">Active</label>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save
        </button>
        <a class="btn btn-outline-secondary" href="<?= site_url('masters/clause-pool') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
