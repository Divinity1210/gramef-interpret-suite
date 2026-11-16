<?php

namespace App\Exports;

use App\InterpreterJob;
use App\TranslatorJob;
use App\ClientInvoiceItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportJobData implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Combine InterpreterJobs and TranslatorJobs into one collection.
     */
    public function collection()
    {
        // Fetch interpreter jobs
        $interpreterJobModels = InterpreterJob::hasEnabledUser()
            ->with(['toLanguage', 'agent.user'])
            ->filter($this->filters)
            ->get();

        // Build invoice lookup map for interpreter jobs (job_id → ClientInvoice)
        $interpreterJobIds = $interpreterJobModels->pluck('id')->all();
        $interpreterInvoiceMap = collect();
        if (!empty($interpreterJobIds)) {
            $interpreterInvoiceMap = ClientInvoiceItem::whereIn('job_id', $interpreterJobIds)
                ->where('job_type', 'App\\InterpreterJob')
                ->with('invoice')
                ->get()
                ->groupBy('job_id')
                ->map(fn($items) => $items->sortByDesc('id')->first()->invoice);
        }

        $interpreterJobs = $interpreterJobModels->map(function ($job) use ($interpreterInvoiceMap) {
            $invoice = $interpreterInvoiceMap->get($job->id);
            return [
                'type' => 'Interpreter',
                'id' => $job->id,
                'agent' => $job->agent->user->full_name ?? 'DELETED USER',
                'date' => $job->appointment_date,
                'language' => $job->toLanguage->name,
                'additional_info' => $job->department, // Add other interpreter-specific fields
                // References
                'client_reference' => $job->client_reference ?? null,
                'file_reference' => $job->file_reference ?? null,
                // Timesheet fields (paper workflow)
                'timesheet_method' => $job->signature_method ?? null,
                'timesheet_status' => $job->timesheet_status ?? null,
                // Service User details (Interpreter jobs only)
                'su_required' => (bool)($job->service_user_required ?? false),
                'su_title' => $job->user_title ?? null,
                'su_first_name' => $job->user_first_name ?? null,
                'su_last_name' => $job->user_last_name ?? null,
                'su_date_of_birth' => optional($job->date_of_birth)->toDateString(),
                'su_personal_identity_number' => $job->personal_identity_number ?? null,
                'su_contact_information' => $job->contact_information ?? null,
                'su_contact_person_details' => $job->contact_person_details ?? null,
                'contact_info_same_as_account' => (bool)($job->contact_information_is_same_as_account ?? false),
                // Invoice financial columns
                'invoice_date' => $invoice ? optional($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') : null,
                'amount_before_vat' => $invoice ? number_format($invoice->subtotal ?? 0, 2) : null,
                'vat_amount' => $invoice ? number_format($invoice->vat_amount ?? 0, 2) : null,
            ];
        });

        // Fetch translator jobs
        $translatorJobModels = TranslatorJob::hasEnabledUser()
            ->with(['fromLanguage', 'toLanguage', 'agent.user'])
            ->filter($this->filters)
            ->get();

        // Build invoice lookup map for translator jobs (job_id → ClientInvoice)
        $translatorJobIds = $translatorJobModels->pluck('id')->all();
        $translatorInvoiceMap = collect();
        if (!empty($translatorJobIds)) {
            $translatorInvoiceMap = ClientInvoiceItem::whereIn('job_id', $translatorJobIds)
                ->where('job_type', 'App\\TranslatorJob')
                ->with('invoice')
                ->get()
                ->groupBy('job_id')
                ->map(fn($items) => $items->sortByDesc('id')->first()->invoice);
        }

        $translatorJobs = $translatorJobModels->map(function ($job) use ($translatorInvoiceMap) {
            $invoice = $translatorInvoiceMap->get($job->id);
            return [
                'type' => 'Translator',
                'id' => $job->id,
                'agent' => $job->agent->user->full_name ?? 'DELETED USER',
                'date' => $job->target_date,
                'language' => $job->fromLanguage->name . ' → ' . $job->toLanguage->name,
                'additional_info' => $job->word_count, // Add other translator-specific fields
                // Translator jobs do not have a native file_reference attribute
                'client_reference' => $job->client_reference ?? null,
                'file_reference' => null,
                // Timesheet fields not applicable
                'timesheet_method' => null,
                'timesheet_status' => null,
                // Translator jobs do not have service user details
                'su_required' => null,
                'su_title' => null,
                'su_first_name' => null,
                'su_last_name' => null,
                'su_date_of_birth' => null,
                'su_personal_identity_number' => null,
                'su_contact_information' => null,
                'su_contact_person_details' => null,
                'contact_info_same_as_account' => null,
                // Invoice financial columns
                'invoice_date' => $invoice ? optional($invoice->invoice_date ?? $invoice->created_at)->format('Y-m-d') : null,
                'amount_before_vat' => $invoice ? number_format($invoice->subtotal ?? 0, 2) : null,
                'vat_amount' => $invoice ? number_format($invoice->vat_amount ?? 0, 2) : null,
            ];
        });

        return $interpreterJobs->merge($translatorJobs);
    }

    /**
     * Map the data for each row.
     */
    public function map($row): array
    {
        return [
            $row['type'],
            $row['id'],
            $row['agent'],
            $row['date'],
            $row['language'],
            $row['additional_info'],
            $row['client_reference'] ?? null,
            $row['file_reference'] ?? null,
            // Timesheet fields
            $row['timesheet_method'] ?? null,
            $row['timesheet_status'] ?? null,
            // Service User columns
            isset($row['su_required']) ? (($row['su_required']) ? 'Yes' : ($row['su_required'] === null ? '' : 'No')) : '',
            $row['su_title'] ?? null,
            $row['su_first_name'] ?? null,
            $row['su_last_name'] ?? null,
            $row['su_date_of_birth'] ?? null,
            $row['su_personal_identity_number'] ?? null,
            $row['su_contact_information'] ?? null,
            $row['su_contact_person_details'] ?? null,
            isset($row['contact_info_same_as_account']) ? (($row['contact_info_same_as_account']) ? 'Yes' : ($row['contact_info_same_as_account'] === null ? '' : 'No')) : '',
            // Invoice financial columns
            $row['invoice_date'] ?? null,
            $row['amount_before_vat'] ?? null,
            $row['vat_amount'] ?? null,
        ];
    }

    /**
     * Define the headings for the exported file.
     */
    public function headings(): array
    {
        return [
            'Job Type',
            'Job Ref',
            'Agent Name',
            'Date',
            'Language(s)',
            'Additional Info',
            'Client Ref',
            'File Ref',
            'Timesheet Method',
            'Timesheet Status',
            // Service User columns
            'Service User Required',
            'Service User Title',
            'Service User First Name',
            'Service User Last Name',
            'Service User Date of Birth',
            'Service User Personal ID Number',
            'Service User Contact Information',
            'Service User Contact Person Details',
            'Contact Info Same As Account',
            // Invoice financial columns
            'Invoice Date',
            'Amount Before VAT',
            'VAT Amount',
        ];
    }

    /**
     * Apply styles to the exported sheet.
     */
    public function styles(Worksheet $sheet)
    {
        // Style header row
        $sheet->getStyle('A1:V1')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'V') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Set default row height
        $sheet->getDefaultRowDimension()->setRowHeight(20);
    }
}