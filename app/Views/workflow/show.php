<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<style>
    .workflow-card {
        display: block;
        height: 100%;
        min-height: 128px;
        border-radius: 8px;
        padding: 14px;
        background: #ffffff;
        color: inherit;
        text-decoration: none;
    }

    .workflow-card-clickable:hover {
        box-shadow: 0 8px 18px rgba(15, 94, 168, 0.12);
        transform: translateY(-1px);
    }

    .workflow-card-disabled {
        opacity: 0.68;
        cursor: not-allowed;
    }

    .workflow-section-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .workflow-subsection {
        border-top: 1px solid #dce6f0;
        margin-top: 18px;
        padding-top: 16px;
    }

    .workflow-subsection-title {
        font-weight: 700;
        margin-bottom: 4px;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$stateClasses = [
    'complete' => ['border-success', 'text-bg-success', 'fa-circle-check'],
    'in_progress' => ['border-primary', 'text-bg-primary', 'fa-clock'],
    'rejected' => ['border-danger', 'text-bg-danger', 'fa-circle-xmark'],
    'active' => ['border-primary', 'text-bg-primary', 'fa-clock'],
    'overdue' => ['border-danger', 'text-bg-danger', 'fa-triangle-exclamation'],
    'locked' => ['border-secondary', 'text-bg-secondary', 'fa-lock'],
    'pending' => ['border-secondary', 'text-bg-secondary', 'fa-circle'],
];
$records = $workflow['records'];
$responsible = $workflow['responsible'] ?? [];
$nameText = static function ($value): string {
    if (is_array($value)) {
        if ($value === []) {
            return 'Not assigned';
        }

        return implode(', ', array_map(static function (array $person): string {
            $role = isset($person['appointment_role']) ? ' (' . str_replace('_', ' ', $person['appointment_role']) . ')' : '';

            return ($person['full_name'] ?? 'Unnamed') . $role;
        }, $value));
    }

    return trim((string) $value) !== '' ? (string) $value : 'Not assigned';
};
$eventRoute = static function (?array $event, string $target) use ($client): ?string {
    if ($event === null) {
        return null;
    }

    $id = (int) $event['id'];

    return match ($target) {
        'appointment' => site_url('workflow/certification/' . $client['id'] . '/appointments?event_id=' . $id),
        'plan' => site_url('workflow/certification/' . $client['id'] . '/audit-plan?event_id=' . $id),
        'report' => site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $id . '/execute'),
        'ncr_capa' => site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $id . '/file#tab-ncr-capa'),
        'technical_review' => site_url('workflow/certification/' . $client['id'] . '/technical-review?event_id=' . $id),
        'decision' => site_url('workflow/certification/' . $client['id'] . '/decision?event_id=' . $id),
        'file' => site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $id . '/file'),
        default => null,
    };
};
$eventDueDate = static function (?array $event, ?string $fallback = null): ?string {
    if ($event === null) {
        return $fallback;
    }

    return $event['audit_window_end'] ?: ($event['planned_start_date'] ?: $fallback);
};
$eventLabel = static fn (?array $event): string => $event === null ? 'Not generated' : esc($event['audit_number'] . ' - ' . str_replace('_', ' ', $event['event_type']));
$today = new DateTimeImmutable(date('Y-m-d'));
$surveillanceState = static function (?array $event, ?string $dueDate) use ($today): array {
    if ($event !== null && in_array($event['status'], ['completed', 'closed'], true)) {
        return ['complete', 'Completed', true];
    }

    if ($dueDate === null || $dueDate === '') {
        return ['pending', 'Pending', false];
    }

    $due = new DateTimeImmutable($dueDate);
    if ($today < $due) {
        return ['locked', 'Locked - Not Due Yet', false];
    }

    if ($today > $due) {
        return ['overdue', 'Overdue', true];
    }

    return ['active', 'Active - Due Now', true];
};
$workflowCard = static function (string $title, string $owner, string $note, string $state, ?string $url, array $stateClasses, string $icon = 'fa-arrow-up-right-from-square'): string {
    $classes = $stateClasses[$state] ?? $stateClasses['pending'];
    $clickable = $url !== null && $state !== 'locked';
    $tag = $clickable ? 'a' : 'div';
    $href = $clickable ? ' href="' . esc($url, 'attr') . '"' : '';
    $extra = $clickable ? ' workflow-card-clickable' : ' workflow-card-disabled';

    return '<' . $tag . $href . ' class="workflow-card border ' . esc($classes[0]) . $extra . '">'
        . '<div class="d-flex align-items-start justify-content-between gap-2 mb-2">'
        . '<div><div class="text-secondary small">' . esc($owner) . '</div><div class="fw-semibold">' . esc($title) . '</div></div>'
        . '<span class="badge ' . esc($classes[1]) . '"><i class="fa-solid ' . esc($classes[2]) . ' me-1" aria-hidden="true"></i>' . esc(str_replace('_', ' ', $state)) . '</span>'
        . '</div>'
        . '<div class="small text-secondary">' . esc($note) . '</div>'
        . ($clickable ? '<div class="small fw-semibold mt-2"><i class="fa-solid ' . esc($icon) . ' me-1" aria-hidden="true"></i>Open</div>' : '')
        . '</' . $tag . '>';
};
$actionButton = static function (string $label, string $icon, ?string $url, bool $enabled = true, string $style = 'primary'): string {
    if ($url === null || ! $enabled) {
        return '<button class="btn btn-outline-secondary btn-sm" type="button" disabled>'
            . '<i class="fa-solid ' . esc($icon) . ' me-1" aria-hidden="true"></i>' . esc($label) . '</button>';
    }

    return '<a class="btn btn-outline-' . esc($style) . ' btn-sm" href="' . esc($url, 'attr') . '">'
        . '<i class="fa-solid ' . esc($icon) . ' me-1" aria-hidden="true"></i>' . esc($label) . '</a>';
};
$eventDocumentUrl = static function (?array $event, string $documentKey) use ($client): ?string {
    return $event === null ? null : site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/documents/' . $documentKey);
};
$renderEventFileTable = static function (?array $event, string $emptyText, bool $enabled = true) use ($client, $actionButton, $eventDocumentUrl): void {
    if ($event === null) {
        echo '<div class="text-secondary">' . esc($emptyText) . '</div>';
        return;
    }

    echo '<table class="table table-sm align-middle"><tbody>'
        . '<tr><th>Audit</th><td>' . esc($event['audit_number']) . '</td></tr>'
        . '<tr><th>Type</th><td>' . esc(str_replace('_', ' ', $event['event_type'])) . '</td></tr>'
        . '<tr><th>Planned dates</th><td>' . esc(($event['planned_start_date'] ?? '') . ' to ' . ($event['planned_end_date'] ?? '')) . '</td></tr>'
        . '<tr><th>Status</th><td>' . esc($event['status']) . '</td></tr>'
        . '</tbody></table>'
        . '<div class="d-flex flex-wrap justify-content-end gap-2">'
        . $actionButton('Appointment', 'fa-user-check', site_url('workflow/certification/' . $client['id'] . '/appointments?event_id=' . $event['id']), $enabled)
        . $actionButton('Plan', 'fa-list-check', site_url('workflow/certification/' . $client['id'] . '/audit-plan?event_id=' . $event['id']), $enabled)
        . $actionButton('Report', 'fa-clipboard-list', site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/execute'), $enabled)
        . $actionButton('File', 'fa-folder-open', site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/file'), $enabled)
        . $actionButton('Plan PDF', 'fa-file-pdf', $eventDocumentUrl($event, 'audit_plan'), $enabled, 'danger')
        . $actionButton('Report PDF', 'fa-file-pdf', $eventDocumentUrl($event, 'audit_report'), $enabled, 'danger')
        . $actionButton('NCR/CAPA PDF', 'fa-file-pdf', $eventDocumentUrl($event, 'ncr_capa'), $enabled, 'danger')
        . '</div>';
};
$renderExecutionTable = static function (array $executionEvents, string $emptyText) use ($client): void {
    echo '<div class="table-responsive"><table class="table table-striped align-middle">'
        . '<thead><tr><th>Audit</th><th>Type</th><th>Planned dates</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>';

    foreach ($executionEvents as $auditEvent) {
        echo '<tr>'
            . '<td>' . esc($auditEvent['audit_number']) . '</td>'
            . '<td>' . esc(str_replace('_', ' ', $auditEvent['event_type'])) . '</td>'
            . '<td>' . esc(($auditEvent['planned_start_date'] ?? '') . ' to ' . ($auditEvent['planned_end_date'] ?? '')) . '</td>'
            . '<td>' . esc($auditEvent['status']) . '</td>'
            . '<td class="text-end">'
            . '<a class="btn btn-outline-secondary btn-sm" href="' . esc(site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $auditEvent['id'] . '/file'), 'attr') . '"><i class="fa-solid fa-folder-open me-1" aria-hidden="true"></i>File</a> '
            . '<a class="btn btn-outline-primary btn-sm" href="' . esc(site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $auditEvent['id'] . '/execute'), 'attr') . '"><i class="fa-solid fa-clipboard-list me-1" aria-hidden="true"></i>Execute</a> '
            . '<a class="btn btn-outline-danger btn-sm" href="' . esc(site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $auditEvent['id'] . '/documents/audit_plan'), 'attr') . '"><i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>Plan PDF</a>'
            . '</td></tr>';
    }

    if ($executionEvents === []) {
        echo '<tr><td colspan="5" class="text-secondary">' . esc($emptyText) . '</td></tr>';
    }

    echo '</tbody></table></div>';
};
$stage1 = $records['stage1'] ?? null;
$stage2 = $records['stage2'] ?? null;
$surveillance1 = $records['surveillance1'] ?? null;
$surveillance2 = $records['surveillance2'] ?? null;
$recertification = $records['recertification'] ?? null;
$program = $records['audit_program'] ?? null;
$surveillance1Due = $program['surveillance_1_due_date'] ?? $eventDueDate($surveillance1);
$surveillance2Due = $program['surveillance_2_due_date'] ?? $eventDueDate($surveillance2);
$expiryDate = $program['certificate_expiry_date'] ?? null;
[$surveillance1State, $surveillance1Status, $surveillance1Clickable] = $surveillanceState($surveillance1, $surveillance1Due);
[$surveillance2State, $surveillance2Status, $surveillance2Clickable] = $surveillanceState($surveillance2, $surveillance2Due);
$stepLinks = [
    'application' => site_url('workflow/certification/' . $client['id'] . '/application'),
    'tm_application_review' => site_url('workflow/certification/' . $client['id'] . '/review'),
    'qm_application_approval' => site_url('workflow/certification/' . $client['id'] . '/review'),
    'proposal' => site_url('workflow/certification/' . $client['id'] . '/proposal'),
    'contract' => site_url('workflow/certification/' . $client['id'] . '/contract'),
    'audit_program' => site_url('workflow/certification/' . $client['id'] . '/audit-program'),
    'auditor_appointment' => site_url('workflow/certification/' . $client['id'] . '/appointments'),
    'stage1' => $eventRoute($stage1, 'report'),
    'stage2' => $eventRoute($stage2, 'report'),
    'ncr_closure' => $eventRoute($stage2, 'ncr_capa'),
    'tm_file_review' => site_url('workflow/certification/' . $client['id'] . '/technical-review'),
    'certification_decision' => site_url('workflow/certification/' . $client['id'] . '/decision'),
    'gm_final_approval' => site_url('workflow/certification/' . $client['id'] . '/decision'),
    'certificates' => site_url('workflow/certification/' . $client['id'] . '/certificates'),
    'feedback' => site_url('workflow/certification/' . $client['id'] . '/feedback'),
];
$stepStates = [];
foreach ($workflow['steps'] as $step) {
    $stepStates[$step['key']] = $step['state'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= site_url('workflow/certification') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
        Workflow register
    </a>
    <a href="<?= site_url('masters/clients/' . $client['id']) ?>" class="btn btn-outline-primary btn-sm">
        <i class="fa-solid fa-building me-1" aria-hidden="true"></i>
        Client details
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Workflow progress</div>
            <div class="metric-value"><?= esc($workflow['progress']) ?>%</div>
            <div class="text-secondary small"><?= esc($workflow['completed']) ?> of <?= esc($workflow['total']) ?> steps complete</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Selected standards</div>
            <div class="metric-value"><?= esc($records['client_standard_count']) ?></div>
            <div class="text-secondary small">Linked on client profile</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Open NCRs</div>
            <div class="metric-value"><?= esc($records['open_ncr_count']) ?></div>
            <div class="text-secondary small"><?= esc($records['total_ncr_count']) ?> total NCR record(s)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Certificates</div>
            <div class="metric-value"><?= esc($records['certificate_count']) ?></div>
            <div class="text-secondary small">Issued certificate record(s)</div>
        </div>
    </div>
</div>

<div class="panel mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Certification Audit</div>
            <div class="text-secondary small">Initial certification workflow records and audit execution screens.</div>
        </div>
        <span class="badge text-bg-primary">Current file</span>
    </div>
    <div class="workflow-section-meta">
        <div>
            <div class="text-secondary small">Certificate issue date</div>
            <div class="fw-semibold"><?= esc($program['certificate_issue_date'] ?? 'Not set') ?></div>
        </div>
        <div>
            <div class="text-secondary small">Stage 1</div>
            <div class="fw-semibold"><?= $eventLabel($stage1) ?></div>
        </div>
        <div>
            <div class="text-secondary small">Stage 2</div>
            <div class="fw-semibold"><?= $eventLabel($stage2) ?></div>
        </div>
    </div>
    <div class="row g-3">
        <?php foreach ([
            ['Client Application', 'Client', 'Dynamic certification application form and selected standards.', $stepStates['application'] ?? 'pending', $stepLinks['application']],
            ['Application Review', 'Technical / Quality', 'Technical Manager and Quality Manager review.', $stepStates['qm_application_approval'] ?? 'pending', $stepLinks['tm_application_review']],
            ['Proposal', 'Admin / Client', 'Commercial proposal and client response.', $stepStates['proposal'] ?? 'pending', $stepLinks['proposal']],
            ['Contract', 'Admin / Client', 'Signed agreement and certification cycle requirements.', $stepStates['contract'] ?? 'pending', $stepLinks['contract']],
            ['Three-year Audit Program', 'Admin', 'Initial, surveillance and recertification events.', $stepStates['audit_program'] ?? 'pending', $stepLinks['audit_program']],
            ['Auditor Appointment', 'Admin / Technical', 'Competent audit team assignment.', $stepStates['auditor_appointment'] ?? 'pending', $stepLinks['auditor_appointment']],
            ['Stage 1 Audit Plan', 'Auditor', 'Separate Stage 1 plan.', $stage1 === null ? 'pending' : $stepStates['stage1'] ?? 'pending', $eventRoute($stage1, 'plan')],
            ['Stage 1 Audit Report', 'Auditor', 'Stage 1 checklist, notes and report.', $stage1 === null ? 'pending' : $stepStates['stage1'] ?? 'pending', $eventRoute($stage1, 'report')],
            ['Stage 2 Audit Plan', 'Auditor', 'Separate Stage 2 plan.', $stage2 === null ? 'pending' : $stepStates['stage2'] ?? 'pending', $eventRoute($stage2, 'plan')],
            ['Stage 2 Audit Report', 'Auditor', 'Stage 2 checklist, notes and report.', $stage2 === null ? 'pending' : $stepStates['stage2'] ?? 'pending', $eventRoute($stage2, 'report')],
            ['Certification NCR / CAPA', 'Client / Auditor', 'Certification audit NCR and CAPA records.', $stepStates['ncr_closure'] ?? 'pending', $eventRoute($stage2 ?? $stage1, 'ncr_capa')],
            ['Technical Review', 'Technical Manager', 'Certification file technical review.', $stepStates['tm_file_review'] ?? 'pending', $stepLinks['tm_file_review']],
            ['Decision Making', 'Decision Maker', 'Independent certification decision.', $stepStates['certification_decision'] ?? 'pending', $stepLinks['certification_decision']],
            ['Certificate Issue', 'Admin', 'Certificate generation and verification.', $stepStates['certificates'] ?? 'pending', $stepLinks['certificates']],
            ['Client Feedback', 'Quality', 'Client satisfaction and improvement feedback.', $stepStates['feedback'] ?? 'pending', $stepLinks['feedback']],
        ] as [$title, $owner, $note, $state, $url]): ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <?= $workflowCard($title, $owner, $note, $state, $url, $stateClasses) ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="workflow-subsection">
        <div class="workflow-subsection-title">Certification Audit Actions</div>
        <div class="text-secondary small mb-3">Only initial certification records. Stage 1 and Stage 2 plans/reports are opened separately.</div>
        <div class="d-flex flex-wrap gap-2">
            <?= $actionButton('Application review', 'fa-clipboard-check', site_url('workflow/certification/' . $client['id'] . '/review')) ?>
            <?= $actionButton('Proposal', 'fa-file-invoice-dollar', site_url('workflow/certification/' . $client['id'] . '/proposal')) ?>
            <?= $actionButton('Contract', 'fa-file-signature', site_url('workflow/certification/' . $client['id'] . '/contract')) ?>
            <?= $actionButton('Audit program', 'fa-calendar-days', site_url('workflow/certification/' . $client['id'] . '/audit-program')) ?>
            <?= $actionButton('Stage 1 appointment', 'fa-user-check', $eventRoute($stage1, 'appointment')) ?>
            <?= $actionButton('Stage 1 plan', 'fa-list-check', $eventRoute($stage1, 'plan')) ?>
            <?= $actionButton('Stage 1 report', 'fa-clipboard-list', $eventRoute($stage1, 'report')) ?>
            <?= $actionButton('Stage 2 appointment', 'fa-user-check', $eventRoute($stage2, 'appointment')) ?>
            <?= $actionButton('Stage 2 plan', 'fa-list-check', $eventRoute($stage2, 'plan')) ?>
            <?= $actionButton('Stage 2 report', 'fa-clipboard-list', $eventRoute($stage2, 'report')) ?>
            <?= $actionButton('Certification NCR/CAPA', 'fa-screwdriver-wrench', $eventRoute($stage2 ?? $stage1, 'ncr_capa')) ?>
            <?= $actionButton('Technical review', 'fa-user-shield', site_url('workflow/certification/' . $client['id'] . '/technical-review')) ?>
            <?= $actionButton('Decision', 'fa-stamp', site_url('workflow/certification/' . $client['id'] . '/decision')) ?>
            <?= $actionButton('Certificates', 'fa-certificate', site_url('workflow/certification/' . $client['id'] . '/certificates')) ?>
            <?= $actionButton('Feedback', 'fa-comment-dots', site_url('workflow/certification/' . $client['id'] . '/feedback')) ?>
        </div>
    </div>

    <div class="workflow-subsection">
        <div class="workflow-subsection-title">Certification Audit PDF Documents</div>
        <div class="text-secondary small mb-3">Initial certification PDFs, with Stage 1 and Stage 2 audit plans/reports separated.</div>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ([
                'certification_application' => ['Application PDF', 'fa-file-circle-question'],
                'application_review' => ['Application review PDF', 'fa-clipboard-check'],
                'proposal' => ['Proposal PDF', 'fa-file-invoice-dollar'],
                'contract' => ['Contract PDF', 'fa-file-signature'],
                'audit_program' => ['Audit program PDF', 'fa-calendar-days'],
                'feedback' => ['Feedback PDF', 'fa-comment-dots'],
            ] as $key => [$label, $icon]): ?>
                <?= $actionButton($label, $icon, site_url('workflow/certification/' . $client['id'] . '/documents/' . $key), true, 'secondary') ?>
            <?php endforeach; ?>
            <?= $actionButton('Stage 1 plan PDF', 'fa-file-pdf', $eventDocumentUrl($stage1, 'audit_plan'), true, 'danger') ?>
            <?= $actionButton('Stage 1 report PDF', 'fa-file-pdf', $eventDocumentUrl($stage1, 'audit_report'), true, 'danger') ?>
            <?= $actionButton('Stage 2 plan PDF', 'fa-file-pdf', $eventDocumentUrl($stage2, 'audit_plan'), true, 'danger') ?>
            <?= $actionButton('Stage 2 report PDF', 'fa-file-pdf', $eventDocumentUrl($stage2, 'audit_report'), true, 'danger') ?>
            <?= $actionButton('Certification NCR/CAPA PDF', 'fa-file-pdf', $eventDocumentUrl($stage2 ?? $stage1, 'ncr_capa'), true, 'danger') ?>
            <?= $actionButton('Technical review PDF', 'fa-file-pdf', $eventDocumentUrl($stage2 ?? $stage1, 'technical_review'), true, 'danger') ?>
            <?= $actionButton('Decision PDF', 'fa-file-pdf', $eventDocumentUrl($stage2 ?? $stage1, 'decision_report'), true, 'danger') ?>
        </div>
    </div>

    <div class="workflow-subsection">
        <div class="workflow-subsection-title">Certification Audit File Tabs</div>
        <ul class="nav nav-tabs" role="tablist">
            <?php foreach ([
                'application' => 'Application Review',
                'proposal' => 'Proposal',
                'contract' => 'Contract',
                'program' => 'Audit Program',
                'stages' => 'Stage 1 / Stage 2',
                'certificates' => 'Certificate',
                'feedback' => 'Feedback',
            ] as $tab => $label): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $tab === 'application' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#file-<?= esc($tab) ?>" type="button" role="tab">
                        <?= esc($label) ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content pt-3">
            <div class="tab-pane fade show active" id="file-application" role="tabpanel">
                <div class="d-flex justify-content-end gap-2 mb-2">
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/application') ?>">Application form</a>
                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/certification_application') ?>">PDF</a>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/review') ?>">TM/QM review</a>
                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/application_review') ?>">Review PDF</a>
                </div>
                <table class="table table-sm"><tbody>
                <tr><th>Status</th><td><?= esc($records['application_review']['status'] ?? 'Not started') ?></td></tr>
                <tr><th>Risk</th><td><?= esc($records['application_review']['risk_rating'] ?? '') ?></td></tr>
                <tr><th>Recommendation</th><td><?= esc($records['application_review']['recommendation'] ?? '') ?></td></tr>
                </tbody></table>
            </div>
            <div class="tab-pane fade" id="file-proposal" role="tabpanel">
                <div class="d-flex justify-content-end gap-2 mb-2">
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/proposal') ?>">View / Edit</a>
                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/proposal') ?>">PDF</a>
                </div>
                <table class="table table-sm"><tbody>
                <tr><th>Proposal</th><td><?= esc($records['proposal']['proposal_number'] ?? 'Not created') ?></td></tr>
                <tr><th>Status</th><td><?= esc($records['proposal']['status'] ?? '') ?></td></tr>
                <tr><th>Total</th><td><?= esc(isset($records['proposal']['grand_total']) ? number_format((float) $records['proposal']['grand_total'], 2) : '') ?></td></tr>
                </tbody></table>
            </div>
            <div class="tab-pane fade" id="file-contract" role="tabpanel">
                <div class="d-flex justify-content-end gap-2 mb-2">
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/contract') ?>">View / Edit</a>
                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/contract') ?>">PDF</a>
                </div>
                <table class="table table-sm"><tbody>
                <tr><th>Contract</th><td><?= esc($records['contract']['contract_number'] ?? 'Not created') ?></td></tr>
                <tr><th>Status</th><td><?= esc($records['contract']['status'] ?? '') ?></td></tr>
                <tr><th>Signed by</th><td><?= esc($records['contract']['signed_by_name'] ?? '') ?></td></tr>
                </tbody></table>
            </div>
            <div class="tab-pane fade" id="file-program" role="tabpanel">
                <div class="d-flex justify-content-end gap-2 mb-2">
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-program') ?>">View / Edit</a>
                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/audit_program') ?>">PDF</a>
                </div>
                <table class="table table-sm"><tbody>
                <tr><th>Program</th><td><?= esc($records['audit_program']['program_number'] ?? 'Not created') ?></td></tr>
                <tr><th>Issue date</th><td><?= esc($records['audit_program']['certificate_issue_date'] ?? '') ?></td></tr>
                <tr><th>Expiry date</th><td><?= esc($records['audit_program']['certificate_expiry_date'] ?? '') ?></td></tr>
                </tbody></table>
            </div>
            <div class="tab-pane fade" id="file-stages" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Tab</th><th>Audit</th><th>Dates</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach (array_filter($records['audit_events'], static fn (array $auditEvent): bool => in_array($auditEvent['event_type'], ['initial_stage1', 'initial_stage2'], true)) as $auditEvent): ?>
                            <?php $stageLabel = $auditEvent['event_type'] === 'initial_stage1' ? 'Stage 1 Audit Plan / Report' : 'Stage 2 Audit Plan / Report'; ?>
                            <tr>
                                <td><?= esc($stageLabel) ?></td>
                                <td><?= esc($auditEvent['audit_number']) ?></td>
                                <td><?= esc(($auditEvent['planned_start_date'] ?? '') . ' to ' . ($auditEvent['planned_end_date'] ?? '')) ?></td>
                                <td><?= esc($auditEvent['status']) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $auditEvent['id'] . '/file') ?>">View</a>
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $auditEvent['id'] . '/execute') ?>">Edit report</a>
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()">Print</button>
                                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $auditEvent['id'] . '/documents/audit_plan') ?>">Plan PDF</a>
                                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $auditEvent['id'] . '/documents/audit_report') ?>">Report PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="file-certificates" role="tabpanel">
                <div class="d-flex justify-content-end mb-2"><a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/certificates') ?>">View / Edit</a></div>
                <div class="text-secondary">Certificate records are managed in the certificate screen, with one certificate per standard.</div>
            </div>
            <div class="tab-pane fade" id="file-feedback" role="tabpanel">
                <div class="d-flex justify-content-end gap-2 mb-2">
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/feedback') ?>">View / Edit</a>
                    <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/feedback') ?>">PDF</a>
                </div>
                <div class="text-secondary">Client feedback count: <?= esc($records['feedback_count']) ?></div>
            </div>
        </div>
    </div>

    <div class="workflow-subsection">
        <div class="workflow-subsection-title">Certification Audit Execution</div>
        <?php $renderExecutionTable(array_filter([$stage1, $stage2]), 'No certification audit event generated yet.'); ?>
    </div>
</div>

<?php foreach ([
    [
        'title' => 'Surveillance Audit #01',
        'event' => $surveillance1,
        'due' => $surveillance1Due,
        'state' => $surveillance1State,
        'status' => $surveillance1Status,
        'clickable' => $surveillance1Clickable,
        'prefix' => 'Surveillance 1',
        'tabPrefix' => 's1',
    ],
    [
        'title' => 'Surveillance Audit #02',
        'event' => $surveillance2,
        'due' => $surveillance2Due,
        'state' => $surveillance2State,
        'status' => $surveillance2Status,
        'clickable' => $surveillance2Clickable,
        'prefix' => 'Surveillance 2',
        'tabPrefix' => 's2',
    ],
] as $surveillanceSection): ?>
    <?php
    $surveillanceEvent = $surveillanceSection['event'];
    $surveillanceUrl = static fn (string $target): ?string => $surveillanceSection['clickable'] ? $eventRoute($surveillanceEvent, $target) : null;
    ?>
    <div class="panel mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <div class="panel-title mb-1"><?= esc($surveillanceSection['title']) ?></div>
                <div class="text-secondary small">Locked until the due date is reached.</div>
            </div>
            <?php $statusClasses = $stateClasses[$surveillanceSection['state']] ?? $stateClasses['pending']; ?>
            <span class="badge <?= esc($statusClasses[1]) ?>">
                <i class="fa-solid <?= esc($statusClasses[2]) ?> me-1" aria-hidden="true"></i>
                <?= esc($surveillanceSection['status']) ?>
            </span>
        </div>
        <div class="workflow-section-meta">
            <div>
                <div class="text-secondary small">Due date</div>
                <div class="fw-semibold"><?= esc($surveillanceSection['due'] ?? 'Not set') ?></div>
            </div>
            <div>
                <div class="text-secondary small">Audit event</div>
                <div class="fw-semibold"><?= $eventLabel($surveillanceEvent) ?></div>
            </div>
            <div>
                <div class="text-secondary small">Current status</div>
                <div class="fw-semibold"><?= esc($surveillanceSection['status']) ?></div>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ([
                ['Auditor Appointment', 'Admin / Technical', 'Auditor appointment and impartiality check.', 'appointment'],
                [$surveillanceSection['prefix'] . ' Audit Plan', 'Auditor', 'Separate surveillance audit plan.', 'plan'],
                [$surveillanceSection['prefix'] . ' Audit Report', 'Auditor', 'Checklist, predefined notes, NCR and PDF.', 'report'],
                [$surveillanceSection['prefix'] . ' NCR / CAPA', 'Client / Auditor', 'Separate surveillance NCR/CAPA records.', 'ncr_capa'],
                [$surveillanceSection['prefix'] . ' Technical Review', 'Technical Manager', 'Surveillance file technical review.', 'technical_review'],
                [$surveillanceSection['prefix'] . ' Decision Making', 'Decision Maker', 'Maintain certification decision.', 'decision'],
                [$surveillanceSection['prefix'] . ' Completion Status', 'Admin', 'View full surveillance file and status.', 'file'],
            ] as [$title, $owner, $note, $target]): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <?= $workflowCard($title, $owner, $note, $surveillanceSection['state'], $surveillanceUrl($target), $stateClasses) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="workflow-subsection">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <div class="workflow-subsection-title"><?= esc($surveillanceSection['title']) ?> Actions</div>
                    <div class="text-secondary small">Surveillance records are separate from the initial certification audit.</div>
                </div>
                <span class="badge <?= esc($statusClasses[1]) ?>"><?= esc($surveillanceSection['status']) ?></span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?= $actionButton('Auditor appointment', 'fa-user-check', $eventRoute($surveillanceEvent, 'appointment'), $surveillanceSection['clickable']) ?>
                <?= $actionButton('Audit plan', 'fa-list-check', $eventRoute($surveillanceEvent, 'plan'), $surveillanceSection['clickable']) ?>
                <?= $actionButton('Audit report', 'fa-clipboard-list', $eventRoute($surveillanceEvent, 'report'), $surveillanceSection['clickable']) ?>
                <?= $actionButton('NCR / CAPA', 'fa-screwdriver-wrench', $eventRoute($surveillanceEvent, 'ncr_capa'), $surveillanceSection['clickable']) ?>
                <?= $actionButton('Technical review', 'fa-user-shield', $eventRoute($surveillanceEvent, 'technical_review'), $surveillanceSection['clickable']) ?>
                <?= $actionButton('Decision making', 'fa-stamp', $eventRoute($surveillanceEvent, 'decision'), $surveillanceSection['clickable']) ?>
                <?= $actionButton('Full file', 'fa-folder-open', $eventRoute($surveillanceEvent, 'file'), $surveillanceSection['clickable']) ?>
            </div>
        </div>

        <div class="workflow-subsection">
            <div class="workflow-subsection-title"><?= esc($surveillanceSection['title']) ?> PDF Documents</div>
            <div class="text-secondary small mb-3">Separate PDFs for this surveillance stage only.</div>
            <div class="d-flex flex-wrap gap-2">
                <?= $actionButton('Appointment PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'auditor_appointment'), $surveillanceSection['clickable'], 'danger') ?>
                <?= $actionButton('Audit plan PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'audit_plan'), $surveillanceSection['clickable'], 'danger') ?>
                <?= $actionButton('Audit report PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'audit_report'), $surveillanceSection['clickable'], 'danger') ?>
                <?= $actionButton('NCR / CAPA PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'ncr_capa'), $surveillanceSection['clickable'], 'danger') ?>
                <?= $actionButton('Technical review PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'technical_review'), $surveillanceSection['clickable'], 'danger') ?>
                <?= $actionButton('Decision PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'decision_report'), $surveillanceSection['clickable'], 'danger') ?>
            </div>
        </div>

        <div class="workflow-subsection">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="workflow-subsection-title"><?= esc($surveillanceSection['title']) ?> File Tabs</div>
                <span class="badge <?= esc($statusClasses[1]) ?>"><?= esc($surveillanceSection['status']) ?></span>
            </div>
            <ul class="nav nav-tabs" role="tablist">
                <?php foreach ([
                    'summary' => 'Summary',
                    'appointment' => 'Auditor Appointment',
                    'plan' => 'Audit Plan',
                    'report' => 'Audit Report',
                    'capa' => 'NCR / CAPA',
                    'review' => 'Technical Review',
                    'decision' => 'Decision',
                ] as $tab => $label): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $tab === 'summary' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= esc($surveillanceSection['tabPrefix'] . '-' . $tab) ?>" type="button" role="tab">
                            <?= esc($label) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="tab-content pt-3">
                <div class="tab-pane fade show active" id="<?= esc($surveillanceSection['tabPrefix']) ?>-summary" role="tabpanel">
                    <?php $renderEventFileTable($surveillanceEvent, 'Surveillance audit event is not generated yet.', $surveillanceSection['clickable']); ?>
                </div>
                <div class="tab-pane fade" id="<?= esc($surveillanceSection['tabPrefix']) ?>-appointment" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
                        <?= $actionButton('View / Edit', 'fa-user-check', $eventRoute($surveillanceEvent, 'appointment'), $surveillanceSection['clickable']) ?>
                        <?= $actionButton('PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'auditor_appointment'), $surveillanceSection['clickable'], 'danger') ?>
                    </div>
                    <div class="text-secondary">Auditor appointment, competence, impartiality and conflict checks for this surveillance stage.</div>
                </div>
                <div class="tab-pane fade" id="<?= esc($surveillanceSection['tabPrefix']) ?>-plan" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
                        <?= $actionButton('View / Edit', 'fa-list-check', $eventRoute($surveillanceEvent, 'plan'), $surveillanceSection['clickable']) ?>
                        <?= $actionButton('PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'audit_plan'), $surveillanceSection['clickable'], 'danger') ?>
                    </div>
                    <div class="text-secondary">Separate surveillance audit plan with process, timing, auditor role and auditor name.</div>
                </div>
                <div class="tab-pane fade" id="<?= esc($surveillanceSection['tabPrefix']) ?>-report" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
                        <?= $actionButton('Execute / View', 'fa-clipboard-list', $eventRoute($surveillanceEvent, 'report'), $surveillanceSection['clickable']) ?>
                        <?= $actionButton('PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'audit_report'), $surveillanceSection['clickable'], 'danger') ?>
                    </div>
                    <div class="text-secondary">Separate surveillance checklist, conformity notes, NCR decisions and report submission date.</div>
                </div>
                <div class="tab-pane fade" id="<?= esc($surveillanceSection['tabPrefix']) ?>-capa" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
                        <?= $actionButton('View / Edit', 'fa-screwdriver-wrench', $eventRoute($surveillanceEvent, 'ncr_capa'), $surveillanceSection['clickable']) ?>
                        <?= $actionButton('PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'ncr_capa'), $surveillanceSection['clickable'], 'danger') ?>
                    </div>
                    <div class="text-secondary">Separate surveillance NCR, correction, root cause, corrective action, evidence and closure records.</div>
                </div>
                <div class="tab-pane fade" id="<?= esc($surveillanceSection['tabPrefix']) ?>-review" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
                        <?= $actionButton('View / Edit', 'fa-user-shield', $eventRoute($surveillanceEvent, 'technical_review'), $surveillanceSection['clickable']) ?>
                        <?= $actionButton('PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'technical_review'), $surveillanceSection['clickable'], 'danger') ?>
                    </div>
                    <div class="text-secondary">Technical Manager review for this surveillance audit file only.</div>
                </div>
                <div class="tab-pane fade" id="<?= esc($surveillanceSection['tabPrefix']) ?>-decision" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-end gap-2 mb-2">
                        <?= $actionButton('View / Edit', 'fa-stamp', $eventRoute($surveillanceEvent, 'decision'), $surveillanceSection['clickable']) ?>
                        <?= $actionButton('PDF', 'fa-file-pdf', $eventDocumentUrl($surveillanceEvent, 'decision_report'), $surveillanceSection['clickable'], 'danger') ?>
                    </div>
                    <div class="text-secondary">Maintain certification decision for this surveillance audit stage.</div>
                </div>
            </div>
        </div>

        <div class="workflow-subsection">
            <div class="workflow-subsection-title"><?= esc($surveillanceSection['title']) ?> Execution</div>
            <?php $renderExecutionTable(array_filter([$surveillanceEvent]), 'No surveillance audit event generated yet.'); ?>
        </div>
    </div>
<?php endforeach; ?>

<div class="panel mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Recertification / Expiry Information</div>
            <div class="text-secondary small">Certification cycle expiry and recertification planning.</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="<?= $recertification === null ? site_url('workflow/certification/' . $client['id'] . '/audit-program') : site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $recertification['id'] . '/file') ?>">
            <i class="fa-solid fa-folder-open me-1" aria-hidden="true"></i>
            Open
        </a>
    </div>
    <div class="workflow-section-meta">
        <div>
            <div class="text-secondary small">Certificate expiry date</div>
            <div class="fw-semibold"><?= esc($expiryDate ?? 'Not set') ?></div>
        </div>
        <div>
            <div class="text-secondary small">Recertification audit</div>
            <div class="fw-semibold"><?= $eventLabel($recertification) ?></div>
        </div>
        <div>
            <div class="text-secondary small">Recertification status</div>
            <div class="fw-semibold"><?= esc($recertification['status'] ?? 'Not generated') ?></div>
        </div>
    </div>
    <div class="workflow-subsection">
        <div class="workflow-subsection-title">Recertification Execution</div>
        <?php $renderExecutionTable(array_filter([$recertification]), 'No recertification audit event generated yet.'); ?>
    </div>
</div>

<div class="panel mb-3">
    <div class="panel-title">Responsible names</div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="text-secondary small">Technical Manager</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['technical_manager'] ?? null)) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary small">Quality Manager</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['quality_manager'] ?? null)) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary small">Auditor / audit team</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['all_auditors'] ?? [])) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary small">Proposal prepared by</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['proposal_created_by'] ?? null)) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary small">Contract signed by</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['contract_signed_by'] ?? null)) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary small">Technical Reviewer</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['technical_reviewer'] ?? null)) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary small">Decision Maker</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['decision_maker'] ?? null)) ?></div>
        </div>
        <div class="col-md-4">
            <div class="text-secondary small">General Manager</div>
            <div class="fw-semibold"><?= esc($nameText($responsible['general_manager'] ?? null)) ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="panel h-100">
            <div class="panel-title">Commercial and approval records</div>
            <table class="table table-sm">
                <tbody>
                <tr>
                    <th>Application review</th>
                    <td><?= esc($records['application_review']['status'] ?? 'Not started') ?></td>
                </tr>
                <tr>
                    <th>Proposal</th>
                    <td><?= esc(($records['proposal']['proposal_number'] ?? 'Not created') . (($records['proposal']['status'] ?? '') ? ' - ' . $records['proposal']['status'] : '')) ?></td>
                </tr>
                <tr>
                    <th>Contract</th>
                    <td><?= esc(($records['contract']['contract_number'] ?? 'Not created') . (($records['contract']['status'] ?? '') ? ' - ' . $records['contract']['status'] : '')) ?></td>
                </tr>
                <tr>
                    <th>Decision</th>
                    <td><?= esc(($records['certification_decision']['decision'] ?? 'Not recorded') . (($records['certification_decision']['status'] ?? '') ? ' - ' . $records['certification_decision']['status'] : '')) ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="panel h-100">
            <div class="panel-title">Audit records</div>
            <table class="table table-sm">
                <tbody>
                <tr>
                    <th>Audit program</th>
                    <td><?= esc(($records['audit_program']['program_number'] ?? 'Not created') . (($records['audit_program']['status'] ?? '') ? ' - ' . $records['audit_program']['status'] : '')) ?></td>
                </tr>
                <tr>
                    <th>Stage 1</th>
                    <td><?= esc(($records['stage1']['audit_number'] ?? 'Not planned') . (($records['stage1']['status'] ?? '') ? ' - ' . $records['stage1']['status'] : '')) ?></td>
                </tr>
                <tr>
                    <th>Stage 2</th>
                    <td><?= esc(($records['stage2']['audit_number'] ?? 'Not planned') . (($records['stage2']['status'] ?? '') ? ' - ' . $records['stage2']['status'] : '')) ?></td>
                </tr>
                <tr>
                    <th>Technical file review</th>
                    <td><?= esc($records['technical_review']['status'] ?? 'Not started') ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
