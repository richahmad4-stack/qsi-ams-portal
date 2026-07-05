<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/technical-review') ?>" class="panel">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Technical file review</div>
            <div class="text-secondary small">Confirm the audit file is complete before certification decision.</div>
        </div>
        <div class="d-flex gap-2">
            <?php if (! empty($selectedEvent['id'])): ?>
                <a href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $selectedEvent['id'] . '/documents/technical_review') ?>" class="btn btn-outline-danger btn-sm">
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

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label class="form-label" for="audit_event_id">Audit file</label>
            <select class="form-select" id="audit_event_id" name="audit_event_id" required>
                <?php foreach ($events as $event): ?>
                    <option value="<?= esc($event['id']) ?>" <?= (int) ($selectedEvent['id'] ?? 0) === (int) $event['id'] ? 'selected' : '' ?>>
                        <?= esc($event['audit_number'] . ' - ' . str_replace('_', ' ', $event['event_type']) . ' - ' . $event['status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="reviewer_personnel_id">Technical reviewer</label>
            <select class="form-select" id="reviewer_personnel_id" name="reviewer_personnel_id" required>
                <?php foreach ($reviewers as $reviewer): ?>
                    <option value="<?= esc($reviewer['id']) ?>" <?= (int) ($review['reviewer_personnel_id'] ?? 0) === (int) $reviewer['id'] ? 'selected' : '' ?>>
                        <?= esc($reviewer['full_name'] . ' - ' . str_replace('_', ' ', $reviewer['personnel_type'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Review status</label>
            <select class="form-select" id="status" name="status" required>
                <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned for correction', 'rejected' => 'Rejected'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($review['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Audit reports</div>
                <div class="metric-value"><?= esc($reportCount) ?></div>
                <div class="text-secondary small">Submitted/draft report record(s)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="metric">
                <div class="text-secondary small">Open NCRs</div>
                <div class="metric-value"><?= esc($openNcrCount) ?></div>
                <div class="text-secondary small"><?= esc(max(0, (int) $totalNcrCount - (int) $openNcrCount)) ?> closed of <?= esc($totalNcrCount) ?> total</div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="recommendation">Recommendation</label>
            <select class="form-select" id="recommendation" name="recommendation">
                <?php foreach (['' => 'Not selected', 'recommend_certification' => 'Recommend certification', 'return_for_correction' => 'Return for correction', 'do_not_recommend' => 'Do not recommend'] as $value => $label): ?>
                    <option value="<?= esc($value) ?>" <?= ($review['recommendation'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">File basis</div>
                <table class="table table-sm mb-0">
                    <tbody>
                    <tr><th>Audit stage</th><td><?= esc(str_replace('_', ' ', (string) ($selectedEvent['event_type'] ?? 'Not selected'))) ?></td></tr>
                    <tr><th>Audit number</th><td><?= esc($selectedEvent['audit_number'] ?? '') ?></td></tr>
                    <tr><th>Planned dates</th><td><?= esc(trim((string) (($selectedEvent['planned_start_date'] ?? '') . ' to ' . ($selectedEvent['planned_end_date'] ?? '')), ' to')) ?></td></tr>
                    <tr><th>Standards</th><td><?= esc(implode(', ', array_filter(array_column($standards, 'standard_code')))) ?></td></tr>
                    <tr><th>Reviewed at</th><td><?= esc($review['reviewed_at'] ?? 'Not recorded') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">Audit team</div>
                <?php if ($auditTeam === []): ?>
                    <div class="text-secondary small">No auditor appointment found for this stage.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($auditTeam as $member): ?>
                                <tr>
                                    <td><?= esc($member['full_name']) ?></td>
                                    <td><?= esc(str_replace('_', ' ', (string) $member['appointment_role'])) ?></td>
                                    <td><?= esc($member['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">Audit report submission</div>
                <?php if ($reportRows === []): ?>
                    <div class="text-secondary small">No report record found for this stage.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Report</th><th>Status</th><th>Submitted</th></tr></thead>
                            <tbody>
                            <?php foreach ($reportRows as $reportRow): ?>
                                <tr>
                                    <td><?= esc($reportRow['audit_number'] . ' - v' . $reportRow['version_number']) ?></td>
                                    <td><?= esc($reportRow['status']) ?></td>
                                    <td><?= esc($reportRow['submitted_at'] ?? 'Not submitted') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-2">NCR / CAPA position</div>
                <?php if ($ncrRows === []): ?>
                    <div class="text-secondary small">No nonconformities recorded for this stage.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>NCR</th><th>Clause</th><th>Class</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($ncrRows as $ncr): ?>
                                <tr>
                                    <td><?= esc($ncr['ncr_number']) ?></td>
                                    <td><?= esc(trim((string) (($ncr['standard_code'] ?? '') . ' ' . ($ncr['clause_number'] ?? '')))) ?></td>
                                    <td><?= esc($ncr['classification']) ?></td>
                                    <td><?= esc($ncr['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <?php foreach ([
            'competency_confirmed' => 'Auditor competence confirmed',
            'duration_confirmed' => 'Audit duration confirmed',
            'application_confirmed' => 'Application and contract scope confirmed',
            'reports_confirmed' => 'Audit reports and evidence reviewed',
            'ncr_capa_confirmed' => 'NCR/CAPA closure confirmed',
            'scope_dates_confirmed' => 'Scope, issue date and expiry date confirmed',
            'impartiality_confirmed' => 'Impartiality and conflict check confirmed',
        ] as $field => $label): ?>
            <div class="col-md-6">
                <label class="border rounded p-2 w-100">
                    <input type="checkbox" name="<?= esc($field) ?>" value="1" <?= (int) ($review[$field] ?? 0) === 1 ? 'checked' : '' ?>>
                    <?= esc($label) ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mb-3">
        <label class="form-label" for="review_notes">Review notes</label>
        <textarea class="form-control" id="review_notes" name="review_notes" rows="4"><?php
            $payload = isset($review['checklist_payload']) ? json_decode((string) $review['checklist_payload'], true) : [];
            echo esc($payload['review_notes'] ?? '');
        ?></textarea>
    </div>

    <div class="d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
            Save technical review
        </button>
    </div>
</form>
<?= $this->endSection() ?>
