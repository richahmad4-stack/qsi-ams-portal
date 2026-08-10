<?= $this->extend('layouts/main') ?>

<?php
$eventLabel = preg_replace('/\bStage\s*(\d)\b/i', 'Stage $1', ucwords(str_replace('_', ' ', (string) $event['event_type'])));
$pdfBase = 'workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/documents/';
$tabId = static fn (string $key): string => 'tab-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($key));
$currentRoles = (array) session()->get('role_codes');
$regulatedReadOnly = in_array('compliance_auditor', $currentRoles, true)
    && ! in_array('super_admin', $currentRoles, true);
$decodeJson = static fn (?string $json): array => $json !== null && trim($json) !== ''
    ? (json_decode($json, true) ?: [])
    : [];
$jsonRows = static function (array $payload): array {
    $rows = [];
    foreach ($payload as $key => $value) {
        if (in_array($key, ['checklist_rows', 'system_prepared', 'automation_mode', 'created_from', 'event_type', 'decision_basis'], true)) {
            continue;
        }

        $label = $key === 'application_id' ? 'Application reference' : ucwords(str_replace('_', ' ', (string) $key));
        if (is_bool($value) || str_ends_with((string) $key, '_confirmed')) {
            $rows[$label] = (bool) $value ? 'Yes' : 'No';
            continue;
        }

        $rows[$label] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : (string) $value;
    }

    return $rows;
};
$technicalPayload = $decodeJson($technicalReview['checklist_payload'] ?? null);
$decisionPayload = $decodeJson($decision['decision_payload'] ?? null);
?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
        Client file
    </a>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()">
            <i class="fa-solid fa-print me-1" aria-hidden="true"></i>
            Print
        </button>
        <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'auditor_appointment') ?>">
            <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
            Appointment PDF
        </a>
        <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'audit_plan') ?>">
            <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
            Audit plan PDF
        </a>
        <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'audit_report') ?>">
            <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
            Audit report PDF
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Stage</div>
            <div class="fw-semibold"><?= esc($eventLabel) ?></div>
            <div class="text-secondary small"><?= esc($event['audit_number']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Dates</div>
            <div class="fw-semibold"><?= esc($event['planned_start_date']) ?></div>
            <div class="text-secondary small">to <?= esc($event['planned_end_date']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Report notes</div>
            <div class="metric-value"><?= esc(count($sections)) ?></div>
            <div class="text-secondary small">Checklist/report entries</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">NCR / CAPA</div>
            <div class="metric-value"><?= esc(count($ncrs)) ?> / <?= esc(count($capas)) ?></div>
            <div class="text-secondary small"><?= esc($event['status']) ?></div>
        </div>
    </div>
</div>

<section class="panel">
    <ul class="nav nav-tabs" role="tablist">
        <?php foreach (['Appointment', 'Audit Plan', 'Audit Report', 'NCR / CAPA', 'Technical Review', 'Decision', 'PDFs'] as $index => $label): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#<?= esc($tabId($label)) ?>" type="button" role="tab">
                    <?= esc($label) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content pt-3">
        <div class="tab-pane fade show active" id="<?= esc($tabId('Appointment')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/appointments') ?>">
                    <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>
                    Edit appointments
                </a>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'auditor_appointment') ?>">
                    <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
                    Generate PDF
                </a>
            </div>
            <table class="table table-sm">
                <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Appointed</th></tr></thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= esc($appointment['full_name']) ?></td>
                        <td><?= esc(str_replace('_', ' ', $appointment['appointment_role'])) ?></td>
                        <td><?= esc($appointment['status']) ?></td>
                        <td><?= esc($appointment['appointed_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($appointments === []): ?><tr><td colspan="4" class="text-secondary">No appointment recorded for this stage.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('Audit Plan')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-plan') ?>">Edit plan</a>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'audit_plan') ?>">Generate PDF</a>
            </div>
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Time</th><th>Activity</th><th>Process</th><th>Auditor</th></tr></thead>
                <tbody>
                <?php foreach ($planItems as $item): ?>
                    <tr>
                        <td><?= esc($item['audit_date']) ?></td>
                        <td><?= esc(substr((string) $item['start_time'], 0, 5) . ' - ' . substr((string) $item['end_time'], 0, 5)) ?></td>
                        <td><?= esc(str_replace('_', ' ', $item['activity_type'])) ?></td>
                        <td><?= esc($item['process_name'] ?: $item['department']) ?></td>
                        <td><?= esc($item['auditor_name'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($planItems === []): ?><tr><td colspan="5" class="text-secondary">No timetable recorded for this stage.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('Audit Report')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <?php if (! $regulatedReadOnly): ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/execute') ?>">Edit report</a>
                <?php endif; ?>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'audit_report') ?>">Generate PDF</a>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="text-secondary small">Report status</div>
                        <div class="fw-semibold"><?= esc(str_replace('_', ' ', (string) ($report['status'] ?? 'draft'))) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="text-secondary small">Report submission date</div>
                        <div class="fw-semibold"><?= esc((string) ($report['submitted_at'] ?? 'Not submitted')) ?></div>
                    </div>
                </div>
            </div>
            <?php foreach ($sections as $index => $section): ?>
                <article class="border rounded mb-3 overflow-hidden">
                    <div class="bg-light border-bottom p-3 d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <div class="fw-semibold"><?= esc($section['section_title'] ?? ('Checklist item ' . ($index + 1))) ?></div>
                            <div class="text-secondary small"><?= esc($section['requirement_family'] ?? 'Audit requirement') ?></div>
                        </div>
                        <div class="d-flex flex-wrap gap-1 align-items-start">
                            <span class="badge text-bg-<?= (int) ($section['auditor_confirmed'] ?? 0) === 1 ? 'success' : 'warning' ?>">
                                <?= (int) ($section['auditor_confirmed'] ?? 0) === 1 ? 'Auditor confirmed' : 'Confirmation pending' ?>
                            </span>
                            <span class="badge text-bg-light border"><?= esc(ucwords(str_replace('_', ' ', (string) ($section['finding_type'] ?? $section['section_key'] ?? 'note')))) ?></span>
                        </div>
                    </div>
                    <div class="p-3">
                        <?php if (! empty($section['mappings'])): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>Standard</th><th>Clause</th><th>Clause title</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($section['mappings'] as $mapping): ?>
                                        <tr>
                                            <td><?= esc($mapping['standard_code']) ?></td>
                                            <td><?= esc($mapping['clause_reference']) ?></td>
                                            <td><?= esc($mapping['clause_title']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($section['audit_question'])): ?>
                            <div class="small text-secondary mb-1">Audit requirement / question</div>
                            <div class="mb-3"><?= nl2br(esc($section['audit_question'])) ?></div>
                        <?php endif; ?>
                        <div class="small text-secondary mb-1">Audit response / conformity statement</div>
                        <div class="mb-3"><?= nl2br(esc($section['response_text'] ?? $section['section_content'] ?? '')) ?></div>
                        <?php if (! empty($section['objective_evidence'])): ?>
                            <div class="small text-secondary mb-1">Objective evidence</div>
                            <div class="mb-3"><?= nl2br(esc($section['objective_evidence'])) ?></div>
                        <?php endif; ?>
                        <?php if (! empty($section['confirmed_by_name']) || ! empty($section['confirmed_at'])): ?>
                            <div class="small text-secondary">Confirmed by <?= esc($section['confirmed_by_name'] ?? 'auditor') ?><?= ! empty($section['confirmed_at']) ? ' on ' . esc($section['confirmed_at']) : '' ?></div>
                        <?php endif; ?>
                        <?php if (! empty($section['ncrs'])): ?>
                            <div class="mt-3 border-start border-danger ps-3">
                                <div class="fw-semibold mb-1">Linked NCR</div>
                                <?php foreach ($section['ncrs'] as $ncr): ?>
                                    <div><?= esc(($ncr['ncr_number'] ?? '') . ' - ' . strtoupper((string) ($ncr['classification'] ?? '')) . ' - ' . ($ncr['status'] ?? '')) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($sections === []): ?><div class="text-secondary">No checklist/report entries saved for this stage.</div><?php endif; ?>

            <?php if (($supplementaryNotes ?? []) !== []): ?>
                <h3 class="h6 mt-4">Supplementary notes</h3>
                <table class="table table-sm">
                    <thead><tr><th>Type</th><th>Title</th><th>Content</th></tr></thead>
                    <tbody>
                    <?php foreach ($supplementaryNotes as $note): ?>
                        <tr><td><?= esc(str_replace('_', ' ', $note['section_key'])) ?></td><td><?= esc($note['section_title']) ?></td><td><?= nl2br(esc($note['section_content'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('NCR / CAPA')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <?php if (! $regulatedReadOnly): ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/execute') ?>">Manage NCRs</a>
                <?php endif; ?>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'ncr_capa') ?>">Generate PDF</a>
            </div>
            <h3 class="h6">NCRs</h3>
            <table class="table table-sm">
                <thead><tr><th>NCR</th><th>Class</th><th>Status</th><th>Finding</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($ncrs as $ncr): ?>
                    <tr>
                        <td><?= esc($ncr['ncr_number']) ?></td>
                        <td><?= esc($ncr['classification']) ?></td>
                        <td><?= esc($ncr['status']) ?></td>
                        <td><?= esc(mb_strimwidth($ncr['finding'], 0, 160, '...')) ?></td>
                        <td class="text-end">
                            <?php if (! $regulatedReadOnly): ?>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#capaForm<?= esc($ncr['id']) ?>">
                                    Create CAPA
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (! $regulatedReadOnly): ?>
                    <tr class="collapse" id="capaForm<?= esc($ncr['id']) ?>">
                        <td colspan="5">
                            <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/capas') ?>" class="border rounded p-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="ncr_id" value="<?= esc($ncr['id']) ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Issue / NCR finding</label>
                                        <textarea class="form-control" name="issue" rows="3" required><?= esc($ncr['finding']) ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Immediate correction</label>
                                        <textarea class="form-control" name="immediate_correction" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Root cause</label>
                                        <textarea class="form-control" name="root_cause" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Corrective action</label>
                                        <textarea class="form-control" name="corrective_action" rows="3" required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Preventive action</label>
                                        <textarea class="form-control" name="preventive_action" rows="2"></textarea>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Responsible person</label>
                                        <input class="form-control" name="responsible_person" value="<?= esc($ncr['responsible_person'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Target date</label>
                                        <input class="form-control" type="date" name="target_date" value="<?= esc($ncr['target_date'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Evidence uploaded / reference</label>
                                        <textarea class="form-control" name="evidence_reference" rows="2" placeholder="File name, upload reference, or evidence location"></textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button class="btn btn-primary btn-sm" type="submit">Save CAPA</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($ncrs === []): ?><tr><td colspan="5" class="text-secondary">No NCRs recorded.</td></tr><?php endif; ?>
                </tbody>
            </table>
            <h3 class="h6 mt-3">CAPAs</h3>
            <table class="table table-sm">
                <thead><tr><th>CAPA</th><th>NCR</th><th>Status</th><th>Target</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($capas as $capa): ?>
                    <tr>
                        <td><?= esc($capa['capa_number']) ?></td>
                        <td><?= esc($capa['ncr_number']) ?></td>
                        <td><?= esc($capa['status']) ?></td>
                        <td><?= esc($capa['target_date']) ?></td>
                        <td>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#capaDetails<?= esc($capa['id']) ?>">
                                View / close
                            </button>
                        </td>
                    </tr>
                    <tr class="collapse" id="capaDetails<?= esc($capa['id']) ?>">
                        <td colspan="5">
                            <div class="border rounded p-3">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3"><strong>NCR number:</strong><br><?= esc($capa['ncr_number']) ?></div>
                                    <div class="col-md-3"><strong>Clause:</strong><br><?= esc(trim(($capa['clause_number'] ?? '') . ' ' . ($capa['clause_title'] ?? ''))) ?></div>
                                    <div class="col-md-3"><strong>Classification:</strong><br><?= esc($capa['ncr_classification'] ?? '') ?></div>
                                    <div class="col-md-3"><strong>Target:</strong><br><?= esc($capa['target_date'] ?? '') ?></div>
                                    <div class="col-md-6"><strong>Requirement:</strong><br><?= nl2br(esc($capa['ncr_requirement'] ?? '')) ?></div>
                                    <div class="col-md-6"><strong>Nonconformity statement:</strong><br><?= nl2br(esc($capa['ncr_finding'] ?? $capa['issue'])) ?></div>
                                    <div class="col-md-6"><strong>Immediate correction:</strong><br><?= nl2br(esc($capa['immediate_correction'] ?? '')) ?></div>
                                    <div class="col-md-6"><strong>Root cause:</strong><br><?= nl2br(esc($capa['root_cause'] ?? '')) ?></div>
                                    <div class="col-md-6"><strong>Corrective action:</strong><br><?= nl2br(esc($capa['corrective_action'] ?? '')) ?></div>
                                    <div class="col-md-6"><strong>Preventive action:</strong><br><?= nl2br(esc($capa['preventive_action'] ?? '')) ?></div>
                                    <div class="col-md-6"><strong>Evidence uploaded / reference:</strong><br><?= nl2br(esc($capa['evidence_reference'] ?? '')) ?></div>
                                    <div class="col-md-3"><strong>Responsible:</strong><br><?= esc($capa['responsible_person'] ?? '') ?></div>
                                    <div class="col-md-3"><strong>Closed:</strong><br><?= esc($capa['closed_at'] ?? '') ?></div>
                                </div>
                                <?php if (! $regulatedReadOnly && ! in_array($capa['status'], ['closed', 'verified_closed'], true)): ?>
                                    <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/capas/' . $capa['id'] . '/close') ?>" class="row g-3">
                                        <?= csrf_field() ?>
                                        <div class="col-md-6">
                                            <label class="form-label">Verification</label>
                                            <textarea class="form-control" name="verification" rows="3" required></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Effectiveness</label>
                                            <textarea class="form-control" name="effectiveness" rows="3" required></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Evidence uploaded / reference</label>
                                            <textarea class="form-control" name="evidence_reference" rows="2"><?= esc($capa['evidence_reference'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Closure notes</label>
                                            <textarea class="form-control" name="closure_notes" rows="2"></textarea>
                                        </div>
                                        <div class="col-12 text-end">
                                            <button class="btn btn-success btn-sm" type="submit">Close CAPA</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <div class="col-md-6"><strong>Verification:</strong><br><?= nl2br(esc($capa['verification'] ?? '')) ?></div>
                                        <div class="col-md-6"><strong>Effectiveness:</strong><br><?= nl2br(esc($capa['effectiveness'] ?? '')) ?></div>
                                        <div class="col-12"><strong>Evidence uploaded / reference:</strong><br><?= nl2br(esc($capa['evidence_reference'] ?? '')) ?></div>
                                        <div class="col-12"><strong>Closure notes:</strong><br><?= nl2br(esc($capa['closure_notes'] ?? '')) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($capas === []): ?><tr><td colspan="5" class="text-secondary">No CAPA records linked to this stage.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('Technical Review')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <?php if (! $regulatedReadOnly): ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/technical-review?event_id=' . $event['id']) ?>">Edit review</a>
                <?php endif; ?>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'technical_review') ?>">Generate PDF</a>
            </div>
            <?php if ($technicalReview === null): ?>
                <div class="text-secondary">No technical review recorded for this stage.</div>
            <?php else: ?>
                <table class="table table-sm">
                    <tbody>
                    <tr><th>Status</th><td><?= esc($technicalReview['status']) ?></td></tr>
                    <tr><th>Recommendation</th><td><?= esc(str_replace('_', ' ', (string) $technicalReview['recommendation'])) ?></td></tr>
                    <tr><th>Reviewed at</th><td><?= esc($technicalReview['reviewed_at']) ?></td></tr>
                    <?php foreach ($jsonRows($technicalPayload) as $label => $value): ?>
                        <tr><th><?= esc($label) ?></th><td><?= nl2br(esc($value)) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (($technicalPayload['checklist_rows'] ?? []) !== []): ?>
                    <h3 class="h6 mt-4">Technical Review checklist</h3>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead><tr><th>Reference</th><th>Review area</th><th>Requirement</th><th>Result</th><th>Evidence reviewed</th></tr></thead>
                            <tbody>
                            <?php foreach ($technicalPayload['checklist_rows'] as $row): ?>
                                <tr>
                                    <td><?= esc($row['ref'] ?? '') ?></td>
                                    <td><?= esc($row['group'] ?? '') ?></td>
                                    <td><?= esc($row['requirement'] ?? '') ?></td>
                                    <td><?= esc($row['result'] ?? '') ?></td>
                                    <td><?= esc($row['evidence'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('Decision')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <?php if (! $regulatedReadOnly): ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/decision?event_id=' . $event['id']) ?>">Edit decision</a>
                <?php endif; ?>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'decision_report') ?>">Generate PDF</a>
            </div>
            <?php if ($decision === null): ?>
                <div class="text-secondary">No decision recorded for this stage.</div>
            <?php else: ?>
                <table class="table table-sm">
                    <tbody>
                    <tr><th>Decision</th><td><?= esc(str_replace('_', ' ', $decision['decision'])) ?></td></tr>
                    <tr><th>Status</th><td><?= esc($decision['status']) ?></td></tr>
                    <tr><th>Reason</th><td><?= esc($decision['reason']) ?></td></tr>
                    <tr><th>Decision maker</th><td><?= esc($decision['decision_maker_name'] ?? '') ?></td></tr>
                    <tr><th>Decided at</th><td><?= esc($decision['decided_at'] ?? '') ?></td></tr>
                    <tr><th>GM approval</th><td><?= esc($decision['gm_approved_at'] ?? 'Not recorded') ?></td></tr>
                    <?php foreach ($jsonRows($decisionPayload) as $label => $value): ?>
                        <tr><th><?= esc($label) ?></th><td><?= nl2br(esc($value)) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (($decisionPayload['checklist_rows'] ?? []) !== []): ?>
                    <h3 class="h6 mt-4">Decision Making checklist</h3>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead><tr><th>Reference</th><th>Decision area</th><th>Requirement</th><th>Result</th><th>Evidence reviewed</th></tr></thead>
                            <tbody>
                            <?php foreach ($decisionPayload['checklist_rows'] as $row): ?>
                                <tr>
                                    <td><?= esc($row['ref'] ?? '') ?></td>
                                    <td><?= esc($row['group'] ?? '') ?></td>
                                    <td><?= esc($row['requirement'] ?? '') ?></td>
                                    <td><?= esc($row['result'] ?? '') ?></td>
                                    <td><?= esc($row['evidence'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('PDFs')) ?>" role="tabpanel">
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'auditor_appointment') ?>">Auditor appointment PDF</a>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'audit_plan') ?>">Audit plan PDF</a>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'audit_report') ?>">Audit report PDF</a>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'ncr_capa') ?>">NCR / CAPA PDF</a>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'technical_review') ?>">Technical review PDF</a>
                <a class="btn btn-outline-danger btn-sm" href="<?= site_url($pdfBase . 'decision_report') ?>">Decision PDF</a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.location.hash) {
        return;
    }

    const trigger = document.querySelector(`[data-bs-target="${window.location.hash}"]`);
    if (trigger && window.bootstrap) {
        window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
});
</script>
<?= $this->endSection() ?>
