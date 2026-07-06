<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
    .chart-box {
        height: 260px;
    }

    .table td,
    .table th {
        white-space: nowrap;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$cards = $dashboard['cards'];
$workload = $dashboard['auditor_workload'];
$calendar = $dashboard['audit_calendar'];
$auditStatus = $dashboard['audit_status'];
$activities = $dashboard['recent_activities'];
$notifications = $notifications ?? [];
$clientsByStatus = $dashboard['clients_by_status'];
$certificatesByStandard = $dashboard['certificates_by_standard'];
$auditsByMonth = $dashboard['audits_by_month'];
$openNcrsBySeverity = $dashboard['open_ncrs_by_severity'];
$capaStatus = $dashboard['capa_status'];
$upcomingSurveillance = $dashboard['upcoming_surveillance_table'];
$expiringCertificates = $dashboard['expiring_certificates'];

$clientsByStatusLabels = array_column($clientsByStatus, 'status');
$clientsByStatusData = array_map('intval', array_column($clientsByStatus, 'total'));
$certificatesByStandardLabels = array_column($certificatesByStandard, 'standard_code');
$certificatesByStandardData = array_map('intval', array_column($certificatesByStandard, 'total'));
$auditsByMonthLabels = array_column($auditsByMonth, 'month');
$auditsByMonthData = array_map('intval', array_column($auditsByMonth, 'total'));
$openNcrsBySeverityLabels = array_column($openNcrsBySeverity, 'severity');
$openNcrsBySeverityData = array_map('intval', array_column($openNcrsBySeverity, 'total'));
$capaStatusLabels = array_column($capaStatus, 'status');
$capaStatusData = array_map('intval', array_column($capaStatus, 'total'));
$statusLabels = array_column($auditStatus, 'status');
$statusData = array_map('intval', array_column($auditStatus, 'total'));
$workloadLabels = array_column($workload, 'full_name');
$workloadData = array_map('intval', array_column($workload, 'total'));
?>

<div class="row g-3 mb-3">
    <?php
    $metricCards = [
        ['Total clients', $cards['total_clients'], 'fa-building', 'total_clients'],
        ['Active clients', $cards['active_clients'], 'fa-building-circle-check', 'active_clients'],
        ['Active certificates', $cards['active_certificates'], 'fa-certificate', 'active_certificates'],
        ['Expired certificates', $cards['expired_certificates'], 'fa-triangle-exclamation', 'expired_certificates'],
        ['Suspended certificates', $cards['suspended_certificates'], 'fa-ban', 'suspended_certificates'],
        ['Withdrawn certificates', $cards['withdrawn_certificates'], 'fa-circle-xmark', 'withdrawn_certificates'],
        ['Pending applications', $cards['pending_applications'], 'fa-file-signature', 'pending_applications'],
        ['Certificates expiring', $cards['certificates_expiring'], 'fa-clock', 'certificates_expiring'],
        ['Open NCRs', $cards['open_ncrs'], 'fa-clipboard-list', 'open_ncrs'],
        ['Open CAPAs', $cards['open_capas'], 'fa-screwdriver-wrench', 'open_capas'],
        ['Closed CAPAs', $cards['closed_capas'], 'fa-circle-check', 'closed_capas'],
        ['Completed audits', $cards['completed_audits'], 'fa-clipboard-check', 'completed_audits'],
        ['Pending reviews', $cards['pending_technical_reviews'], 'fa-user-check', 'pending_technical_reviews'],
        ['Pending decisions', $cards['pending_certification_decisions'], 'fa-stamp', 'pending_certification_decisions'],
        ['Upcoming audits', $cards['upcoming_audits'], 'fa-calendar-days', 'upcoming_audits'],
        ['Upcoming surveillance', $cards['upcoming_surveillance_audits'], 'fa-calendar-check', 'upcoming_surveillance_audits'],
        ['Customer feedback', $cards['customer_feedback'], 'fa-comments', 'customer_feedback'],
    ];
    ?>

    <?php foreach ($metricCards as [$label, $value, $icon, $section]): ?>
        <div class="col-sm-6 col-lg-4 col-xxl-3">
            <a class="metric d-block text-decoration-none text-reset" href="<?= site_url('dashboard/section/' . $section) ?>">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="text-secondary small"><?= esc($label) ?></div>
                        <div class="metric-value"><?= esc((string) $value) ?></div>
                    </div>
                    <i class="fa-solid <?= esc($icon) ?> text-primary fs-4" aria-hidden="true"></i>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-title">Clients by status</div>
            <div class="chart-box"><canvas id="clientsByStatus"></canvas></div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-title">Certificates by standard</div>
            <div class="chart-box"><canvas id="certificatesByStandard"></canvas></div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-title">Audits by month</div>
            <div class="chart-box"><canvas id="auditsByMonth"></canvas></div>
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-title">Open NCRs by severity</div>
            <div class="chart-box"><canvas id="openNcrsBySeverity"></canvas></div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-title">CAPA status</div>
            <div class="chart-box"><canvas id="capaStatus"></canvas></div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-title">Audit status</div>
            <div class="chart-box"><canvas id="auditStatus"></canvas></div>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <section class="panel">
            <div class="panel-title">Notifications</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($notifications === []): ?>
                        <tr><td colspan="4" class="text-secondary">No notifications</td></tr>
                    <?php endif; ?>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td><?= esc($notification['created_at']) ?></td>
                            <td><?= esc($notification['title']) ?></td>
                            <td><?= esc($notification['body']) ?></td>
                            <td><?= esc($notification['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="panel h-100">
            <div class="panel-title">Auditor workload</div>
            <div class="chart-box"><canvas id="auditorWorkload"></canvas></div>
        </section>
    </div>

    <div class="col-xl-7">
        <section class="panel h-100">
            <div class="panel-title">Audit calendar</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th>Audit</th>
                        <th>Type</th>
                        <th>Start</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($calendar === []): ?>
                        <tr><td colspan="5" class="text-secondary">No scheduled audits</td></tr>
                    <?php endif; ?>
                    <?php foreach ($calendar as $event): ?>
                        <tr>
                            <td><?= esc($event['company']) ?></td>
                            <td><?= esc($event['audit_number']) ?></td>
                            <td><?= esc(str_replace('_', ' ', $event['event_type'])) ?></td>
                            <td><?= esc($event['planned_start_date']) ?></td>
                            <td><?= esc($event['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Upcoming surveillance audits</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th>Audit</th>
                        <th>Type</th>
                        <th>Start</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($upcomingSurveillance === []): ?>
                        <tr><td colspan="5" class="text-secondary">No upcoming surveillance audits</td></tr>
                    <?php endif; ?>
                    <?php foreach ($upcomingSurveillance as $event): ?>
                        <tr>
                            <td><?= esc($event['company']) ?></td>
                            <td><?= esc($event['audit_number']) ?></td>
                            <td><?= esc(str_replace('_', ' ', $event['event_type'])) ?></td>
                            <td><?= esc($event['planned_start_date']) ?></td>
                            <td><?= esc($event['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Expiring certificates</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Client</th>
                        <th>Certificate</th>
                        <th>Standard</th>
                        <th>Expiry</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($expiringCertificates === []): ?>
                        <tr><td colspan="5" class="text-secondary">No certificates expiring in the next 90 days</td></tr>
                    <?php endif; ?>
                    <?php foreach ($expiringCertificates as $certificate): ?>
                        <tr>
                            <td><?= esc($certificate['company']) ?></td>
                            <td><?= esc($certificate['certificate_number']) ?></td>
                            <td><?= esc($certificate['standard_code']) ?></td>
                            <td><?= esc($certificate['expiry_date']) ?></td>
                            <td><?= esc($certificate['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12">
        <section class="panel">
            <div class="panel-title">Recent activities</div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Record</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($activities === []): ?>
                        <tr><td colspan="5" class="text-secondary">No activity recorded</td></tr>
                    <?php endif; ?>
                    <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td><?= esc($activity['created_at']) ?></td>
                            <td><?= esc($activity['full_name'] ?? 'System') ?></td>
                            <td><?= esc($activity['module']) ?></td>
                            <td><?= esc($activity['action']) ?></td>
                            <td><?= esc(($activity['entity_table'] ?? '') . ' #' . ($activity['entity_id'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const palette = ['#0a3765', '#3f8f5f', '#d98b2b', '#64748b', '#b84a62', '#4a7f8f', '#94a3b8'];

function chartOrEmpty(id, type, labels, data) {
    const canvas = document.getElementById(id);
    if (!canvas || labels.length === 0) {
        return;
    }

    new Chart(canvas, {
        type,
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: palette,
                borderColor: '#ffffff',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: type === 'bar' ? {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            } : {}
        }
    });
}

chartOrEmpty('clientsByStatus', 'doughnut', <?= json_encode($clientsByStatusLabels, JSON_THROW_ON_ERROR) ?>, <?= json_encode($clientsByStatusData, JSON_THROW_ON_ERROR) ?>);
chartOrEmpty('certificatesByStandard', 'doughnut', <?= json_encode($certificatesByStandardLabels, JSON_THROW_ON_ERROR) ?>, <?= json_encode($certificatesByStandardData, JSON_THROW_ON_ERROR) ?>);
chartOrEmpty('auditsByMonth', 'bar', <?= json_encode($auditsByMonthLabels, JSON_THROW_ON_ERROR) ?>, <?= json_encode($auditsByMonthData, JSON_THROW_ON_ERROR) ?>);
chartOrEmpty('openNcrsBySeverity', 'doughnut', <?= json_encode($openNcrsBySeverityLabels, JSON_THROW_ON_ERROR) ?>, <?= json_encode($openNcrsBySeverityData, JSON_THROW_ON_ERROR) ?>);
chartOrEmpty('capaStatus', 'doughnut', <?= json_encode($capaStatusLabels, JSON_THROW_ON_ERROR) ?>, <?= json_encode($capaStatusData, JSON_THROW_ON_ERROR) ?>);
chartOrEmpty('auditStatus', 'doughnut', <?= json_encode($statusLabels, JSON_THROW_ON_ERROR) ?>, <?= json_encode($statusData, JSON_THROW_ON_ERROR) ?>);
chartOrEmpty('auditorWorkload', 'bar', <?= json_encode($workloadLabels, JSON_THROW_ON_ERROR) ?>, <?= json_encode($workloadData, JSON_THROW_ON_ERROR) ?>);
</script>
<?= $this->endSection() ?>
