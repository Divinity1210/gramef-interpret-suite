<?php

namespace App\Http\Controllers;

use App\Remittance;
use App\ClientInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\RemittanceCreatedMail;
use App\Mail\RemittanceApprovedMail;
use App\Mail\RemittanceRejectedMail;

class RemittanceController extends Controller
{
    public function index(Request $request)
    {
        // Filters: status, date range (created_at), client (via invoice's client)
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $clientId = $request->query('client_id');

        $query = Remittance::with([
            'clientInvoice.client.user',
            'clientInvoice.items',
            'clientInvoice.paymentProofs',
            'uploadedBy',
            'approvedBy'
        ]);

        if (!empty($status)) {
            $query->where('status', $status);
        }
        if (!empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if (!empty($clientId)) {
            // Filter by the client attached to the invoice (remittance->client_id may be null)
            $query->whereHas('clientInvoice', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        }

        $remittances = $query->orderBy('created_at', 'desc')->paginate(15)->appends($request->query());

        // Provide client list options for admin filtering (optional for non-admins)
        $clientOptions = collect();
        try {
            if (auth()->check()) {
                $user = auth()->user();
                $isAdmin = false;
                // Resolve role names via relationship to avoid IDE diagnostics on trait methods
                try {
                    $roleNames = collect(optional($user->roles)->pluck('name'))->filter();
                    $isAdmin = $roleNames->contains('admin') || $roleNames->contains('super-admin');
                } catch (\Throwable $e) {}

                if ($isAdmin) {
                    // Use scopeFullNames to get a neatly ordered list of enabled client users
                    $clientOptions = \App\Client::fullNames();
                }
            }
        } catch (\Throwable $e) {
            // Swallow errors; filtering works without client options
        }

        return view('remittances.index', compact('remittances', 'clientOptions'));
    }

    public function create()
    {
        $clientInvoices = ClientInvoice::where('status', 'approved')
            ->whereDoesntHave('remittance')
            ->pluck('invoice_number', 'id');

        return view('remittances.create', compact('clientInvoices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_invoice_id' => 'required|exists:client_invoices,id',
            'remittance_file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Check if remittance already exists for this invoice
        $existingRemittance = Remittance::where('client_invoice_id', $request->client_invoice_id)->first();
        if ($existingRemittance) {
            return back()->withErrors(['client_invoice_id' => 'A remittance already exists for this invoice.']);
        }

        $file = $request->file('remittance_file');
        $filename = $this->generateRemittanceNumber() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $disk = env('REMITTANCES_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        $filePath = $file->storeAs('remittances', $filename, $disk);

        $remittance = Remittance::create([
            'remittance_number' => $this->generateRemittanceNumber(),
            'client_invoice_id' => $request->client_invoice_id,
            'amount' => $request->amount,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'notes' => $request->notes,
            'status' => 'pending',
            'uploaded_by' => Auth::id(),
        ]);

        // Notify admins/finance of new pending remittance
        try {
            Mail::to(config('app.to.address'))
                ->send(new RemittanceCreatedMail($remittance->load('clientInvoice.client')));
        } catch (\Throwable $e) {
            // Swallow email errors to avoid breaking upload flow
        }

        return redirect()->route('remittances.show', $remittance)
            ->with('success', 'Remittance uploaded successfully and is pending approval.');
    }

    public function show(Remittance $remittance)
    {
        $remittance->load(['clientInvoice', 'uploadedBy', 'approvedBy']);
        return view('remittances.show', compact('remittance'));
    }

    public function edit(Remittance $remittance)
    {
        if ($remittance->status !== 'pending') {
            return redirect()->route('remittances.show', $remittance)
                ->with('error', 'Only pending remittances can be edited.');
        }

        $clientInvoices = ClientInvoice::where('status', 'approved')
            ->where(function($query) use ($remittance) {
                $query->whereDoesntHave('remittance')
                      ->orWhere('id', $remittance->client_invoice_id);
            })
            ->pluck('invoice_number', 'id');

        return view('remittances.edit', compact('remittance', 'clientInvoices'));
    }

    public function update(Request $request, Remittance $remittance)
    {
        if ($remittance->status !== 'pending') {
            return redirect()->route('remittances.show', $remittance)
                ->with('error', 'Only pending remittances can be updated.');
        }

        $request->validate([
            'client_invoice_id' => 'required|exists:client_invoices,id',
            'remittance_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Check if changing invoice and another remittance exists
        if ($request->client_invoice_id != $remittance->client_invoice_id) {
            $existingRemittance = Remittance::where('client_invoice_id', $request->client_invoice_id)
                ->where('id', '!=', $remittance->id)
                ->first();
            if ($existingRemittance) {
                return back()->withErrors(['client_invoice_id' => 'A remittance already exists for this invoice.']);
            }
        }

        $updateData = [
            'client_invoice_id' => $request->client_invoice_id,
            'amount' => $request->amount,
            'notes' => $request->notes,
        ];

        // Handle file upload if new file provided
        if ($request->hasFile('remittance_file')) {
            // Delete old file
            $disk = env('REMITTANCES_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            if ($remittance->file_path && Storage::disk($disk)->exists($remittance->file_path)) {
                Storage::disk($disk)->delete($remittance->file_path);
            }

            $file = $request->file('remittance_file');
            $filename = $remittance->remittance_number . '_' . time() . '.' . $file->getClientOriginalExtension();
            $disk = env('REMITTANCES_DISK') ?: (app()->environment('production') ? 's3' : 'public');
            $filePath = $file->storeAs('remittances', $filename, $disk);

            $updateData['file_path'] = $filePath;
            $updateData['original_filename'] = $file->getClientOriginalName();
        }

        $remittance->update($updateData);

        return redirect()->route('remittances.show', $remittance)
            ->with('success', 'Remittance updated successfully.');
    }

    public function approve(Remittance $remittance)
    {
        if ($remittance->status !== 'pending') {
            return redirect()->route('remittances.show', $remittance)
                ->with('error', 'Only pending remittances can be approved.');
        }

        $remittance->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        // Notify client of approved remittance
        try {
            $remittance->load('clientInvoice.client');
            $recipient = optional(optional($remittance->clientInvoice)->client)->user;
            if ($recipient) {
                Mail::to($recipient)->send(new RemittanceApprovedMail($remittance));
            }
        } catch (\Throwable $e) {
            // Ignore mail errors, approval succeeded
        }

        return redirect()->route('remittances.show', $remittance)
            ->with('success', 'Remittance approved successfully.');
    }

    public function reject(Remittance $remittance)
    {
        if ($remittance->status !== 'pending') {
            return redirect()->route('remittances.show', $remittance)
                ->with('error', 'Only pending remittances can be rejected.');
        }

        $remittance->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        // Notify uploader of rejection
        try {
            $remittance->load('uploadedBy');
            if ($remittance->uploadedBy) {
                Mail::to($remittance->uploadedBy)->send(new RemittanceRejectedMail($remittance));
            }
        } catch (\Throwable $e) {
            // Ignore mail errors, rejection succeeded
        }

        return redirect()->route('remittances.show', $remittance)
            ->with('success', 'Remittance rejected.');
    }

    public function download(Remittance $remittance)
    {
        $disk = env('REMITTANCES_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        if (!$remittance->file_path || !Storage::disk($disk)->exists($remittance->file_path)) {
            return redirect()->route('remittances.show', $remittance)
                ->with('error', 'File not found.');
        }
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
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $remittance->file_path) : $remittance->file_path;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+30 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
                return redirect()->route('remittances.show', $remittance)
                    ->with('error', 'Unable to download from S3.');
            }
        }

        return response()->download(
            storage_path('app/public/' . $remittance->file_path),
            $remittance->original_filename ?? 'remittance_' . $remittance->remittance_number
        );
    }

    private function generateRemittanceNumber()
    {
        $prefix = 'REM';
        $year = date('Y');
        $month = date('m');
        
        $lastRemittance = Remittance::where('remittance_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('remittance_number', 'desc')
            ->first();

        if ($lastRemittance) {
            $lastNumber = intval(substr($lastRemittance->remittance_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
