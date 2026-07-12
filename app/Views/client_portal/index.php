<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$eventLabel = static fn (string $type): string => match ($type) {
    'initial_stage1' => 'Stage 1',
    'initial_stage2' => 'Stage 2',
    'surveillance1' => 'Surveillance 1',
    'surveillance2' => 'Surveillance 2',
    default => ucwords(str_replace('_', ' ', $type)),
};

$eventGroup = static fn (string $type): string => in_array($type, ['surveillance1', 'surveillance2'], true) ? 'surveillance' : 'initial';
$initialEvents = array_values(array_filter($events, static fn (array $event): bool => $eventGroup((string) $event['event_type']) === 'initial'));
$surveillanceEvents = array_values(array_filter($events, static fn (array $event): bool => $eventGroup((string) $event['event_type']) === 'surveillance'));
$capasByEvent = [];
foreach ($capas as $capa) {
    $capasByEvent[(string) $capa['audit_number']][] = $capa;
}
?>

<section class="panel mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <div class="panel-title mb-1"><?= esc($client['company']) ?></div>
            <div class="text-secondary">Client certification file and submitted records.</div>
        </div>
        <span class="badge text-bg-info align-self-start"><?= esc(str_replace('_', ' ', (string) ($client['certification_status'] ?? 'client'))) ?></span>
    </div>
</section>

<section class="panel mb-3">
    <div class="panel-title">Initial certification file</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Item</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($documents as $document): ?>
                <tr>
                    <td><?= esc($document['label']) ?></td>
                    <td>
                        <span class="badge <?= $document['available'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= esc($document['status']) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <?php if ($document['available']): ?>
                            <a class="btn btn-outline-danger btn-sm" href="<?= esc($document['url']) ?>"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Pending</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?= view('client_portal/_event_table', ['title' => 'Initial audit records', 'events' => $initialEvents, 'eventLabel' => $eventLabel, 'capasByEvent' => $capasByEvent]) ?>
<?= view('client_portal/_event_table', ['title' => 'Surveillance 1 and 2 records', 'events' => $surveillanceEvents, 'eventLabel' => $eventLabel, 'capasByEvent' => $capasByEvent]) ?>

<section class="panel mb-3">
    <div class="panel-title">Nonconformities</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>NCR</th><th>Audit</th><th>Class</th><th>Status</th><th>Target</th></tr></thead>
            <tbody>
            <?php foreach ($ncrs as $ncr): ?>
                <tr>
                    <td><?= esc($ncr['ncr_number']) ?></td>
                    <td><?= esc($eventLabel((string) $ncr['event_type'])) ?></td>
                    <td><?= esc(strtoupper((string) $ncr['classification'])) ?></td>
                    <td><?= esc($ncr['status']) ?></td>
                    <td><?= esc($ncr['target_date'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($ncrs === []): ?><tr><td colspan="5" class="text-secondary">No NCRs recorded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel mb-3">
    <div class="panel-title">CAPA responses</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>CAPA</th><th>NCR</th><th>Audit</th><th>Status</th><th>Target</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($capas as $capa): ?>
                <tr>
                    <td><?= esc($capa['capa_number']) ?></td>
                    <td><?= esc($capa['ncr_number']) ?></td>
                    <td><?= esc($eventLabel((string) $capa['event_type'])) ?></td>
                    <td><?= esc($capa['status']) ?></td>
                    <td><?= esc($capa['target_date'] ?? '') ?></td>
                    <td class="text-end"><a class="btn btn-primary btn-sm" href="<?= site_url('client-portal/capas/' . $capa['id']) ?>">Fill CAPA</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($capas === []): ?><tr><td colspan="6" class="text-secondary">No CAPA response required.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel mb-3">
    <div class="panel-title">Certificates</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Certificate</th><th>Standard</th><th>Issue</th><th>Expiry</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($certificates as $certificate): ?>
                <tr>
                    <td><?= esc($certificate['certificate_number']) ?></td>
                    <td><?= esc($certificate['standard_code']) ?></td>
                    <td><?= esc($certificate['issue_date']) ?></td>
                    <td><?= esc($certificate['expiry_date']) ?></td>
                    <td><?= esc($certificate['status']) ?></td>
                    <td class="text-end"><a class="btn btn-outline-danger btn-sm" href="<?= site_url('client-portal/certificates/' . $certificate['id'] . '/pdf') ?>"><i class="fa-solid fa-file-pdf me-1"></i>Certificate PDF</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($certificates === []): ?><tr><td colspan="6" class="text-secondary">No certificate issued yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-title">Client feedback</div>
    <form method="post" action="<?= site_url('client-portal/feedback') ?>" class="row g-3 mb-4">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label" for="certificate_id">Certificate</label>
            <select id="certificate_id" name="certificate_id" class="form-select">
                <option value="">General feedback</option>
                <?php foreach ($certificates as $certificate): ?>
                    <option value="<?= esc((string) $certificate['id']) ?>"><?= esc($certificate['certificate_number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php foreach (['overall_rating' => 'Overall', 'communication_rating' => 'Communication', 'auditor_rating' => 'Auditor', 'report_quality_rating' => 'Report quality'] as $field => $label): ?>
            <div class="col-md-2">
                <label class="form-label" for="<?= esc($field) ?>"><?= esc($label) ?></label>
                <select id="<?= esc($field) ?>" name="<?= esc($field) ?>" class="form-select">
                    <option value="">-</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <div class="col-md-6">
            <label class="form-label" for="comments">Comments</label>
            <textarea id="comments" name="comments" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="improvement_suggestion">Improvement suggestion</label>
            <textarea id="improvement_suggestion" name="improvement_suggestion" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Submit feedback</button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Date</th><th>Overall</th><th>Comments</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($feedbackRows as $feedback): ?>
                <tr><td><?= esc($feedback['submitted_at']) ?></td><td><?= esc((string) ($feedback['overall_rating'] ?? '')) ?></td><td><?= esc($feedback['comments'] ?? '') ?></td><td><?= esc($feedback['status']) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($feedbackRows === []): ?><tr><td colspan="4" class="text-secondary">No feedback submitted.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
