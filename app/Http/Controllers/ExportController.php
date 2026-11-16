<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\AgentInvoice;
use App\ClientInvoice;
use App\Payslip;
use App\Remittance;
use App\Agent;
use App\Client;
use App\Export;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InvoicesExport;
use App\Exports\PayslipsExport;
use App\Exports\RemittancesExport;
use App\Exports\BulkExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Carbon\Carbon;

class ExportController extends Controller
{
    public function index()
    {
        $agents = Agent::with('user')->get()->map(function($agent) {
            return (object)[
                'id' => $agent->id,
                'name' => $agent->user->name ?? 'Unknown'
            ];
        });

        $clients = Client::with('user')->get()->map(function($client) {
            return (object)[
                'id' => $client->id,
                'name' => $client->user->name ?? $client->name ?? 'Unknown'
            ];
        });

        $stats = [
            'total_invoices' => AgentInvoice::count() + ClientInvoice::count(),
            'total_payslips' => Payslip::count(),
            'total_remittances' => Remittance::count(),
            'last_export' => Export::latest()->first()?->created_at?->format('M d, Y H:i') ?? 'Never'
        ];

        $recentExports = Export::latest()->limit(10)->get();

        return view('exports.index', compact('agents', 'clients', 'stats', 'recentExports'));
    }

    public function exportInvoices(Request $request)
    {
        $request->validate([
            'type' => 'required|in:agent,client,both',
            'format' => 'required|in:excel,csv,pdf',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string'
        ]);

        $export = Export::create([
            'type' => 'invoices',
            'format' => $request->input('format'),
            'filters' => $request->only(['type', 'start_date', 'end_date', 'status']),
            'status' => 'processing',
            'user_id' => auth()->id()
        ]);

        try {
            $filename = $this->generateInvoicesExport($request, $export);
            
            $export->update([
                'status' => 'completed',
                'file_path' => $filename
            ]);

            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            $rel = 'exports/' . $filename;
            if ($disk === 's3') {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $rel) : $rel;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            }
            return response()->download(storage_path('app/' . $rel));
        } catch (\Exception $e) {
            Log::error('Export failed (invoices)', [
                'user_id' => auth()->id(),
                'filters' => $request->only(['type', 'start_date', 'end_date', 'status']),
                'format' => $request->input('format'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $export->update(['status' => 'failed']);
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    public function exportPayslips(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,csv,pdf,zip',
            'agent_id' => 'nullable|exists:agents,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string'
        ]);

        $export = Export::create([
            'type' => 'payslips',
            'format' => $request->input('format'),
            'filters' => $request->only(['agent_id', 'start_date', 'end_date', 'status']),
            'status' => 'processing',
            'user_id' => auth()->id()
        ]);

        try {
            $filename = $this->generatePayslipsExport($request, $export);
            
            $export->update([
                'status' => 'completed',
                'file_path' => $filename
            ]);

            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            $rel = 'exports/' . $filename;
            if ($disk === 's3') {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $rel) : $rel;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            }
            return response()->download(storage_path('app/' . $rel));
        } catch (\Exception $e) {
            Log::error('Export failed (payslips)', [
                'user_id' => auth()->id(),
                'filters' => $request->only(['agent_id', 'start_date', 'end_date', 'status']),
                'format' => $request->input('format'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $export->update(['status' => 'failed']);
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    public function exportRemittances(Request $request)
    {
        $request->validate([
            'format' => 'required|in:excel,csv,pdf',
            'client_id' => 'nullable|exists:clients,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string'
        ]);

        $export = Export::create([
            'type' => 'remittances',
            'format' => $request->input('format'),
            'filters' => $request->only(['client_id', 'start_date', 'end_date', 'status']),
            'status' => 'processing',
            'user_id' => auth()->id()
        ]);

        try {
            $filename = $this->generateRemittancesExport($request, $export);
            
            $export->update([
                'status' => 'completed',
                'file_path' => $filename
            ]);

            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            $rel = 'exports/' . $filename;
            if ($disk === 's3') {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $rel) : $rel;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            }
            return response()->download(storage_path('app/' . $rel));
        } catch (\Exception $e) {
            Log::error('Export failed (remittances)', [
                'user_id' => auth()->id(),
                'filters' => $request->only(['client_id', 'start_date', 'end_date', 'status']),
                'format' => $request->input('format'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $export->update(['status' => 'failed']);
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    public function bulkExport(Request $request)
    {
        $request->validate([
            'types' => 'required|array|min:1',
            'types.*' => 'in:invoices,payslips,remittances',
            'format' => 'required|in:zip,excel',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $export = Export::create([
            'type' => 'bulk',
            'format' => $request->input('format'),
            'filters' => $request->only(['types', 'start_date', 'end_date']),
            'status' => 'processing',
            'user_id' => auth()->id()
        ]);

        try {
            $filename = $this->generateBulkExport($request, $export);
            
            $export->update([
                'status' => 'completed',
                'file_path' => $filename
            ]);

            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            $rel = 'exports/' . $filename;
            if ($disk === 's3') {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $rel) : $rel;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            }
            return response()->download(storage_path('app/' . $rel));
        } catch (\Exception $e) {
            Log::error('Bulk export failed', [
                'user_id' => auth()->id(),
                'filters' => $request->only(['types', 'start_date', 'end_date']),
                'format' => $request->input('format'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $export->update(['status' => 'failed']);
            return back()->with('error', 'Bulk export failed: ' . $e->getMessage());
        }
    }

    public function download(Export $export)
    {
        if ($export->status !== 'completed' || !$export->file_path) {
            return back()->with('error', 'Export not available for download.');
        }
        $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $rel = 'exports/' . $export->file_path;
        if ($disk === 's3') {
            try {
                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $rel) : $rel;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
                return back()->with('error', 'Export file not found.');
            }
        }
        $filePath = storage_path('app/' . $rel);
        if (!file_exists($filePath)) { return back()->with('error', 'Export file not found.'); }
        return response()->download($filePath);
    }

    private function generateInvoicesExport(Request $request, ?Export $export): string
    {
        $query = collect();
        
        if ($request->type === 'agent' || $request->type === 'both') {
            $agentQuery = AgentInvoice::with(['agent.user', 'items']);
            // agent_invoices table uses start_date and end_date columns
            $this->applyDateFilters($agentQuery, $request, 'start_date', 'end_date');
            if ($request->status) {
                $agentQuery->where('status', $request->status);
            }
            $query = $query->merge($agentQuery->get()->map(function($invoice) {
                return [
                    'type' => 'Agent',
                    'number' => $invoice->invoice_number,
                    'agent_client' => $invoice->agent->user->name ?? 'N/A',
                    'amount' => $invoice->total ?? $invoice->total_amount ?? 0,
                    'status' => $invoice->status,
                    'date' => $invoice->start_date,
                    'created_at' => $invoice->created_at
                ];
            }));
        }

        if ($request->type === 'client' || $request->type === 'both') {
            $clientQuery = ClientInvoice::with(['client.user']);
            $this->applyDateFilters($clientQuery, $request, 'date_range_start', 'date_range_end');
            if ($request->status) {
                $clientQuery->where('status', $request->status);
            }
            $query = $query->merge($clientQuery->get()->map(function($invoice) {
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
        }

        $extension = $request->input('format') === 'excel' ? 'xlsx' : $request->input('format');
        $filename = 'invoices_export_' . now()->format('Y-m-d_H-i-s') . '.' . $extension;
        
        if ($extension === 'pdf') {
            return $this->generatePdfReport($query->toArray(), 'Invoices Export', $filename);
        } else {
            // Explicitly set writer type for Excel / CSV
            $writerType = $extension === 'xlsx' ? \Maatwebsite\Excel\Excel::XLSX : ($extension === 'csv' ? \Maatwebsite\Excel\Excel::CSV : null);
            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            if ($writerType) {
                Excel::store(new InvoicesExport($query), 'exports/' . $filename, $disk, $writerType);
            } else {
                Excel::store(new InvoicesExport($query), 'exports/' . $filename, $disk);
            }
            return $filename;
        }
    }

    private function generatePayslipsExport(Request $request, ?Export $export): string
    {
        $query = Payslip::with(['agent.user', 'items']);
        
        if ($request->agent_id) {
            $query->where('agent_id', $request->agent_id);
        }
        
        $this->applyDateFilters($query, $request);
        
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $payslips = $query->get();
        $filename = 'payslips_export_' . now()->format('Y-m-d_H-i-s');

        if ($request->input('format') === 'zip') {
            return $this->generatePayslipsPdfZip($payslips, $filename);
        } elseif (($request->input('format') === 'pdf')) {
            return $this->generatePdfReport($payslips->toArray(), 'Payslips Export', $filename . '.pdf');
        } else {
            $extension = $request->input('format') === 'excel' ? 'xlsx' : $request->input('format'); // map excel -> xlsx
            $filename .= '.' . $extension;
            $writerType = $extension === 'xlsx' ? \Maatwebsite\Excel\Excel::XLSX : ($extension === 'csv' ? \Maatwebsite\Excel\Excel::CSV : null);
            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            if ($writerType) {
                Excel::store(new PayslipsExport($payslips), 'exports/' . $filename, $disk, $writerType);
            } else {
                Excel::store(new PayslipsExport($payslips), 'exports/' . $filename, $disk);
            }
            return $filename;
        }
    }

    private function generateRemittancesExport(Request $request, ?Export $export): string
    {
        $query = Remittance::with(['clientInvoice.client.user']);
        
        if ($request->client_id) {
            $query->whereHas('clientInvoice', function($q) use ($request) {
                $q->where('client_id', $request->client_id);
            });
        }
        
        $this->applyDateFilters($query, $request);
        
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $remittances = $query->get();
        $extension = $request->input('format') === 'excel' ? 'xlsx' : $request->input('format');
        $filename = 'remittances_export_' . now()->format('Y-m-d_H-i-s') . '.' . $extension;

        if ($extension === 'pdf') {
            return $this->generatePdfReport($remittances->toArray(), 'Remittances Export', $filename);
        } else {
            $writerType = $extension === 'xlsx' ? \Maatwebsite\Excel\Excel::XLSX : ($extension === 'csv' ? \Maatwebsite\Excel\Excel::CSV : null);
            $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            if ($writerType) {
                Excel::store(new RemittancesExport($remittances), 'exports/' . $filename, $disk, $writerType);
            } else {
                Excel::store(new RemittancesExport($remittances), 'exports/' . $filename, $disk);
            }
            return $filename;
        }
    }

    private function generateBulkExport(Request $request, Export $export): string
    {
        $filename = 'bulk_export_' . now()->format('Y-m-d_H-i-s');
        
        if ($request->input('format') === 'zip') {
            return $this->generateBulkZip($request, $filename);
        } else {
            return $this->generateBulkExcel($request, $filename);
        }
    }

    private function generateBulkZip(Request $request, string $filename): string
    {
        $zipFilename = $filename . '.zip';
        $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFilename;
        
        $zip = new ZipArchive;
        if ($zip->open($tmpPath, ZipArchive::CREATE) === TRUE) {
            foreach ($request->types as $type) {
                $tempRequest = new Request($request->only(['start_date', 'end_date']));
                $tempRequest->merge(['format' => 'excel']);
                
                if ($type === 'invoices') {
                    $tempRequest->merge(['type' => 'both']);
                    $tempFilename = $this->generateInvoicesExport($tempRequest, null);
                } elseif ($type === 'payslips') {
                    $tempFilename = $this->generatePayslipsExport($tempRequest, null);
                } elseif ($type === 'remittances') {
                    $tempFilename = $this->generateRemittancesExport($tempRequest, null);
                }
                
                $rel = 'exports/' . $tempFilename;
                $content = \Illuminate\Support\Facades\Storage::disk($disk)->get($rel);
                $zip->addFromString($tempFilename, $content);
            }
            $zip->close();
        }
        
        $content = @file_get_contents($tmpPath) ?: '';
        @unlink($tmpPath);
        \Illuminate\Support\Facades\Storage::disk($disk)->put('exports/' . $zipFilename, $content);
        return $zipFilename;
    }

    private function generateBulkExcel(Request $request, string $filename): string
    {
        $filename .= '.xlsx';
        $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        Excel::store(new BulkExport($request->types, $request->only(['start_date', 'end_date'])), 'exports/' . $filename, $disk);
        return $filename;
    }

    private function generatePayslipsPdfZip($payslips, string $filename): string
    {
        $zipFilename = $filename . '.zip';
        $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFilename;
        
        $zip = new ZipArchive;
        if ($zip->open($tmpPath, ZipArchive::CREATE) === TRUE) {
            foreach ($payslips as $payslip) {
                $pdf = Pdf::loadView('payslips.pdf', compact('payslip'));
                $pdfContent = $pdf->output();
                $zip->addFromString('payslip_' . $payslip->payslip_number . '.pdf', $pdfContent);
            }
            $zip->close();
        }
        
        $content = @file_get_contents($tmpPath) ?: '';
        @unlink($tmpPath);
        \Illuminate\Support\Facades\Storage::disk($disk)->put('exports/' . $zipFilename, $content);
        return $zipFilename;
    }

    private function generatePdfReport(array $data, string $title, string $filename): string
    {
        $pdf = Pdf::loadView('exports.pdf-report', compact('data', 'title'));
        $disk = env('EXPORTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $content = $pdf->output();
        \Illuminate\Support\Facades\Storage::disk($disk)->put('exports/' . $filename, $content);
        return $filename;
    }

    private function applyDateFilters($query, Request $request, string $startField = 'created_at', ?string $endField = null): void
    {
        if ($request->start_date) {
            if ($endField) {
                $query->whereDate($startField, '>=', $request->start_date);
            } else {
                $query->whereDate($startField, '>=', $request->start_date);
            }
        }
        
        if ($request->end_date) {
            if ($endField) {
                $query->whereDate($endField, '<=', $request->end_date);
            } else {
                $query->whereDate($startField, '<=', $request->end_date);
            }
        }
    }
}