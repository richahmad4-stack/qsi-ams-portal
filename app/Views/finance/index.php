<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$money = static fn (float $value): string => number_format($value, 2);
?>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Proposals', $summary['proposal_count'], 'fa-file-invoice'],
        ['Invoices', $summary['invoice_count'], 'fa-receipt'],
        ['Total invoiced', $money($summary['total_invoiced']), 'fa-scale-balanced'],
        ['Total paid', $money($summary['total_paid']), 'fa-money-check-dollar'],
        ['Monthly revenue', $money($summary['monthly_revenue']), 'fa-chart-line'],
        ['Outstanding', $money($summary['outstanding']), 'fa-circle-exclamation'],
    ] as [$label, $value, $icon]): ?>
        <div class="col-sm-6 col-xl-2">
            <div class="metric">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="text-secondary small"><?= esc($label) ?></div>
                        <div class="metric-value fs-4"><?= esc((string) $value) ?></div>
                    </div>
                    <i class="fa-solid <?= esc($icon) ?> text-primary fs-5" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Proposals</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Client</th><th>Proposal</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($proposals as $proposal): ?>
                        <tr>
                            <td><?= esc($proposal['company']) ?></td>
                            <td><?= esc($proposal['proposal_number']) ?></td>
                            <td><?= esc($proposal['status']) ?></td>
                            <td><?= esc($proposal['currency'] . ' ' . $money((float) $proposal['grand_total'])) ?></td>
                            <td><?= esc(substr((string) $proposal['created_at'], 0, 10)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($proposals === []): ?>
                        <tr><td colspan="5" class="text-secondary">No proposals found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Invoices</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Client</th><th>Invoice</th><th>Date</th><th>Due</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?= esc($invoice['company']) ?></td>
                            <td><?= esc($invoice['invoice_number']) ?></td>
                            <td><?= esc($invoice['invoice_date']) ?></td>
                            <td><?= esc($invoice['due_date']) ?></td>
                            <td><?= esc($invoice['currency'] . ' ' . $money((float) $invoice['total_amount'])) ?></td>
                            <td><?= esc($invoice['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($invoices === []): ?>
                        <tr><td colspan="6" class="text-secondary">No invoices found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Payments</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Client</th><th>Invoice</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= esc($payment['company']) ?></td>
                            <td><?= esc($payment['invoice_number']) ?></td>
                            <td><?= esc($payment['payment_date']) ?></td>
                            <td><?= esc($payment['currency'] . ' ' . $money((float) $payment['amount'])) ?></td>
                            <td><?= esc($payment['method']) ?></td>
                            <td><?= esc($payment['reference_number']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($payments === []): ?>
                        <tr><td colspan="6" class="text-secondary">No payments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="panel h-100">
            <div class="panel-title">Outstanding payments</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Client</th><th>Invoice</th><th>Due</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead>
                    <tbody>
                    <?php foreach ($outstanding as $invoice): ?>
                        <?php $balance = max(0, (float) $invoice['total_amount'] - (float) $invoice['paid_amount']); ?>
                        <tr>
                            <td><?= esc($invoice['company']) ?></td>
                            <td><?= esc($invoice['invoice_number']) ?></td>
                            <td><?= esc($invoice['due_date']) ?></td>
                            <td><?= esc($invoice['currency'] . ' ' . $money((float) $invoice['total_amount'])) ?></td>
                            <td><?= esc($invoice['currency'] . ' ' . $money((float) $invoice['paid_amount'])) ?></td>
                            <td><?= esc($invoice['currency'] . ' ' . $money($balance)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($outstanding === []): ?>
                        <tr><td colspan="6" class="text-secondary">No outstanding balances found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12">
        <section class="panel">
            <div class="panel-title">Finance reports</div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary" type="button" onclick="window.print()">
                    <i class="fa-solid fa-print me-1" aria-hidden="true"></i>
                    Print finance summary
                </button>
                <a class="btn btn-outline-primary" href="<?= site_url('dashboard/section/pending_applications') ?>">
                    <i class="fa-solid fa-file-signature me-1" aria-hidden="true"></i>
                    Pending applications
                </a>
                <a class="btn btn-outline-primary" href="<?= site_url('workflow/certification') ?>">
                    <i class="fa-solid fa-diagram-project me-1" aria-hidden="true"></i>
                    Certification files
                </a>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
