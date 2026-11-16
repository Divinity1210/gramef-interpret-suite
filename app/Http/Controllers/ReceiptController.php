<?php

namespace App\Http\Controllers;

use App\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    /**
     * Stream the receipt PDF in the browser (inline view/print).
     */
    public function view(Receipt $receipt)
    {
        $path = $receipt->pdf_path;
        $disk = env('RECEIPTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        if (!$path || !Storage::disk($disk)->exists($path)) {
            return back()->with('error', 'Receipt file not found.');
        }
        $filename = 'receipt_' . ($receipt->receipt_number ?? $receipt->id) . '.pdf';
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
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $path) : $path;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
                return back()->with('error', 'Unable to view receipt from S3.');
            }
        }
        $fullPath = storage_path('app/public/' . $path);
        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Download the receipt PDF.
     */
    public function download(Receipt $receipt)
    {
        $path = $receipt->pdf_path;
        $disk = env('RECEIPTS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        if (!$path || !Storage::disk($disk)->exists($path)) {
            return back()->with('error', 'Receipt file not found.');
        }
        $filename = 'receipt_' . ($receipt->receipt_number ?? $receipt->id) . '.pdf';
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
                $key = trim(env('AWS_PREFIX', ''), '/') ? (trim(env('AWS_PREFIX', ''), '/') . '/' . $path) : $path;
                $cmd = $client->getCommand('GetObject', ['Bucket' => env('AWS_BUCKET'), 'Key' => $key]);
                $req = $client->createPresignedRequest($cmd, '+15 minutes');
                return redirect()->away((string) $req->getUri());
            } catch (\Throwable $e) {
                return back()->with('error', 'Unable to download receipt from S3.');
            }
        }
        $fullPath = storage_path('app/public/' . $path);
        return response()->download($fullPath, $filename);
    }
}