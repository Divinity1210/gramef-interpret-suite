<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\AgentInvoice;
use App\ClientInvoice;
use App\Payslip;
use App\Remittance;

class BulkExport implements WithMultipleSheets
{
    protected $types;
    protected $filters;

    public function __construct($types, $filters = [])
    {
        $this->types = $types;
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->types as $type) {
            switch ($type) {
                case 'invoices':
                    $sheets[] = new BulkInvoicesSheet($this->filters);
                    break;
                case 'payslips':
                    $sheets[] = new BulkPayslipsSheet($this->filters);
                    break;
                case 'remittances':
                    $sheets[] = new BulkRemittancesSheet($this->filters);
                    break;
            }
        }

        return $sheets;
    }
}

class BulkInvoicesSheet extends InvoicesExport
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
        
        $invoices = collect();
        
        // Agent invoices
        $agentQuery = AgentInvoice::with(['agent.user', 'items']);
        $this->applyDateFilters($agentQuery);
        $invoices = $invoices->merge($agentQuery->get()->map(function($invoice) {
            return [
                'type' => 'Agent',
                'number' => $invoice->invoice_number,
                'agent_client' => $invoice->agent->user->name ?? 'N/A',
                'amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'date' => $invoice->date_from,
                'created_at' => $invoice->created_at
            ];
        }));

        // Client invoices
        $clientQuery = ClientInvoice::with(['client.user']);
        $this->applyDateFilters($clientQuery);
        $invoices = $invoices->merge($clientQuery->get()->map(function($invoice) {
            return [
                'type' => 'Client',
                'number' => $invoice->invoice_number,
                'agent_client' => $invoice->client->user->name ?? $invoice->client->name ?? 'N/A',
                'amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'date' => $invoice->date_range_start,
                'created_at' => $invoice->created_at
            ];
        }));

        parent::__construct($invoices);
    }

    private function applyDateFilters($query)
    {
        if (isset($this->filters['start_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['start_date']);
        }
        if (isset($this->filters['end_date'])) {
            $query->whereDate('created_at', '<=', $this->filters['end_date']);
        }
    }
}

class BulkPayslipsSheet extends PayslipsExport
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
        
        $query = Payslip::with(['agent.user', 'items']);
        $this->applyDateFilters($query);
        
        parent::__construct($query->get());
    }

    private function applyDateFilters($query)
    {
        if (isset($this->filters['start_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['start_date']);
        }
        if (isset($this->filters['end_date'])) {
            $query->whereDate('created_at', '<=', $this->filters['end_date']);
        }
    }
}

class BulkRemittancesSheet extends RemittancesExport
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
        
        $query = Remittance::with(['clientInvoice.client.user']);
        $this->applyDateFilters($query);
        
        parent::__construct($query->get());
    }

    private function applyDateFilters($query)
    {
        if (isset($this->filters['start_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['start_date']);
        }
        if (isset($this->filters['end_date'])) {
            $query->whereDate('created_at', '<=', $this->filters['end_date']);
        }
    }
}