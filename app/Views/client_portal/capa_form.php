<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<section class="panel mb-3">
    <div class="panel-title">NCR details</div>
    <dl class="row mb-0">
        <dt class="col-md-3">Audit</dt>
        <dd class="col-md-9"><?= esc(str_replace('_', ' ', (string) $capa['event_type']) . ' - ' . $capa['audit_number']) ?></dd>
        <dt class="col-md-3">NCR</dt>
        <dd class="col-md-9"><?= esc($capa['ncr_number']) ?></dd>
        <dt class="col-md-3">Requirement</dt>
        <dd class="col-md-9"><?= nl2br(esc((string) $capa['requirement'])) ?></dd>
        <dt class="col-md-3">Finding</dt>
        <dd class="col-md-9"><?= nl2br(esc((string) $capa['finding'])) ?></dd>
        <dt class="col-md-3">Objective evidence</dt>
        <dd class="col-md-9"><?= nl2br(esc((string) $capa['objective_evidence'])) ?></dd>
    </dl>
</section>

<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="panel-title">CAPA response</div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="immediate_correction">Immediate correction</label>
                <textarea id="immediate_correction" name="immediate_correction" class="form-control" rows="3"><?= esc(old('immediate_correction', $capa['immediate_correction'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="root_cause">Root cause</label>
                <textarea id="root_cause" name="root_cause" class="form-control" rows="3" required><?= esc(old('root_cause', $capa['root_cause'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="corrective_action">Corrective action</label>
                <textarea id="corrective_action" name="corrective_action" class="form-control" rows="3" required><?= esc(old('corrective_action', $capa['corrective_action'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="preventive_action">Preventive action</label>
                <textarea id="preventive_action" name="preventive_action" class="form-control" rows="3"><?= esc(old('preventive_action', $capa['preventive_action'] ?? '')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="responsible_person">Responsible person</label>
                <input id="responsible_person" name="responsible_person" class="form-control" maxlength="180" value="<?= esc(old('responsible_person', $capa['responsible_person'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="target_date">Target date</label>
                <input id="target_date" name="target_date" type="date" class="form-control" value="<?= esc(old('target_date', $capa['target_date'] ?? '')) ?>">
            </div>
        </div>
    </section>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" type="submit">Submit CAPA</button>
        <a class="btn btn-outline-secondary" href="<?= site_url('client-portal') ?>">Back to client portal</a>
    </div>
</form>
<?= $this->endSection() ?>
