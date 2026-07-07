<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <section class="metric">
            <div class="text-secondary">Readiness</div>
            <div class="metric-value"><?= esc((string) $summary['ready']) ?>/<?= esc((string) $summary['total']) ?></div>
        </section>
    </div>
    <div class="col-md-3">
        <section class="metric">
            <div class="text-secondary">Environment</div>
            <div class="metric-value fs-4"><?= esc((string) $summary['environment']) ?></div>
        </section>
    </div>
    <div class="col-md-3">
        <section class="metric">
            <div class="text-secondary">Open reminders</div>
            <div class="metric-value"><?= esc((string) $summary['open_reminders']) ?></div>
        </section>
    </div>
    <div class="col-md-3">
        <section class="metric">
            <div class="text-secondary">New leads</div>
            <div class="metric-value"><?= esc((string) $summary['new_leads']) ?></div>
        </section>
    </div>
</div>

<section class="panel mb-3">
    <div class="panel-title">Go-live checklist</div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Area</th>
                <th>Status</th>
                <th>Next action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($check['label']) ?></td>
                    <td>
                        <span class="badge <?= $check['ready'] ? 'text-bg-success' : 'text-bg-warning' ?>">
                            <?= $check['ready'] ? 'Ready' : 'Action needed' ?>
                        </span>
                    </td>
                    <td><?= esc($check['next_action']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-title">Operating notes</div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-1">Public URL</div>
                <div class="text-secondary"><?= esc($summary['base_url'] ?: 'Not configured') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-1">Email delivery</div>
                <div class="text-secondary"><?= $summary['email_enabled'] ? 'Enabled' : 'Disabled until SMTP is configured' ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <div class="fw-semibold mb-1">Reminder command</div>
                <div class="text-secondary">Run <code>php spark ams:process-reminders</code> manually or schedule it daily.</div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
