<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
        Back to workflow
    </a>
</div>

<div class="panel mb-3">
    <div class="panel-title">New appointment</div>
    <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/appointments') ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="audit_event_id">Audit event</label>
                <select class="form-select" id="audit_event_id" name="audit_event_id" required>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= esc($event['id']) ?>" <?= (int) ($selectedEventId ?? 0) === (int) $event['id'] ? 'selected' : '' ?>>
                            <?= esc(str_replace('_', ' ', $event['event_type']) . ' - ' . $event['audit_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="personnel_id">Auditor / expert</label>
                <select class="form-select" id="personnel_id" name="personnel_id" required>
                    <?php foreach ($personnel as $person): ?>
                        <option value="<?= esc($person['id']) ?>">
                            <?= esc($person['full_name'] . ' - ' . str_replace('_', ' ', $person['personnel_type'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="appointment_role">Role</label>
                <select class="form-select" id="appointment_role" name="appointment_role" required>
                    <?php foreach (['lead_auditor' => 'Lead auditor', 'auditor' => 'Auditor', 'technical_expert' => 'Technical expert', 'observer' => 'Observer'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <?php foreach (['appointed' => 'Appointed', 'accepted' => 'Accepted', 'declined' => 'Declined', 'replaced' => 'Replaced'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" value="1" id="competence_confirmed" name="competence_confirmed" checked>
                    <label class="form-check-label" for="competence_confirmed">Competence confirmed</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" value="1" id="impartiality_confirmed" name="impartiality_confirmed" checked>
                    <label class="form-check-label" for="impartiality_confirmed">Impartiality confirmed</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" value="1" id="conflict_of_interest" name="conflict_of_interest">
                    <label class="form-check-label" for="conflict_of_interest">Conflict declared</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="conflict_notes">Notes</label>
                <input class="form-control" id="conflict_notes" name="conflict_notes">
            </div>
        </div>
        <div class="mt-3 text-end">
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i>
                Save appointment
            </button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel-title">Appointments</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Audit</th>
                <th>Name</th>
                <th>Role</th>
                <th>Status</th>
                <th>Conflict check</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $appointment): ?>
                <?php $check = json_decode($appointment['conflict_check_json'] ?? '{}', true) ?: []; ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= esc($appointment['audit_number']) ?></div>
                        <div class="text-secondary small"><?= esc(str_replace('_', ' ', $appointment['event_type'])) ?></div>
                    </td>
                    <td><?= esc($appointment['full_name']) ?></td>
                    <td><?= esc(str_replace('_', ' ', $appointment['appointment_role'])) ?></td>
                    <td><?= esc($appointment['status']) ?></td>
                    <td class="small">
                        Competence: <?= ! empty($check['competence_confirmed']) ? 'Yes' : 'No' ?>,
                        Impartiality: <?= ! empty($check['impartiality_confirmed']) ? 'Yes' : 'No' ?>,
                        Conflict: <?= ! empty($check['conflict_of_interest']) ? 'Yes' : 'No' ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                            <a class="btn btn-outline-danger btn-sm" href="<?= site_url('workflow/certification/' . $client['id'] . '/audit-events/' . $appointment['audit_event_id'] . '/documents/auditor_appointment') ?>" title="Auditor appointment PDF">
                                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                            </a>
                            <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/appointments/' . $appointment['id'] . '/delete') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger btn-sm" type="submit">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
