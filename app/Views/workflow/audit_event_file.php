<?= $this->extend('layouts/main') ?>

<?php
$eventLabel = ucwords(str_replace('_', ' ', (string) $event['event_type']));
$pdfBase = 'workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/documents/';
$tabId = static fn (string $key): string => 'tab-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($key));
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
                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/execute') ?>">Edit report</a>
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
            <table class="table table-sm">
                <thead><tr><th>Type</th><th>Clause</th><th>Content</th></tr></thead>
                <tbody>
                <?php foreach ($sections as $section): ?>
                    <tr>
                        <td><?= esc(str_replace('_', ' ', $section['section_key'])) ?></td>
                        <td><?= esc(trim(($section['clause_number'] ?? '') . ' ' . ($section['clause_title'] ?? ''))) ?></td>
                        <td><?= esc(mb_strimwidth($section['section_content'], 0, 180, '...')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sections === []): ?><tr><td colspan="3" class="text-secondary">No checklist/report entries saved for this stage.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('NCR / CAPA')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/execute') ?>">Manage NCRs</a>
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
                            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#capaForm<?= esc($ncr['id']) ?>">
                                Create CAPA
                            </button>
                        </td>
                    </tr>
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
                                <?php if (! in_array($capa['status'], ['closed', 'verified_closed'], true)): ?>
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
                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/technical-review?event_id=' . $event['id']) ?>">Edit review</a>
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
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="<?= esc($tabId('Decision')) ?>" role="tabpanel">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/decision?event_id=' . $event['id']) ?>">Edit decision</a>
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
                    </tbody>
                </table>
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
