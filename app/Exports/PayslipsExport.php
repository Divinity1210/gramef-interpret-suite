<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayslipsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $payslips;

    public function __construct($payslips)
    {
        $this->payslips = $payslips;
    }

    public function collection()
    {
        return $this->payslips;
    }

    public function headings(): array
    {
        return [
            'Payslip Number',
            'Agent',
            'Period From',
            'Period To',
            'Gross Amount',
            'Tax Amount',
            'Net Amount',
            'Status',
            'Created At'
        ];
    }

    public function map($payslip): array
    {
        return [
            $payslip->payslip_number,
            $payslip->agent->user->name ?? 'N/A',
            $payslip->period_from,
            $payslip->period_to,
            number_format($payslip->gross_amount, 2),
            number_format($payslip->tax_amount, 2),
            number_format($payslip->net_amount, 2),
            ucfirst($payslip->status),
            $payslip->created_at->format('Y-m-d H:i:s')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}