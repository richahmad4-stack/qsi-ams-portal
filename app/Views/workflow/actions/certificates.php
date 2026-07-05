<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="panel mb-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="panel-title mb-1">Certificate issue</div>
            <div class="text-secondary small">Issue one certificate for each selected standard after final approval.</div>
        </div>
        <a href="<?= site_url('workflow/certification/' . $client['id']) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>
            Back
        </a>
    </div>

    <form method="post" action="<?= site_url('workflow/certification/' . $client['id'] . '/certificates') ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label" for="issue_date">Issue date</label>
            <input class="form-control" type="date" id="issue_date" name="issue_date" value="<?= esc($defaultIssueDate) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="initial_certification_date">Initial certification date</label>
            <input class="form-control" type="date" id="initial_certification_date" name="initial_certification_date" value="<?= esc($client['initial_certification_date'] ?: $defaultIssueDate) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Decision status</label>
            <div class="form-control bg-light"><?= esc(($decision['decision'] ?? 'No decision') . ' - ' . ($decision['status'] ?? 'not approved')) ?></div>
        </div>

        <div class="col-12">
            <div class="row g-3">
                <?php foreach ([
                    'surveillance1' => ['Surveillance 1 due date', 'fa-calendar-check'],
                    'surveillance2' => ['Surveillance 2 due date', 'fa-calendar-days'],
                    'expiry' => ['Certificate expiry date', 'fa-hourglass-end'],
                ] as $key => [$label, $icon]): ?>
                    <div class="col-md-4">
                        <div class="metric">
                            <div class="text-secondary small"><i class="fa-solid <?= esc($icon) ?> me-1" aria-hidden="true"></i><?= esc($label) ?></div>
                            <div class="metric-value fs-5" data-cycle-date="<?= esc($key) ?>"><?= esc($cycleDates[$key]) ?></div>
                            <div class="text-secondary small">Calculated from issue date</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-12">
            <div class="border rounded p-3">
                <div class="fw-semibold mb-2">Certificates to issue</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Standard</th><th>Scheme</th><th>Scope</th></tr></thead>
                        <tbody>
                        <?php foreach ($standards as $standard): ?>
                            <tr>
                                <td><?= esc($standard['standard_code']) ?></td>
                                <td><?= esc($standard['scheme_type']) ?></td>
                                <td><?= esc($standard['scope'] ?: $client['scope']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($standards === []): ?>
                            <tr><td colspan="3" class="text-secondary">No selected standards found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary" type="submit" <?= ($decision['status'] ?? '') === 'gm_approved' ? '' : 'disabled' ?>>
                <i class="fa-solid fa-certificate me-1" aria-hidden="true"></i>
                Generate certificates
            </button>
        </div>
    </form>

    <?php if (($decision['status'] ?? '') !== 'gm_approved'): ?>
        <div class="alert alert-warning mt-3 mb-0">GM-approved granted decision is required before certificates can be issued.</div>
    <?php endif; ?>
</div>

<section class="panel">
    <div class="panel-title">Certificate records</div>
    <div class="table-responsive">
        <table class="table table-striped align-middle" data-table="true">
            <thead>
            <tr>
                <th>Certificate</th>
                <th>Standard</th>
                <th>Issue</th>
                <th>Expiry</th>
                <th>Status</th>
                <th>Verification</th>
                <th class="text-end">PDF</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($certificates as $certificate): ?>
                <tr>
                    <td><?= esc($certificate['certificate_number']) ?></td>
                    <td><?= esc($certificate['standard_code']) ?></td>
                    <td><?= esc($certificate['issue_date']) ?></td>
                    <td><?= esc($certificate['expiry_date']) ?></td>
                    <td><?= esc($certificate['status']) ?></td>
                    <td class="small"><?= esc($certificate['qr_payload']) ?></td>
                    <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="<?= site_url('workflow/certification/certificates/' . $certificate['id'] . '/pdf') ?>">
                            <i class="fa-solid fa-file-pdf me-1" aria-hidden="true"></i>
                            PDF
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($certificates === []): ?>
                <tr><td colspan="7" class="text-secondary">No certificates issued yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
document.getElementById('issue_date')?.addEventListener('change', function () {
    const raw = this.value;
    if (!raw) {
        return;
    }
    const addYearsMinusDay = function (years) {
        const date = new Date(raw + 'T00:00:00');
        date.setFullYear(date.getFullYear() + years);
        date.setDate(date.getDate() - 1);
        return date.toISOString().slice(0, 10);
    };
    document.querySelector('[data-cycle-date="surveillance1"]').textContent = addYearsMinusDay(1);
    document.querySelector('[data-cycle-date="surveillance2"]').textContent = addYearsMinusDay(2);
    document.querySelector('[data-cycle-date="expiry"]').textContent = addYearsMinusDay(3);
});
</script>
<?= $this->endSection() ?>
