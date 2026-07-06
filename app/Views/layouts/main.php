<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'QSI AMS') ?> | QSI AMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.css" rel="stylesheet">
    <?= $this->renderSection('head') ?>
    <style>
        :root {
            --qsi-blue: #0f5ea8;
            --qsi-blue-dark: #0a3765;
            --qsi-sidebar: #0d2947;
            --qsi-bg: #f3f6fa;
            --qsi-panel: #ffffff;
            --qsi-border: #d6e0ea;
            --qsi-text: #17202a;
            --qsi-muted: #64748b;
            --qsi-green: #2f855a;
            --qsi-red: #b42318;
            --qsi-amber: #b7791f;
        }

        body {
            background: var(--qsi-bg);
            color: var(--qsi-text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.45;
        }

        .app-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
        }

        .sidebar {
            background: var(--qsi-sidebar);
            color: #ffffff;
            padding: 18px 14px;
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand {
            font-size: 17px;
            font-weight: 700;
            margin: 0 8px 18px;
        }

        .sidebar-section {
            color: rgba(255, 255, 255, 0.62);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 18px 8px 6px;
        }

        .sidebar .nav-link {
            color: #e9f2fb;
            border-radius: 6px;
            padding: 8px 10px;
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 13px;
            min-height: 36px;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }

        .content {
            min-width: 0;
            padding: 24px;
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .panel,
        .metric {
            border: 1px solid var(--qsi-border);
            border-radius: 8px;
            background: var(--qsi-panel);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .panel {
            padding: 18px;
            margin-bottom: 14px;
        }

        .panel-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .metric {
            padding: 18px;
            min-height: 112px;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        a.metric:hover {
            border-color: #9fb7cf;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }

        .metric-value {
            font-size: 30px;
            line-height: 1.1;
            font-weight: 700;
            color: #0f172a;
        }

        .table td,
        .table th {
            vertical-align: middle;
            border-color: #e2e8f0;
        }

        .table thead th {
            color: #334155;
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .02em;
        }

        .table-striped > tbody > tr:nth-of-type(odd) > * {
            --bs-table-bg-type: #f8fafc;
        }

        .form-label {
            font-weight: 600;
        }

        .btn-primary {
            --bs-btn-bg: var(--qsi-blue);
            --bs-btn-border-color: var(--qsi-blue);
            --bs-btn-hover-bg: var(--qsi-blue-dark);
            --bs-btn-hover-border-color: var(--qsi-blue-dark);
        }

        .btn {
            border-radius: 6px;
            font-weight: 600;
        }

        .badge {
            border-radius: 999px;
            font-weight: 700;
        }

        .alert {
            border-radius: 8px;
            border-width: 1px;
        }

        .nav-tabs .nav-link {
            border-radius: 6px 6px 0 0;
        }

        @media (max-width: 900px) {
            .app-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php
$currentPath = trim(uri_string(), '/');
$currentUser = current_user();
$currentRoles = $currentUser['roles'] ?? [];
$financeRoles = ['super_admin', 'general_manager', 'coo', 'finance_officer', 'admin'];
$showFinance = can('proposals', 'view') || array_intersect($financeRoles, $currentRoles) !== [];

$nav = [
    'Operations' => [
        ['label' => 'Dashboard', 'icon' => 'fa-chart-line', 'href' => site_url('dashboard'), 'match' => 'dashboard'],
        ['label' => 'Clients', 'icon' => 'fa-building', 'href' => site_url('masters/clients'), 'match' => 'masters/clients'],
        ['label' => 'Applications', 'icon' => 'fa-file-signature', 'href' => site_url('dashboard/section/pending_applications'), 'match' => 'dashboard/section/pending_applications'],
        ['label' => 'Certification Files', 'icon' => 'fa-diagram-project', 'href' => site_url('workflow/certification'), 'match' => 'workflow/certification'],
        ['label' => 'Audit Plans', 'icon' => 'fa-list-check', 'href' => site_url('workflow/certification'), 'match' => 'workflow/certification'],
        ['label' => 'Audits', 'icon' => 'fa-clipboard-check', 'href' => site_url('dashboard/section/upcoming_audits'), 'match' => 'dashboard/section/upcoming_audits'],
        ['label' => 'NCRs', 'icon' => 'fa-triangle-exclamation', 'href' => site_url('dashboard/section/open_ncrs'), 'match' => 'dashboard/section/open_ncrs'],
        ['label' => 'CAPA', 'icon' => 'fa-screwdriver-wrench', 'href' => site_url('dashboard/section/open_capas'), 'match' => 'dashboard/section/open_capas'],
        ['label' => 'Technical Reviews', 'icon' => 'fa-user-check', 'href' => site_url('dashboard/section/pending_technical_reviews'), 'match' => 'dashboard/section/pending_technical_reviews'],
        ['label' => 'Certification Decisions', 'icon' => 'fa-stamp', 'href' => site_url('dashboard/section/pending_certification_decisions'), 'match' => 'dashboard/section/pending_certification_decisions'],
        ['label' => 'Certificates', 'icon' => 'fa-certificate', 'href' => site_url('dashboard/section/active_certificates'), 'match' => 'dashboard/section/active_certificates'],
        ['label' => 'Surveillance', 'icon' => 'fa-calendar-check', 'href' => site_url('dashboard/section/upcoming_surveillance_audits'), 'match' => 'dashboard/section/upcoming_surveillance_audits'],
        ['label' => 'Customer Feedback', 'icon' => 'fa-comments', 'href' => site_url('dashboard/section/customer_feedback'), 'match' => 'dashboard/section/customer_feedback'],
    ],
    'Commercial' => array_filter([
        ['label' => 'Proposals', 'icon' => 'fa-file-invoice-dollar', 'href' => site_url('workflow/certification'), 'match' => 'workflow/certification'],
        ['label' => 'Contracts', 'icon' => 'fa-file-contract', 'href' => site_url('workflow/certification'), 'match' => 'workflow/certification'],
        $showFinance ? ['label' => 'Finance', 'icon' => 'fa-coins', 'href' => site_url('finance'), 'match' => 'finance'] : null,
    ]),
    'System Data' => [
        ['label' => 'Standards', 'icon' => 'fa-certificate', 'href' => site_url('masters/standards'), 'match' => 'masters/standards'],
        ['label' => 'IAF Codes', 'icon' => 'fa-tags', 'href' => site_url('masters/references/iaf'), 'match' => 'masters/references/iaf'],
        ['label' => 'NACE Codes', 'icon' => 'fa-industry', 'href' => site_url('masters/references/nace'), 'match' => 'masters/references/nace'],
        ['label' => 'Food Categories', 'icon' => 'fa-utensils', 'href' => site_url('masters/references/food'), 'match' => 'masters/references/food'],
        ['label' => 'Medical Categories', 'icon' => 'fa-kit-medical', 'href' => site_url('masters/references/medical'), 'match' => 'masters/references/medical'],
    ],
    'Resources & Admin' => [
        ['label' => 'Personnel', 'icon' => 'fa-users', 'href' => site_url('masters/personnel'), 'match' => 'masters/personnel'],
        ['label' => 'Clause Library', 'icon' => 'fa-book-open', 'href' => site_url('masters/clauses'), 'match' => 'masters/clauses'],
        ['label' => 'Templates', 'icon' => 'fa-file-lines', 'href' => site_url('masters/templates'), 'match' => 'masters/templates'],
        ['label' => 'Legacy Import', 'icon' => 'fa-file-import', 'href' => site_url('masters/imports'), 'match' => 'masters/imports'],
    ],
];
?>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">QSI AMS</div>
        <nav class="nav flex-column">
            <?php foreach ($nav as $section => $items): ?>
                <div class="sidebar-section"><?= esc($section) ?></div>
                <?php foreach ($items as $item): ?>
                    <?php $active = str_starts_with($currentPath, $item['match']); ?>
                    <a class="nav-link <?= $active ? 'active' : '' ?>" href="<?= esc($item['href']) ?>">
                        <i class="fa-solid <?= esc($item['icon']) ?>" aria-hidden="true"></i>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="<?= site_url('logout') ?>" class="mt-4 px-1">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-light w-100" type="submit">
                <i class="fa-solid fa-right-from-bracket me-2" aria-hidden="true"></i>
                Sign out
            </button>
        </form>
    </aside>

    <main class="content">
        <div class="page-header">
            <div>
                <h1 class="h3 mb-1"><?= esc($pageTitle ?? $title ?? 'QSI AMS') ?></h1>
                <div class="text-secondary"><?= esc($pageSubtitle ?? ($currentUser['tenant_name'] ?? '')) ?></div>
            </div>
            <div class="text-end">
                <div class="fw-semibold"><?= esc($currentUser['name'] ?? '') ?></div>
                <div class="text-secondary small"><?= esc($currentUser['email'] ?? '') ?></div>
            </div>
        </div>

        <?php foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $key => $class): ?>
            <?php if (session()->getFlashdata($key)): ?>
                <div class="alert alert-<?= esc($class) ?>" role="alert"><?= esc(session()->getFlashdata($key)) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.js"></script>
<script>
document.querySelectorAll('[data-table="true"]').forEach((table) => {
    new DataTable(table, {
        pageLength: 25,
        order: [],
        responsive: true
    });
});
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
