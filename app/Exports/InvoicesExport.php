<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $invoices;

    public function __construct($invoices)
    {
        $this->invoices = $invoices;
    }

    public function collection()
    {
        return collect($this->invoices);
    }

    public function headings(): array
    {
        return [
            'Type',
            'Invoice Number',
            'Agent/Client',
            'Amount',
            'Status',
            'Date',
            'Created At'
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice['type'],
            $invoice['number'],
            $invoice['agent_client'],
            number_format($invoice['amount'], 2),
            ucfirst($invoice['status']),
            $invoice['date'],
            $invoice['created_at']->format('Y-m-d H:i:s')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}