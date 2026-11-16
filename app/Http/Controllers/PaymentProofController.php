<?php

namespace App\Http\Controllers;

use App\ClientInvoice;
use App\Document;
use App\PaymentProof;
use App\Receipt;
use App\Mail\PaymentProofSubmittedMail;
use App\Mail\PaymentProofVerifiedMail;
use App\Mail\PaymentProofRejectedMail;
use App\Services\ReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PaymentProofController extends Controller
{
    // Client submits a new payment proof with one or more files
    public function store(Request $request, $invoiceId = null)
    {
        // Support both RESTful route param and a fallback form post without the {id} parameter
        // If the route did not provide an ID (e.g., form posted to /invoices/client), use the hidden invoice_id input
        $invoiceId = $invoiceId ?? $request->input('invoice_id');

        if (empty($invoiceId)) {
            return back()->with('error', 'Unable to determine invoice. Please try again by clicking "Submit Payment Proof" on the specific invoice row.');
        }

        $request->validate([
            'amount' => ['required','numeric','min:0.01'],
            'paid_at' => ['nullable','date'],
            'method' => ['nullable','string','max:100'],
            'reference' => ['nullable','string','max:190'],
            'notes' => ['nullable','string','max:2000'],
            'files' => ['required','array','min:1'],
            'files.*' => ['file','mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx','max:10240'],
        ]);

        $user = Auth::user();
        $client = optional($user)->client;

        $invoice = ClientInvoice::where('client_id', optional($client)->id)
            ->findOrFail($invoiceId);

        $proof = PaymentProof::create([
            'client_invoice_id' => $invoice->id,
            'client_id' => optional($client)->id,
            'submitted_by' => $user->id,
            'amount' => $request->amount,
            'currency' => 'GBP',
            'paid_at' => $request->paid_at,
            'method' => $request->method,
            'reference' => $request->reference,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        // Store documents on configured disk (prefer S3 in production)
        $disk = env('PAYMENT_PROOFS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        foreach ($request->file('files') as $file) {
            $path = $file->store('payment-proofs/'. $proof->id, $disk);

            // Default to a generic document type (integer) to satisfy not-null constraint
            $defaultDocType = config('enums.document_types.file_to_be_translated');

            $proof->documents()->create([
                'name' => $file->getClientOriginalName(),
                'url' => $path,
                'type' => $defaultDocType,
            ]);
        }

        // Notify admins for verification
        try {
            $to = trim((string) config('app.to.address'));
            if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                Mail::to($to)->send(new PaymentProofSubmittedMail($invoice, $proof));
            }
        } catch (\Throwable $e) {
            // Silently ignore notification errors to not block user flow
        }

        return back()->with('success', 'Payment proof submitted. Our team will verify it shortly.');
    }

    // Client can delete their own pending proof
    public function destroy(PaymentProof $paymentProof)
    {
        $this->authorize('delete', $paymentProof);

        if ($paymentProof->status !== 'pending') {
            return back()->with('error', 'Only pending payment proofs can be deleted.');
        }

        // Delete associated files
        $disk = env('PAYMENT_PROOFS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        foreach ($paymentProof->documents as $doc) {
            $url = $doc->url;
            if ($url && Storage::disk($disk)->exists($url)) {
                Storage::disk($disk)->delete($url);
            }
            $doc->delete();
        }

        $paymentProof->delete();
        return back()->with('success', 'Payment proof removed.');
    }

    // Admin verification
    public function verify(Request $request, PaymentProof $paymentProof)
    {
        $this->authorize('verify', $paymentProof);

        if ($paymentProof->status !== 'pending') {
            return back()->with('error', 'Only pending payment proofs can be verified.');
        }

        $paymentProof->update([
            'status' => 'verified',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        // Recompute invoice paid status
        $invoice = $paymentProof->clientInvoice()->with('paymentProofs')->first();
        $verifiedTotal = $invoice->paymentProofs()->where('status','verified')->sum('amount');

        if ($verifiedTotal >= (float) $invoice->total && $invoice->status !== 'paid') {
            $invoice->update(['status' => 'paid']);
        }

        // Create a simple receipt per verified proof (aggregate receipts can be done later)
        $receiptNumber = $this->generateReceiptNumber();
        $receipt = Receipt::create([
            'client_invoice_id' => $invoice->id,
            'receipt_number' => $receiptNumber,
            'total_paid' => $paymentProof->amount,
            'issued_at' => now(),
            'generated_by' => Auth::id(),
            'payment_proof_ids' => [$paymentProof->id],
        ]);

        // Generate PDF for the receipt
        $receiptPdfService = new ReceiptPdfService();
        $receiptPdfService->generateReceiptPdf($receipt);

        // Notify client of verification
        try {
            $clientEmail = optional(optional($invoice->client)->user)->email;
            if ($clientEmail) {
                Mail::to($clientEmail)->send(new PaymentProofVerifiedMail($invoice, $paymentProof, $receipt));
            }
        } catch (\Throwable $e) {
            // Ignore mail errors
        }

        return back()->with('success', 'Payment proof verified. Receipt issued: '. $receiptNumber);
    }

    public function reject(Request $request, PaymentProof $paymentProof)
    {
        $this->authorize('verify', $paymentProof);
        $request->validate([
            'reason' => ['required','string','max:1000']
        ]);

        if ($paymentProof->status !== 'pending') {
            return back()->with('error', 'Only pending payment proofs can be rejected.');
        }

        $paymentProof->update([
            'status' => 'rejected',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'rejection_reason' => $request->reason,
        ]);

        // Notify client of rejection
        try {
            $invoice = $paymentProof->clientInvoice()->with('client.user')->first();
            $clientEmail = optional(optional($invoice->client)->user)->email;
            if ($clientEmail) {
                Mail::to($clientEmail)->send(new PaymentProofRejectedMail($invoice, $paymentProof));
            }
        } catch (\Throwable $e) {
            // Ignore mail errors
        }

        return back()->with('success', 'Payment proof rejected.');
    }

    private function generateReceiptNumber(): string
    {
        $prefix = 'RCPT';
        $date = date('Ymd');
        $random = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4);
        return $prefix . '-' . $date . '-' . $random;
    }
}
