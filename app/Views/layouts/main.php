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
            --qsi-bg: #f4f7fb;
            --qsi-border: #dbe5ef;
            --qsi-text: #17202a;
        }

        body {
            background: var(--qsi-bg);
            color: var(--qsi-text);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
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
            padding: 9px 10px;
            display: flex;
            gap: 8px;
            align-items: center;
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
            background: #ffffff;
        }

        .panel {
            padding: 18px;
        }

        .panel-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .metric {
            padding: 18px;
            min-height: 112px;
        }

        .metric-value {
            font-size: 30px;
            line-height: 1.1;
            font-weight: 700;
        }

        .table td,
        .table th {
            vertical-align: middle;
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
    'Core' => [
        ['label' => 'Dashboard', 'icon' => 'fa-chart-line', 'href' => site_url('dashboard'), 'match' => 'dashboard'],
        ['label' => 'Workflow', 'icon' => 'fa-diagram-project', 'href' => site_url('workflow/certification'), 'match' => 'workflow/certification'],
        ['label' => 'Clients', 'icon' => 'fa-building', 'href' => site_url('masters/clients'), 'match' => 'masters/clients'],
        ['label' => 'Legacy Import', 'icon' => 'fa-file-import', 'href' => site_url('masters/imports'), 'match' => 'masters/imports'],
        ['label' => 'Standards', 'icon' => 'fa-certificate', 'href' => site_url('masters/standards'), 'match' => 'masters/standards'],
    ],
    'Finance' => array_filter([
        $showFinance ? ['label' => 'Finance Dashboard', 'icon' => 'fa-coins', 'href' => site_url('finance'), 'match' => 'finance'] : null,
    ]),
    'References' => [
        ['label' => 'IAF Codes', 'icon' => 'fa-tags', 'href' => site_url('masters/references/iaf'), 'match' => 'masters/references/iaf'],
        ['label' => 'NACE Codes', 'icon' => 'fa-industry', 'href' => site_url('masters/references/nace'), 'match' => 'masters/references/nace'],
        ['label' => 'Food Categories', 'icon' => 'fa-utensils', 'href' => site_url('masters/references/food'), 'match' => 'masters/references/food'],
        ['label' => 'Medical Categories', 'icon' => 'fa-kit-medical', 'href' => site_url('masters/references/medical'), 'match' => 'masters/references/medical'],
    ],
    'Resources' => [
        ['label' => 'Personnel', 'icon' => 'fa-users', 'href' => site_url('masters/personnel'), 'match' => 'masters/personnel'],
        ['label' => 'Clause Library', 'icon' => 'fa-book-open', 'href' => site_url('masters/clauses'), 'match' => 'masters/clauses'],
        ['label' => 'Templates', 'icon' => 'fa-file-lines', 'href' => site_url('masters/templates'), 'match' => 'masters/templates'],
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
