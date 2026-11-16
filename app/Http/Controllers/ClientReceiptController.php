<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Receipt;
use App\Services\ReceiptPdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClientReceiptController extends Controller
{
    protected $receiptPdfService;

    public function __construct(ReceiptPdfService $receiptPdfService)
    {
        $this->receiptPdfService = $receiptPdfService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $client = $user->client; // assumes User has client relationship

        $filters = [
            'date_range_start' => $request->query('date_range_start'),
            'date_range_end' => $request->query('date_range_end'),
        ];

        $query = Receipt::with(['clientInvoice.company'])
            ->whereHas('clientInvoice', function ($q) use ($client) {
                $q->where('client_id', optional($client)->id);
            });

        if ($filters['date_range_start']) {
            $query->whereDate('issued_at', '>=', $filters['date_range_start']);
        }
        if ($filters['date_range_end']) {
            $query->whereDate('issued_at', '<=', $filters['date_range_end']);
        }

        $receipts = $query->orderByDesc('issued_at')->paginate(15)->withQueryString();

        return view('receipts.client.index', compact('receipts', 'filters'));
    }

    public function show(Receipt $receipt)
    {
        $user = Auth::user();
        $client = $user->client;

        // Ensure the receipt belongs to the authenticated client
        $receipt->loadMissing(['clientInvoice.company']);
        if (!$receipt->clientInvoice) {
            abort(404, 'Receipt not found.');
        }

        if ((int) $receipt->clientInvoice->client_id !== (int) optional($client)->id) {
            abort(403, 'Unauthorized access to receipt.');
        }

        return view('receipts.client.show', compact('receipt'));
    }

    public function download(Receipt $receipt)
    {
        $user = Auth::user();
        $client = $user->client;

        // Ensure the receipt belongs to the authenticated client
        $receipt->loadMissing(['clientInvoice']);
        if (!$receipt->clientInvoice) {
            abort(404, 'Receipt not found.');
        }
        if ((int) $receipt->clientInvoice->client_id !== (int) optional($client)->id) {
            abort(403, 'Unauthorized access to receipt.');
        }

        // Generate PDF if it doesn't exist
        if (!$receipt->pdf_path || !$this->receiptPdfService->receiptPdfExists($receipt)) {
            $this->receiptPdfService->generateReceiptPdf($receipt);
            $receipt->refresh();
        }

        $path = $receipt->pdf_path;
        $disk = env('RECEIPTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        if (!$path || !Storage::disk($disk)->exists($path)) {
            abort(404, 'Receipt PDF not found.');
        }

        $filename = 'receipt-' . $receipt->receipt_number . '.pdf';

        if ($disk === 's3') {
            try {
                $s3Client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => env('AWS_DEFAULT_REGION'),
                    'credentials' => [
                        'key' => env('AWS_ACCESS_KEY_ID'),
                        'secret' => env('AWS_SECRET_ACCESS_KEY'),
                    ],
                ]);
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $path) : $path;
                $cmd = $s3Client->getCommand('GetObject', [
                    'Bucket' => env('AWS_BUCKET'),
                    'Key' => $key,
                    'ResponseContentDisposition' => 'attachment; filename="' . $filename . '"',
                    'ResponseContentType' => 'application/pdf',
                ]);
                $req = $s3Client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
                abort(404, 'Unable to download receipt from S3.');
            }
        }

        $abs = storage_path('app/public/' . $path);
        return response()->download($abs, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
