<?= $this->extend('layouts/main') ?>

<?php
$sectionsByClause = [];
$conformityByClause = [];
$manualSections = [];
$ncrsByClause = [];
$clausesByStandard = [];
$standardLabels = [];

foreach ($clientStandards ?? [] as $standard) {
    $standardLabels[] = $standard['standard_code'] ?? '';
}

foreach ($sections as $section) {
    $clauseId = (int) ($section['clause_library_id'] ?? 0);
    if ($clauseId > 0) {
        if ($section['section_key'] === 'conformity' && ! isset($conformityByClause[$clauseId])) {
            $conformityByClause[$clauseId] = $section;
        } else {
            $sectionsByClause[$clauseId][] = $section;
            $manualSections[] = $section;
        }
    } else {
        $manualSections[] = $section;
    }
}

foreach ($ncrs as $ncr) {
    $clauseId = (int) ($ncr['clause_library_id'] ?? 0);
    if ($clauseId > 0) {
        $ncrsByClause[$clauseId][] = $ncr;
    }
}

foreach ($clauses as $clause) {
    $code = (string) ($clause['standard_code'] ?? 'Standard');
    $clausesByStandard[$code][] = $clause;
    if (! in_array($code, $standardLabels, true)) {
        $standardLabels[] = $code;
    }
}

$eventLabel = ucwords(str_replace('_', ' ', (string) $event['event_type']));
?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
        Back to workflow
    </a>
    <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/complete') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-sm" type="submit">
            <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>
            Mark completed
        </button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Client</div>
            <div class="fw-semibold"><?= esc($client['company']) ?></div>
            <div class="text-secondary small"><?= esc($client['client_code'] ?? '') ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Audit</div>
            <div class="fw-semibold"><?= esc($event['audit_number']) ?></div>
            <div class="text-secondary small"><?= esc($eventLabel) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Schedule</div>
            <div class="fw-semibold"><?= esc($event['planned_start_date'] . ' to ' . $event['planned_end_date']) ?></div>
            <div class="text-secondary small"><?= esc($event['status']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric">
            <div class="text-secondary small">Report status</div>
            <div class="fw-semibold"><?= esc($report['status'] ?? 'draft') ?></div>
            <div class="text-secondary small"><?= esc(count($sections)) ?> auto note(s), <?= esc(count($manualSections)) ?> manual note(s), <?= esc(count($ncrs)) ?> NCR(s)</div>
        </div>
    </div>
</div>

<section class="panel mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="panel-title mb-1">Full audit report</div>
            <div class="text-secondary small">Review each clause, record conformity or observations, then raise Minor or Major NC where needed.</div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <?php foreach ($standardLabels as $standardLabel): ?>
                <?php if ($standardLabel !== ''): ?>
                    <span class="badge text-bg-light border"><?= esc($standardLabel) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-5">
            <div class="small text-secondary mb-1">Audit team</div>
            <?php if (($auditTeam ?? []) === []): ?>
                <div class="text-secondary">No auditor appointment recorded for this audit event.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($auditTeam as $member): ?>
                            <tr>
                                <td><?= esc($member['full_name']) ?></td>
                                <td><?= esc(ucwords(str_replace('_', ' ', $member['appointment_role']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-7">
            <div class="small text-secondary mb-1">Audit plan</div>
            <?php if (($planItems ?? []) === []): ?>
                <div class="text-secondary">No audit plan timetable recorded for this audit event.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Activity</th>
                            <th>Process</th>
                            <th>Auditor</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($planItems as $item): ?>
                            <tr>
                                <td><?= esc($item['audit_date']) ?></td>
                                <td><?= esc(substr((string) $item['start_time'], 0, 5) . ' - ' . substr((string) $item['end_time'], 0, 5)) ?></td>
                                <td><?= esc(ucwords(str_replace('_', ' ', $item['activity_type']))) ?></td>
                                <td><?= esc($item['process_name'] ?: $item['department']) ?></td>
                                <td><?= esc($item['auditor_name'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($clausesByStandard === []): ?>
    <section class="panel">
        <div class="panel-title">Audit checklist</div>
        <div class="alert alert-warning mb-0">No clauses are available for this client's requested standards. Add standards to the client, then seed or maintain the Clause Library.</div>
    </section>
<?php else: ?>
    <?php $standardIndex = 0; ?>
    <?php foreach ($clausesByStandard as $standardCode => $standardClauses): ?>
        <?php $standardIndex++; ?>
        <section class="panel mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="panel-title mb-1"><?= esc($standardCode) ?> checklist</div>
                    <div class="text-secondary small"><?= esc(count($standardClauses)) ?> clause(s) available for this audit.</div>
                </div>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#standardClauses<?= esc($standardIndex) ?>">
                    <i class="fa-solid fa-list-check me-1" aria-hidden="true"></i>
                    Show / hide
                </button>
            </div>

            <div class="collapse show" id="standardClauses<?= esc($standardIndex) ?>">
                <div class="accordion" id="clauseAccordion<?= esc($standardIndex) ?>">
                    <?php foreach ($standardClauses as $offset => $clause): ?>
                        <?php
                        $clauseId = (int) $clause['id'];
                        $clauseSections = $sectionsByClause[$clauseId] ?? [];
                        $conformitySection = $conformityByClause[$clauseId] ?? null;
                        $clauseNcrs = $ncrsByClause[$clauseId] ?? [];
                        $smartConformityNote = $smartConformityNotes[$clauseId] ?? ($clause['predefined_conformity_note'] ?? '');
                        $collapseId = 'clause' . $standardIndex . '_' . $clauseId;
                        $sectionTitle = trim((string) $clause['standard_code'] . ' ' . (string) $clause['clause_number'] . ' - ' . (string) $clause['clause_title']);
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $offset === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($collapseId) ?>">
                                    <span class="me-2 fw-semibold"><?= esc($clause['clause_number']) ?></span>
                                    <span><?= esc($clause['clause_title']) ?></span>
                                    <?php if ($conformitySection !== null): ?>
                                        <span class="badge text-bg-success ms-2">Conformity note</span>
                                    <?php endif; ?>
                                    <?php if ($clauseSections !== []): ?>
                                        <span class="badge text-bg-info ms-2"><?= esc(count($clauseSections)) ?> extra note(s)</span>
                                    <?php endif; ?>
                                    <?php if ($clauseNcrs !== []): ?>
                                        <span class="badge text-bg-danger ms-2"><?= esc(count($clauseNcrs)) ?> NCR</span>
                                    <?php endif; ?>
                                </button>
                            </h2>
                            <div id="<?= esc($collapseId) ?>" class="accordion-collapse collapse <?= $offset === 0 ? 'show' : '' ?>" data-bs-parent="#clauseAccordion<?= esc($standardIndex) ?>">
                                <div class="accordion-body">
                                    <div class="row g-3">
                                        <div class="col-xl-5">
                                            <div class="small text-secondary mb-1">Requirement</div>
                                            <div class="mb-3"><?= nl2br(esc($clause['requirement'])) ?></div>

                                            <div class="small text-secondary mb-1">Evidence examples</div>
                                            <div class="mb-3"><?= nl2br(esc($clause['evidence_examples'] ?? '')) ?></div>

                                            <div class="small text-secondary mb-1">Auditor guidance</div>
                                            <div class="mb-3"><?= nl2br(esc($clause['auditor_guidance'] ?? '')) ?></div>

                                            <div class="d-flex flex-wrap gap-2">
                                                <?php if (! empty($clause['risk_rating'])): ?>
                                                    <span class="badge text-bg-light border">Risk: <?= esc($clause['risk_rating']) ?></span>
                                                <?php endif; ?>
                                                <?php if (! empty($clause['stage_applicability'])): ?>
                                                    <span class="badge text-bg-light border"><?= esc($clause['stage_applicability']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="col-xl-7">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="small text-secondary mb-1">Predefined notes</div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm align-middle mb-0">
                                                            <tbody>
                                                            <tr>
                                                                <th class="text-nowrap">Conformity</th>
                                                                <td><?= esc($clause['predefined_conformity_note'] ?? '') ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-nowrap">Positive</th>
                                                                <td><?= esc($clause['positive_finding'] ?? '') ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-nowrap">OFI</th>
                                                                <td><?= esc($clause['opportunity_for_improvement'] ?? '') ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-nowrap">Minor NC</th>
                                                                <td><?= esc($clause['minor_nc'] ?? '') ?></td>
                                                            </tr>
                                                            <tr>
                                                                <th class="text-nowrap">Major NC</th>
                                                                <td><?= esc($clause['major_nc'] ?? '') ?></td>
                                                            </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="border rounded p-3 h-100">
                                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                                            <label class="form-label mb-0" for="conformity_note_<?= esc($clauseId) ?>">Conformity note</label>
                                                            <span class="small text-success" id="autosave_status_<?= esc($clauseId) ?>">Auto saved</span>
                                                        </div>
                                                        <textarea
                                                            class="form-control mb-2"
                                                            id="conformity_note_<?= esc($clauseId) ?>"
                                                            rows="7"
                                                            data-autosave-url="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/findings/' . ($conformitySection['id'] ?? 0) . '/autosave') ?>"
                                                            data-autosave-status="autosave_status_<?= esc($clauseId) ?>"
                                                            data-default-text="<?= esc($smartConformityNote, 'attr') ?>"
                                                        ><?= esc($conformitySection['section_content'] ?? $smartConformityNote) ?></textarea>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button
                                                                class="btn btn-outline-primary btn-sm"
                                                                type="button"
                                                                data-ai-target="conformity_note_<?= esc($clauseId) ?>"
                                                                data-ai-status="autosave_status_<?= esc($clauseId) ?>"
                                                                data-ai-url="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/clauses/' . $clauseId . '/ai-conformity') ?>"
                                                            >
                                                                <i class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></i>
                                                                Generate AI auditor draft
                                                            </button>
                                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-fill-target="conformity_note_<?= esc($clauseId) ?>" data-fill-text="<?= esc($clause['predefined_conformity_note'] ?? '', 'attr') ?>">
                                                                <i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>
                                                                Restore library note
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/ncrs') ?>" class="border rounded p-3 h-100">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="ncr_clause_library_id" value="<?= esc($clauseId) ?>">
                                                        <input type="hidden" name="requirement" value="<?= esc($clause['requirement'], 'attr') ?>">

                                                        <label class="form-label" for="classification_<?= esc($clauseId) ?>">NC classification</label>
                                                        <select class="form-select form-select-sm mb-2" id="classification_<?= esc($clauseId) ?>" name="classification" required>
                                                            <option value="">Select classification</option>
                                                            <option value="minor">Minor NC</option>
                                                            <option value="major">Major NC</option>
                                                        </select>

                                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                                            <button class="btn btn-outline-warning btn-sm" type="button" data-fill-target="finding_<?= esc($clauseId) ?>" data-select-target="classification_<?= esc($clauseId) ?>" data-select-value="minor" data-fill-text="<?= esc($clause['minor_nc'] ?? '', 'attr') ?>">Use Minor</button>
                                                            <button class="btn btn-outline-danger btn-sm" type="button" data-fill-target="finding_<?= esc($clauseId) ?>" data-select-target="classification_<?= esc($clauseId) ?>" data-select-value="major" data-fill-text="<?= esc($clause['major_nc'] ?? '', 'attr') ?>">Use Major</button>
                                                        </div>

                                                        <label class="form-label" for="finding_<?= esc($clauseId) ?>">Finding</label>
                                                        <textarea class="form-control mb-2" id="finding_<?= esc($clauseId) ?>" name="finding" rows="3" placeholder="Record the actual nonconformity observed from the audit sample." required></textarea>

                                                        <label class="form-label" for="objective_evidence_<?= esc($clauseId) ?>">Objective evidence</label>
                                                        <textarea class="form-control mb-2" id="objective_evidence_<?= esc($clauseId) ?>" name="objective_evidence" rows="2" placeholder="State the exact sampled record, interview, observation or missing evidence." required></textarea>

                                                        <div class="row g-2 mb-2">
                                                            <div class="col-md-6">
                                                                <input class="form-control form-control-sm" name="responsible_person" placeholder="Responsible person">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <input class="form-control form-control-sm" type="date" name="target_date">
                                                            </div>
                                                        </div>

                                                        <button class="btn btn-danger btn-sm" type="submit">
                                                            <i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>
                                                            Raise NC
                                                        </button>
                                                    </form>
                                                </div>

                                                <?php if ($clauseSections !== [] || $clauseNcrs !== []): ?>
                                                    <div class="col-12">
                                                        <div class="small text-secondary mb-1">Saved for this clause</div>
                                                        <?php foreach ($clauseSections as $savedSection): ?>
                                                            <div class="border-start border-success ps-2 mb-2">
                                                                <div class="fw-semibold"><?= esc(ucwords(str_replace('_', ' ', $savedSection['section_key']))) ?></div>
                                                                <div><?= nl2br(esc($savedSection['section_content'])) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <?php foreach ($clauseNcrs as $savedNcr): ?>
                                                            <div class="border-start border-danger ps-2 mb-2">
                                                                <div class="fw-semibold"><?= esc($savedNcr['ncr_number'] . ' - ' . strtoupper($savedNcr['classification']) . ' - ' . $savedNcr['status']) ?></div>
                                                                <div><?= nl2br(esc($savedNcr['finding'])) ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<div class="row g-3 mt-1">
    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Extra report notes</div>
            <div class="table-responsive">
                <table class="table table-striped align-middle" data-table="true">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>Clause</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($manualSections as $section): ?>
                        <tr>
                            <td><?= esc(str_replace('_', ' ', $section['section_key'])) ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc($section['section_title']) ?></div>
                                <div class="text-secondary small"><?= esc(mb_strimwidth($section['section_content'], 0, 90, '...')) ?></div>
                            </td>
                            <td><?= esc(trim(($section['clause_number'] ?? '') . ' ' . ($section['clause_title'] ?? ''))) ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/findings/' . $section['id'] . '/delete') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-outline-danger btn-sm" type="submit">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">NCRs</div>
            <div class="accordion" id="ncrAccordion">
                <?php foreach ($ncrs as $ncr): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ncr<?= esc($ncr['id']) ?>">
                                <?= esc($ncr['ncr_number'] . ' - ' . strtoupper($ncr['classification']) . ' - ' . $ncr['status']) ?>
                            </button>
                        </h2>
                        <div id="ncr<?= esc($ncr['id']) ?>" class="accordion-collapse collapse" data-bs-parent="#ncrAccordion">
                            <div class="accordion-body">
                                <div class="small text-secondary mb-2"><?= esc(trim(($ncr['clause_number'] ?? '') . ' ' . ($ncr['clause_title'] ?? ''))) ?></div>
                                <div class="mb-2"><strong>Requirement:</strong> <?= nl2br(esc($ncr['requirement'])) ?></div>
                                <div class="mb-2"><strong>Finding:</strong> <?= nl2br(esc($ncr['finding'])) ?></div>
                                <div class="mb-2"><strong>Evidence:</strong> <?= nl2br(esc($ncr['objective_evidence'])) ?></div>
                                <?php if ($ncr['status'] !== 'closed'): ?>
                                    <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $event['id'] . '/ncrs/' . $ncr['id'] . '/close') ?>" class="row g-2">
                                        <?= csrf_field() ?>
                                        <div class="col-md-6"><textarea class="form-control" name="correction" rows="2" placeholder="Correction"></textarea></div>
                                        <div class="col-md-6"><textarea class="form-control" name="root_cause" rows="2" placeholder="Root cause"></textarea></div>
                                        <div class="col-md-6"><textarea class="form-control" name="corrective_action" rows="2" placeholder="Corrective action"></textarea></div>
                                        <div class="col-md-6"><textarea class="form-control" name="verification" rows="2" placeholder="Verification"></textarea></div>
                                        <div class="col-12"><textarea class="form-control" name="closure_notes" rows="2" placeholder="Closure notes"></textarea></div>
                                        <div class="col-12 text-end"><button class="btn btn-success btn-sm" type="submit">Close NCR</button></div>
                                    </form>
                                <?php else: ?>
                                    <div class="text-success fw-semibold">Closed <?= esc($ncr['closed_at'] ?? '') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let csrfTokenName = <?= json_encode(csrf_token()) ?>;
let csrfTokenValue = <?= json_encode(csrf_hash()) ?>;

function autosaveConformityNote(textarea) {
    const status = document.getElementById(textarea.dataset.autosaveStatus);
    const body = new URLSearchParams();

    body.append(csrfTokenName, csrfTokenValue);
    body.append('section_content', textarea.value);

    if (status) {
        status.className = 'small text-secondary';
        status.textContent = 'Saving...';
    }

    fetch(textarea.dataset.autosaveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body
    })
        .then((response) => response.json())
        .then((payload) => {
            if (payload.csrfToken && payload.csrfHash) {
                csrfTokenName = payload.csrfToken;
                csrfTokenValue = payload.csrfHash;
            }

            if (status) {
                status.className = payload.ok ? 'small text-success' : 'small text-danger';
                status.textContent = payload.ok ? 'Saved' : (payload.message || 'Not saved');
            }
        })
        .catch(() => {
            if (status) {
                status.className = 'small text-danger';
                status.textContent = 'Not saved';
            }
        });
}

document.querySelectorAll('[data-autosave-url]').forEach((textarea) => {
    let timer = null;

    textarea.addEventListener('input', () => {
        const status = document.getElementById(textarea.dataset.autosaveStatus);
        if (status) {
            status.className = 'small text-secondary';
            status.textContent = 'Editing...';
        }

        window.clearTimeout(timer);
        timer = window.setTimeout(() => autosaveConformityNote(textarea), 900);
    });

    textarea.addEventListener('blur', () => {
        window.clearTimeout(timer);
        autosaveConformityNote(textarea);
    });
});

document.querySelectorAll('[data-ai-url]').forEach((button) => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.aiTarget);
        const status = document.getElementById(button.dataset.aiStatus);
        const body = new URLSearchParams();

        if (! target) {
            return;
        }

        button.disabled = true;
        if (status) {
            status.className = 'small text-secondary';
            status.textContent = 'Generating...';
        }

        body.append(csrfTokenName, csrfTokenValue);

        fetch(button.dataset.aiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body
        })
            .then((response) => response.json())
            .then((payload) => {
                if (payload.csrfToken && payload.csrfHash) {
                    csrfTokenName = payload.csrfToken;
                    csrfTokenValue = payload.csrfHash;
                }

                if (! payload.ok) {
                    throw new Error(payload.message || 'Draft not generated');
                }

                target.value = payload.text || '';
                target.focus();
                target.dispatchEvent(new Event('input'));

                if (status) {
                    status.className = payload.source === 'ai' ? 'small text-primary' : 'small text-success';
                    status.textContent = payload.source === 'ai' ? 'AI draft ready' : 'Smart draft ready';
                }
            })
            .catch((error) => {
                if (status) {
                    status.className = 'small text-danger';
                    status.textContent = error.message || 'Not generated';
                }
            })
            .finally(() => {
                button.disabled = false;
            });
    });
});

document.querySelectorAll('[data-fill-target]').forEach((button) => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.fillTarget);
        if (target) {
            target.value = button.dataset.fillText || '';
            target.focus();
            target.dispatchEvent(new Event('input'));
        }

        const select = button.dataset.selectTarget ? document.getElementById(button.dataset.selectTarget) : null;
        if (select && button.dataset.selectValue) {
            select.value = button.dataset.selectValue;
        }
    });
});
</script>
<?= $this->endSection() ?>
