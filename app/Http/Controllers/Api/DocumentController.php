<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $disk = env('TRANSLATOR_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        return response()->json([
            'file_name' => $request->file('file')->getClientOriginalName(),
            'file_path' => $request->file('file')->store('translator-jobs', $disk)
        ]);
    }

    public function destroy(Request $request)
    {
        $disk = env('TRANSLATOR_DOCS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        Storage::disk($disk)->delete($request->path);

        return response()->json([
            'file_path' => $request->path
        ]);
    }
}
