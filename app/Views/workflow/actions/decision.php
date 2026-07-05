<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/decision') ?>" class="panel">
    <?= csrf_field() ?>
    <?php if (! empty($eventId)): ?>
        <input type="hidden" name="event_id" value="<?= esc($eventId) ?>">
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Certification decision and GM approval</div>
            <div class="text-secondary small">Record the independent decision, then final management approval.</div>
        </div>
        <div class="d-flex gap-2">
            <?php if (! empty($technicalReview['audit_event_id'])): ?>
                <a href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $technicalReview['audit_event_id'] . '/documents/decision_report') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
                    PDF
                </a>
            <?php endif; ?>
            <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                Back
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        Technical review status: <strong><?= esc($technicalReview['status']) ?></strong>
        <?php if (($technicalReview['recommendation'] ?? '') !== ''): ?>
            | Recommendation: <strong><?= esc(str_replace('_', ' ', $technicalReview['recommendation'])) ?></strong>
        <?php endif; ?>
        | Open NCRs: <strong><?= esc($openNcrCount) ?></strong>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Audit stage</div>
                <div class="metric-value fs-5"><?= esc(str_replace('_', ' ', (string) ($reviewEvent['event_type'] ?? 'Audit'))) ?></div>
                <div class="text-secondary small"><?= esc($reviewEvent['audit_number'] ?? '') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">NCR closure</div>
                <div class="metric-value"><?= esc(max(0, (int) $totalNcrCount - (int) $openNcrCount)) ?>/<?= esc($totalNcrCount) ?></div>
                <div class="text-secondary small">Closed against total NCRs</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Standards</div>
                <div class="metric-value fs-5"><?= esc(implode(', ', array_filter(array_column($standards, 'standard_code')))) ?></div>
                <div class="text-secondary small">Separate certificates issued after approval</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="decision_maker_personnel_id">Decision maker</label>
            <select class="form-select" id="decision_maker_personnel_id" name="decision_maker_personnel_id" required>
                <?php foreach ($decisionMakers as $person): ?>
                    <option value="<?= esc($person['id']) ?>" <?= (int) ($decision['decision_maker_personnel_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>>
                        <?= esc($person['full_name'] . ' - ' . str_replace('_', ' ', $person['personnel_type'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="decision">Decision</label>
            <select class="form-select" id="decision" name="decision" required>
                <?php foreach (['granted' => 'Certification granted', 'not_granted' => 'Certification not granted', 'deferred' => 'Deferred'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($decision['decision'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Decision status</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['pending' => 'Pending', 'decided' => 'Decided', 'approved' => 'Approved'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($decision['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="electronic_signature">Electronic signature</label>
            <input class="form-control" id="electronic_signature" name="electronic_signature" value="<?= esc(old('electronic_signature', $decision['electronic_signature'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">General Manager approval</label>
            <label class="border rounded p-2 w-100">
                <input type="checkbox" name="gm_approved" value="1" <?= ($decision['status'] ?? '') === 'gm_approved' ? 'checked' : '' ?>>
                Final approval granted by current logged-in user
            </label>
        </div>
        <div class="col-12">
            <label class="form-label" for="reason">Decision reason</label>
            <textarea class="form-control" id="reason" name="reason" rows="4"><?= esc(old('reason', $decision['reason'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="gm_approval_notes">GM approval notes</label>
            <textarea class="form-control" id="gm_approval_notes" name="gm_approval_notes" rows="3"><?= esc(old('gm_approval_notes', $decision['gm_approval_notes'] ?? '')) ?></textarea>
        </div>
    </div>

    <div class="border rounded p-3 mt-3">
        <div class="fw-semibold mb-2">Decision output</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-secondary small">Decision date</div>
                <div><?= esc($decision['decided_at'] ?? 'Not recorded') ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">GM approved at</div>
                <div><?= esc($decision['gm_approved_at'] ?? 'Not recorded') ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Certificates issued</div>
                <div><?= esc(count($certificates)) ?></div>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-stamp me-1" aria-hidden="true"></i>
            Save decision
        </button>
    </div>
</form>
<?= $this->endSection() ?>
