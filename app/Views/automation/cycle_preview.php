<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$input = $preview['input'];
$cycle = $preview['cycle'];
$timeline = $preview['timeline'];
$duration = $preview['duration'];
$events = $preview['events'];
$assignments = $preview['assignments'];
$warnings = $preview['warnings'];
?>

<section class="panel mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-3">
        <div>
            <div class="panel-title mb-1"><?= esc($input['client_name']) ?></div>
            <div class="text-secondary"><?= esc(implode(', ', array_column($preview['standards'], 'code'))) ?></div>
        </div>
        <div class="d-flex gap-2 align-items-start">
            <a class="btn btn-outline-secondary" href="<?= site_url('automation/cycle-generator') ?>">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                Back
            </a>
            <form method="post" action="<?= site_url('automation/cycle-generator/generate') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="preview_payload" value="<?= esc($encodedPreview) ?>">
                <button class="btn btn-primary" type="submit" <?= $preview['can_generate'] ? '' : 'disabled' ?>>
                    <i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>
                    Prepare full cycle
                </button>
            </form>
        </div>
    </div>
</section>

<?php if ($warnings !== []): ?>
    <section class="panel mb-3">
        <div class="panel-title">Warnings and controls</div>
        <?php foreach ($warnings as $warning): ?>
            <div class="alert alert-<?= ($warning['level'] ?? '') === 'critical' ? 'danger' : 'warning' ?> py-2 mb-2">
                <strong><?= esc(strtoupper($warning['level'] ?? 'info')) ?>:</strong>
                <?= esc($warning['message']) ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <section class="panel h-100">
            <div class="panel-title">Cycle dates</div>
            <table class="table table-sm">
                <tr><th>Issue</th><td><?= esc($cycle['issue']) ?></td></tr>
                <tr><th>Surveillance 1 due</th><td><?= esc($cycle['surveillance1']) ?></td></tr>
                <tr><th>Surveillance 2 due</th><td><?= esc($cycle['surveillance2']) ?></td></tr>
                <tr><th>Expiry</th><td><?= esc($cycle['expiry']) ?></td></tr>
            </table>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="panel h-100">
            <div class="panel-title">Audit duration</div>
            <table class="table table-sm">
                <tr><th>Total initial</th><td><?= esc(number_format((float) $duration['total_days'], 2)) ?></td></tr>
                <tr><th>Stage 1</th><td><?= esc(number_format((float) $duration['stage1_days'], 2)) ?></td></tr>
                <tr><th>Stage 2</th><td><?= esc(number_format((float) $duration['stage2_days'], 2)) ?></td></tr>
                <tr><th>Surveillance</th><td><?= esc(number_format((float) $duration['surveillance1_days'], 2)) ?></td></tr>
                <tr><th>Recertification</th><td><?= esc(number_format((float) $duration['recertification_days'], 2)) ?></td></tr>
            </table>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="panel h-100">
            <div class="panel-title">Staff assignment preview</div>
            <table class="table table-sm">
                <tr>
                    <th>Generation mode</th>
                    <td><?= esc($input['generation_mode'] === 'historical_confirmed' ? 'Completed historical file from supplied records' : 'Complete workflow pack') ?></td>
                </tr>
                <?php foreach ($assignments as $role => $person): ?>
                    <tr>
                        <th><?= esc(ucwords(str_replace('_', ' ', $role))) ?></th>
                        <td><?= esc($person['full_name'] ?? 'Not assigned') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>
    </div>
</div>

<section class="panel mb-3">
    <div class="panel-title">Backward lifecycle timeline</div>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead><tr><th>Step</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($timeline as $step => $date): ?>
                <tr>
                    <td><?= esc(ucwords(str_replace('_', ' ', $step))) ?></td>
                    <td><?= esc($date) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel mb-3">
    <div class="panel-title">Evidence and approval controls</div>
    <div class="table-responsive">
        <table class="table table-sm">
            <tr><th>Application review basis</th><td><?= esc($input['application_review_notes'] ?: 'To be completed during application review') ?></td></tr>
            <tr><th>Audit plan notes</th><td><?= esc($input['audit_plan_notes'] ?: 'Process timing will be prepared from selected scope, standards and audit duration') ?></td></tr>
            <tr><th>Audit evidence summary</th><td><?= esc($input['audit_evidence_summary'] ?: 'Auditor will complete clause evidence during audit execution') ?></td></tr>
            <tr><th>Technical review notes</th><td><?= esc($input['technical_review_notes'] ?: 'Technical Review will remain pending') ?></td></tr>
            <tr><th>Decision basis</th><td><?= esc($input['decision_basis'] ?: 'Decision and certificate issue will remain pending') ?></td></tr>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-title">Audit event plan</div>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
            <thead>
            <tr>
                <th>Audit stage</th>
                <th>Start</th>
                <th>End</th>
                <th>Days</th>
                <th>Auditor capacity</th>
                <th>Calendar days</th>
                <th>Status based on today</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($events as $type => $event): ?>
                <tr>
                    <td><?= esc(ucwords(str_replace('_', ' ', $type))) ?></td>
                    <td><?= esc($event['start']) ?></td>
                    <td><?= esc($event['end']) ?></td>
                    <td><?= esc(number_format((float) $event['days'], 2)) ?></td>
                    <td><?= esc((string) ($event['auditor_capacity'] ?? 1)) ?></td>
                    <td><?= esc((string) ($event['calendar_days'] ?? 1)) ?></td>
                    <td><?= esc(str_replace('_', ' ', $event['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
