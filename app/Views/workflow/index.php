<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Client workflow register</div>
            <div class="text-secondary small">Track every certification file from application through feedback.</div>
        </div>
        <a href="<?= site_url('masters/clients/new') ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>
            New client
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Client</th>
                <th>Standards</th>
                <th>Current step</th>
                <th>Status</th>
                <th style="width: 240px;">Progress</th>
                <th>Certificate</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($summaries as $summary): ?>
                <?php
                $client = $summary['client'];
                $standards = $summary['standards'] ?? [];
                $workflow = $summary['workflow'];
                $current = $workflow['current'];
                $responsible = $workflow['responsible'] ?? [];
                $assignee = match ($current['key']) {
                    'tm_application_review' => $responsible['technical_manager'] ?? null,
                    'qm_application_approval' => $responsible['quality_manager'] ?? null,
                    'proposal' => $responsible['proposal_created_by'] ?? null,
                    'contract' => $responsible['contract_signed_by'] ?? ($responsible['contract_created_by'] ?? null),
                    'audit_program' => $responsible['audit_program_created_by'] ?? null,
                    'auditor_appointment', 'stage1', 'stage2', 'ncr_closure' => ($responsible['all_auditors'][0]['full_name'] ?? null),
                    'tm_file_review' => $responsible['technical_reviewer'] ?? null,
                    'certification_decision' => $responsible['decision_maker'] ?? null,
                    'gm_final_approval' => $responsible['general_manager'] ?? null,
                    default => null,
                };
                $badge = match ($current['state']) {
                    'complete' => 'success',
                    'in_progress' => 'primary',
                    'rejected' => 'danger',
                    default => 'secondary',
                };
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= esc($client['company']) ?></div>
                        <div class="text-secondary small"><?= esc($client['certification_status'] ?? 'No status') ?></div>
                    </td>
                    <td>
                        <?php if ($standards === []): ?>
                            <span class="text-secondary small">Not selected</span>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($standards as $standard): ?>
                                    <span class="badge text-bg-light border" title="<?= esc($standard['name'] ?? '') ?>">
                                        <?= esc($standard['code']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= esc($current['label']) ?></div>
                        <div class="text-secondary small"><?= esc($current['owner']) ?><?= $assignee ? ' - ' . esc($assignee) : '' ?></div>
                    </td>
                    <td><span class="badge text-bg-<?= esc($badge) ?>"><?= esc(str_replace('_', ' ', $current['state'])) ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= esc($workflow['progress']) ?>%;" aria-valuenow="<?= esc($workflow['progress']) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <span class="small fw-semibold"><?= esc($workflow['completed']) ?>/<?= esc($workflow['total']) ?></span>
                        </div>
                    </td>
                    <td>
                        <div><?= esc($client['certificate_number'] ?: 'Not issued') ?></div>
                        <div class="text-secondary small"><?= esc($client['certificate_expiry_date'] ?: '') ?></div>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id']) ?>">
                            <i class="fa-solid fa-eye me-1" aria-hidden="true"></i>
                            Open
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
