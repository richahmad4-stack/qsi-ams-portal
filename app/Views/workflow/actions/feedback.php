<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/feedback') ?>" class="panel mb-3">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Client feedback</div>
            <div class="text-secondary small">Capture satisfaction feedback after completion of certification activity.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('workflow/certification/' . $client['id'] . '/documents/feedback') ?>" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
                PDF
            </a>
            <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
                Back
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="contact_name">Contact name</label>
            <input class="form-control" id="contact_name" name="contact_name" value="<?= esc($client['contact_person'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="contact_email">Contact email</label>
            <input class="form-control" type="email" id="contact_email" name="contact_email" value="<?= esc($client['email'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="certificate_id">Certificate</label>
            <select class="form-select" id="certificate_id" name="certificate_id">
                <option value="">No certificate selected</option>
                <?php foreach ($certificates as $certificate): ?>
                    <option value="<?= esc($certificate['id']) ?>"><?= esc($certificate['certificate_number'] . ' - ' . $certificate['standard_code']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php foreach ([
            'overall_rating' => 'Overall rating',
            'communication_rating' => 'Communication',
            'auditor_rating' => 'Auditor performance',
            'report_quality_rating' => 'Report quality',
        ] as $field => $label): ?>
            <div class="col-md-3">
                <label class="form-label" for="<?= esc($field) ?>"><?= esc($label) ?></label>
                <select class="form-select" id="<?= esc($field) ?>" name="<?= esc($field) ?>">
                    <?php for ($score = 5; $score >= 1; $score--): ?>
                        <option value="<?= esc($score) ?>"><?= esc($score) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <div class="col-md-6">
            <label class="form-label" for="comments">Comments</label>
            <textarea class="form-control" id="comments" name="comments" rows="4"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="improvement_suggestion">Improvement suggestion</label>
            <textarea class="form-control" id="improvement_suggestion" name="improvement_suggestion" rows="4"></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="submitted_at">Submitted at</label>
            <input class="form-control" type="datetime-local" id="submitted_at" name="submitted_at" value="<?= esc(date('Y-m-d\TH:i')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="submitted">Submitted</option>
                <option value="draft">Draft</option>
                <option value="reviewed">Reviewed</option>
            </select>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-comment-dots me-1" aria-hidden="true"></i>
            Save feedback
        </button>
    </div>
</form>

<section class="panel">
    <div class="panel-title">Feedback history</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Submitted</th>
                <th>Contact</th>
                <th>Overall</th>
                <th>Communication</th>
                <th>Auditor</th>
                <th>Report</th>
                <th>Certificate</th>
                <th>Comments</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($feedbackRows as $feedback): ?>
                <tr>
                    <td><?= esc($feedback['submitted_at']) ?></td>
                    <td><?= esc($feedback['contact_name'] ?: $feedback['contact_email']) ?></td>
                    <td><?= esc($feedback['overall_rating']) ?></td>
                    <td><?= esc($feedback['communication_rating']) ?></td>
                    <td><?= esc($feedback['auditor_rating']) ?></td>
                    <td><?= esc($feedback['report_quality_rating']) ?></td>
                    <td><?= esc($feedback['certificate_number'] ?? '') ?></td>
                    <td><?= esc($feedback['comments'] ?: $feedback['improvement_suggestion']) ?></td>
                    <td><?= esc($feedback['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($feedbackRows === []): ?>
                <tr><td colspan="9" class="text-secondary">No feedback captured yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?= $this->endSection() ?>
