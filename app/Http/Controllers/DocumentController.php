<?php

namespace App\Http\Controllers;

use App\Document;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function show(\App\Document $document)
    {
        $urlAttr = $document->url;
        if (empty($urlAttr)) {
            abort(404);
        }
        $isTranslatorDoc = $document->documentable_type === 'App\\TranslatorJob' || strpos($urlAttr, 'translator-jobs/') !== false;
        $isInterpreterDoc = $document->documentable_type === 'App\\InterpreterJob' || strpos($urlAttr, 'interpreter-jobs/') !== false;
        $isPaymentProofDoc = $document->documentable_type === 'App\\PaymentProof' || strpos($urlAttr, 'payment-proofs/') !== false;
        $isAgentDoc = $document->documentable_type === 'App\\Agent' || strpos($urlAttr, 'agents/') !== false;
        if ($isTranslatorDoc) {
            $disk = env('TRANSLATOR_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isInterpreterDoc) {
            $disk = env('INTERPRETER_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isPaymentProofDoc) {
            $disk = env('PAYMENT_PROOFS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isAgentDoc) {
            $disk = env('AGENT_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } else {
            $disk = 'public';
        }

        $relativePath = ltrim($urlAttr, '/');
        if (strpos($relativePath, 'storage/') === 0) { $relativePath = substr($relativePath, 8); }
        if (strpos($relativePath, 'public/') === 0) { $relativePath = substr($relativePath, 7); }

        if ($disk === 'public') {
            $abs = storage_path('app/public/' . $relativePath);
            if (!file_exists($abs)) {
                abort(404);
            }
            $mime = \Illuminate\Support\Facades\File::mimeType($abs) ?: 'application/octet-stream';
            return response()->file($abs, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        if ($disk === 's3') {
            $prefix = trim(env('AWS_PREFIX', ''), '/');
            $keyWithPrefix = $prefix ? ($prefix . '/' . $relativePath) : $relativePath;
            $keyNoPrefix = $relativePath;

            try {
                $cfg = config('filesystems.disks.s3');
                $bucket = $cfg['bucket'] ?? env('AWS_BUCKET');
                $region = $cfg['region'] ?? env('AWS_DEFAULT_REGION', 'us-east-1');
                $keyId = $cfg['key'] ?? env('AWS_ACCESS_KEY_ID');
                $secret = $cfg['secret'] ?? env('AWS_SECRET_ACCESS_KEY');

                $client = new \Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => $region,
                    'credentials' => [ 'key' => $keyId, 'secret' => $secret ],
                ]);

                foreach ([$keyWithPrefix, $keyNoPrefix] as $key) {
                    try {
                        $client->headObject(['Bucket' => $bucket, 'Key' => $key]);
                        $cmd = $client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $key]);
                        $req = $client->createPresignedRequest($cmd, '+30 minutes');
                        $signed = (string) $req->getUri();
                        return redirect()->away($signed);
                    } catch (\Throwable) {
                    }
                }
            } catch (\Throwable) {
            }

            $absLocal = storage_path('app/public/' . $relativePath);
            if (file_exists($absLocal)) {
                $mime = \Illuminate\Support\Facades\File::mimeType($absLocal) ?: 'application/octet-stream';
                return response()->file($absLocal, [
                    'Content-Type' => $mime,
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            }

            abort(404);
        }

        abort(404);
    }
    public function destroy(Document $document)
    {
        $urlAttr = $document->url;
        $docType = $document->documentable_type;
        $isTranslatorDoc = $docType === 'App\\TranslatorJob' || strpos($urlAttr, 'translator-jobs/') !== false;
        $isInterpreterDoc = $docType === 'App\\InterpreterJob' || strpos($urlAttr, 'interpreter-jobs/') !== false;
        $isPaymentProofDoc = $docType === 'App\\PaymentProof' || strpos($urlAttr, 'payment-proofs/') !== false;
        $isAgentDoc = $docType === 'App\\Agent' || strpos($urlAttr, 'agents/') !== false;

        if ($isTranslatorDoc) {
            $disk = env('TRANSLATOR_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isInterpreterDoc) {
            $disk = env('INTERPRETER_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isPaymentProofDoc) {
            $disk = env('PAYMENT_PROOFS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } elseif ($isAgentDoc) {
            $disk = env('AGENT_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        } else {
            $disk = 'public';
        }

        if (Storage::disk($disk)->exists($urlAttr)) {
            Storage::disk($disk)->delete($urlAttr);
        }

        $document->delete();

        return redirect()->back()->with('success', 'Document removed.');
    }
}
