<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use Config\Database;

class FinanceController extends BaseController
{
    public function index()
    {
        $tenantId = (int) session()->get('tenant_id');
        $db = Database::connect();
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $invoiceTotals = $db->table('invoices')
            ->select('
                COALESCE(SUM(total_amount), 0) AS invoiced,
                COALESCE(SUM(CASE WHEN status IN ("paid", "closed") THEN total_amount ELSE 0 END), 0) AS closed_invoices
            ', false)
            ->where('tenant_id', $tenantId)
            ->get()
            ->getRowArray() ?: [];

        $payments = $db->table('payments')
            ->select('COALESCE(SUM(payments.amount), 0) AS total', false)
            ->join('invoices', 'invoices.id = payments.invoice_id')
            ->where('invoices.tenant_id', $tenantId)
            ->get()
            ->getRowArray() ?: [];

        $monthlyPayments = $db->table('payments')
            ->select('COALESCE(SUM(payments.amount), 0) AS total', false)
            ->join('invoices', 'invoices.id = payments.invoice_id')
            ->where('invoices.tenant_id', $tenantId)
            ->where('payments.payment_date >=', $monthStart)
            ->where('payments.payment_date <=', $monthEnd)
            ->get()
            ->getRowArray() ?: [];

        $proposalRows = $db->table('proposals')
            ->select('proposals.proposal_number, proposals.status, proposals.currency, proposals.grand_total, proposals.created_at, clients.company')
            ->join('clients', 'clients.id = proposals.client_id')
            ->where('proposals.tenant_id', $tenantId)
            ->where('proposals.deleted_at', null)
            ->orderBy('proposals.id', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $invoiceRows = $db->table('invoices')
            ->select('invoices.*, clients.company')
            ->join('clients', 'clients.id = invoices.client_id')
            ->where('invoices.tenant_id', $tenantId)
            ->orderBy('invoices.invoice_date', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $paymentRows = $db->table('payments')
            ->select('payments.*, invoices.invoice_number, invoices.currency, clients.company')
            ->join('invoices', 'invoices.id = payments.invoice_id')
            ->join('clients', 'clients.id = invoices.client_id')
            ->where('invoices.tenant_id', $tenantId)
            ->orderBy('payments.payment_date', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $outstandingRows = $db->table('invoices')
            ->select('invoices.*, clients.company, COALESCE(SUM(payments.amount), 0) AS paid_amount', false)
            ->join('clients', 'clients.id = invoices.client_id')
            ->join('payments', 'payments.invoice_id = invoices.id', 'left')
            ->where('invoices.tenant_id', $tenantId)
            ->groupBy('invoices.id, clients.company')
            ->having('paid_amount < MAX(invoices.total_amount)', null, false)
            ->orderBy('invoices.due_date', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();

        return view('finance/index', [
            'title' => 'Finance',
            'pageTitle' => 'Finance',
            'pageSubtitle' => 'Proposals, invoices, payments, revenue and outstanding balances',
            'summary' => [
                'proposal_count' => $this->count($db, 'proposals', $tenantId),
                'invoice_count' => $this->count($db, 'invoices', $tenantId),
                'total_invoiced' => (float) ($invoiceTotals['invoiced'] ?? 0),
                'total_paid' => (float) ($payments['total'] ?? 0),
                'monthly_revenue' => (float) ($monthlyPayments['total'] ?? 0),
                'outstanding' => max(0, (float) ($invoiceTotals['invoiced'] ?? 0) - (float) ($payments['total'] ?? 0)),
            ],
            'proposals' => $proposalRows,
            'invoices' => $invoiceRows,
            'payments' => $paymentRows,
            'outstanding' => $outstandingRows,
        ]);
    }

    private function count($db, string $table, int $tenantId): int
    {
        return (int) $db->table($table)
            ->where('tenant_id', $tenantId)
            ->countAllResults();
    }
}
