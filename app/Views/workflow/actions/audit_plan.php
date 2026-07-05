<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
    <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
        Back to workflow
    </a>
</div>

<div class="panel mb-3">
    <div class="panel-title">Create or update audit plan</div>
    <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-plan') ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="audit_event_id">Audit event</label>
                <select class="form-select" id="audit_event_id" name="audit_event_id" required>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= esc($event['id']) ?>" <?= (int) ($selectedEventId ?? 0) === (int) $event['id'] ? 'selected' : '' ?>>
                            <?= esc(str_replace('_', ' ', $event['event_type']) . ' - ' . $event['audit_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Plan status</label>
                <select class="form-select" id="status" name="status" required>
                    <?php foreach (['draft' => 'Draft', 'prepared' => 'Prepared', 'approved' => 'Approved', 'issued' => 'Issued'] as $value => $label): ?>
                        <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end justify-content-end">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>
                    Save plan
                </button>
            </div>
        </div>
    </form>
</div>

<div class="panel mb-3">
    <div class="panel-title">Add timetable item</div>
    <?php if ($plans === []): ?>
        <div class="alert alert-info mb-0">Create an audit plan first, then add timetable items.</div>
    <?php else: ?>
        <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-plan/items') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="audit_plan_id">Plan</label>
                    <select class="form-select" id="audit_plan_id" name="audit_plan_id" required>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= esc($plan['id']) ?>" <?= (int) ($selectedEventId ?? 0) === (int) $plan['audit_event_id'] ? 'selected' : '' ?>>
                                <?= esc($plan['plan_number'] . ' - ' . str_replace('_', ' ', $plan['event_type'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="audit_date">Date</label>
                    <input class="form-control" type="date" id="audit_date" name="audit_date" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="start_time">Start</label>
                    <input class="form-control" type="time" id="start_time" name="start_time" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="end_time">End</label>
                    <input class="form-control" type="time" id="end_time" name="end_time" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="sort_order">Order</label>
                    <input class="form-control" type="number" id="sort_order" name="sort_order" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="activity_type">Activity</label>
                    <input class="form-control" id="activity_type" name="activity_type" placeholder="Opening meeting" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="department">Department</label>
                    <input class="form-control" id="department" name="department">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="process_name">Process</label>
                    <input class="form-control" id="process_name" name="process_name">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="clauses">Clauses</label>
                    <input class="form-control" id="clauses" name="clauses">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="auditor_personnel_id">Auditor</label>
                    <select class="form-select" id="auditor_personnel_id" name="auditor_personnel_id">
                        <option value="">Not assigned</option>
                        <?php foreach ($auditors as $auditor): ?>
                            <option value="<?= esc($auditor['id']) ?>"><?= esc($auditor['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="notes">Notes</label>
                    <input class="form-control" id="notes" name="notes">
                </div>
            </div>
            <div class="mt-3 text-end">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-plus me-1" aria-hidden="true"></i>
                    Add item
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="panel h-100">
            <div class="panel-title">Plans</div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Audit</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td><?= esc($plan['plan_number']) ?></td>
                            <td><?= esc(str_replace('_', ' ', $plan['event_type'])) ?></td>
                            <td><?= esc($plan['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel h-100">
            <div class="panel-title">Timetable</div>
            <div class="table-responsive">
                <table class="table table-striped align-middle" data-table="true">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Activity</th>
                        <th>Process / clauses</th>
                        <th>Auditor</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= esc($item['audit_date']) ?></td>
                            <td><?= esc(substr($item['start_time'], 0, 5) . ' - ' . substr($item['end_time'], 0, 5)) ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc($item['activity_type']) ?></div>
                                <div class="text-secondary small"><?= esc($item['department'] ?? '') ?></div>
                            </td>
                            <td>
                                <div><?= esc($item['process_name'] ?? '') ?></div>
                                <div class="text-secondary small"><?= esc($item['clauses'] ?? '') ?></div>
                            </td>
                            <td><?= esc($item['auditor_name'] ?? 'Not assigned') ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/audit-plan/items/' . $item['id'] . '/delete') ?>">
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
        </div>
    </div>
</div>
<?= $this->endSection() ?>
