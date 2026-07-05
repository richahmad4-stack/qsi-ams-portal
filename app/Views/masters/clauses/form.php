<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$value = static fn (string $field) => old($field, $clause[$field] ?? '');
$riskRatings = ['', 'low', 'medium', 'high'];
$stages = ['', 'stage_1', 'stage_2', 'surveillance', 'recertification', 'all'];
?>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <section class="panel mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="standard_id">Standard</label>
                <select id="standard_id" name="standard_id" class="form-select" required>
                    <option value="">Select standard</option>
                    <?php foreach ($standards as $standard): ?>
                        <option value="<?= esc((string) $standard['id']) ?>" <?= (string) $value('standard_id') === (string) $standard['id'] ? 'selected' : '' ?>>
                            <?= esc($standard['code']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="clause_number">Clause number</label>
                <input id="clause_number" name="clause_number" class="form-control" value="<?= esc($value('clause_number')) ?>" required maxlength="60">
            </div>
            <div class="col-md-5">
                <label class="form-label" for="clause_title">Clause title</label>
                <input id="clause_title" name="clause_title" class="form-control" value="<?= esc($value('clause_title')) ?>" required maxlength="255">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="risk_rating">Risk rating</label>
                <select id="risk_rating" name="risk_rating" class="form-select">
                    <?php foreach ($riskRatings as $risk): ?>
                        <option value="<?= esc($risk) ?>" <?= $value('risk_rating') === $risk ? 'selected' : '' ?>><?= esc($risk === '' ? 'Not set' : ucfirst($risk)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="stage_applicability">Stage applicability</label>
                <select id="stage_applicability" name="stage_applicability" class="form-select">
                    <?php foreach ($stages as $stage): ?>
                        <option value="<?= esc($stage) ?>" <?= $value('stage_applicability') === $stage ? 'selected' : '' ?>><?= esc($stage === '' ? 'Not set' : str_replace('_', ' ', $stage)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="active" name="active" <?= (int) $value('active') === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active">Active</label>
                </div>
            </div>
        </div>
    </section>

    <section class="panel mb-3">
        <div class="panel-title">Requirement and predefined audit notes</div>
        <?php
        $textareas = [
            'requirement' => 'Requirement',
            'predefined_conformity_note' => 'Predefined conformity note',
            'positive_finding' => 'Positive finding',
            'opportunity_for_improvement' => 'Opportunity for improvement',
            'minor_nc' => 'Minor NC',
            'major_nc' => 'Major NC',
            'evidence_examples' => 'Evidence examples',
            'auditor_guidance' => 'Auditor guidance',
        ];
        ?>
        <div class="row g-3">
            <?php foreach ($textareas as $field => $label): ?>
                <div class="col-md-6">
                    <label class="form-label" for="<?= esc($field) ?>"><?= esc($label) ?></label>
                    <textarea id="<?= esc($field) ?>" name="<?= esc($field) ?>" class="form-control" rows="4" <?= $field === 'requirement' ? 'required' : '' ?>><?= esc($value($field)) ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>
            Save
        </button>
        <a class="btn btn-outline-secondary" href="<?= site_url('masters/clauses') ?>">Cancel</a>
    </div>
</form>
<?= $this->endSection() ?>
