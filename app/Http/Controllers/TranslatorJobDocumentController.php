<?php

namespace App\Http\Controllers;

use App\TranslatorJob;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;


class TranslatorJobDocumentController extends Controller
{
    public function index(TranslatorJob $translatorJob)
    {
        return view('translator-jobs.documents.index', [
            'translatorJob' => $translatorJob->load('comments.user'),
        ]);
    }

    public function store(Request $request, TranslatorJob $translatorJob)
    {
        $request->validate([
            'file' => ['required', 'file'],
            'document_type' => ['required', Rule::in(config('enums.document_types'))],
        ]);
        // Choose storage disk dynamically: default to S3 in production, public disk locally unless overridden
        $disk = env('TRANSLATOR_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');

        $documentUrl = $request->file('file')->store("translator-jobs/$translatorJob->id", $disk);
        $documentName = $request->file('file')->getClientOriginalName();

        $existingDocument = $translatorJob->documents->firstWhere('type', $request->document_type);
        // Allow multiple uploads for translated_file type (ID 9); single-file replace for others
        $isTranslatedFile = (int) $request->document_type === (int) config('enums.document_types')['translated_file'];

        if ($existingDocument && !$isTranslatedFile) {
            Storage::disk($disk)->delete($existingDocument->url);
        }

        if ($isTranslatedFile) {
            $translatorJob->documents()->create([
                'type' => $request->document_type,
                'url' => $documentUrl,
                'name' => $documentName,
            ]);
        } else {
            $translatorJob->documents()->updateOrCreate(
                [
                    'type' => $request->document_type
                ],
                [
                    'url' => $documentUrl,
                    'name' => $documentName,
                ]
            );
        }

        // Build a full URL without relying on FilesystemAdapter::url() to avoid runtime/static issues
        $configuredBase = config("filesystems.disks.$disk.url");
        if (!$configuredBase) {
            if ($disk === 'public') {
                $configuredBase = url('/storage');
            } elseif ($disk === 's3') {
                $configuredBase = config('filesystems.disks.s3.url') ?: env('AWS_URL');
            } else {
                // Sensible fallback to app URL storage path
                $configuredBase = url('/storage');
            }
        }

        $fullUrl = rtrim($configuredBase, '/').'/'.ltrim($documentUrl, '/');

        return response()->json([
            'fullUrl' => $fullUrl,
            'name' => $documentName,
        ]);
    }
}
