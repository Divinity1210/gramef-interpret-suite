<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RemittancesExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $remittances;

    public function __construct($remittances)
    {
        $this->remittances = $remittances;
    }

    public function collection()
    {
        return $this->remittances;
    }

    public function headings(): array
    {
        return [
            'Remittance Number',
            'Client Invoice',
            'Client',
            'Amount',
            'Status',
            'Uploaded By',
            'Upload Date',
            'Approved Date'
        ];
    }

    public function map($remittance): array
    {
        return [
            $remittance->remittance_number,
            $remittance->clientInvoice->invoice_number ?? 'N/A',
            $remittance->clientInvoice->client->user->name ?? $remittance->clientInvoice->client->name ?? 'N/A',
            number_format($remittance->amount, 2),
            ucfirst($remittance->status),
            $remittance->uploaded_by_name ?? 'N/A',
            $remittance->created_at->format('Y-m-d H:i:s'),
            $remittance->approved_at ? $remittance->approved_at->format('Y-m-d H:i:s') : 'N/A'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}