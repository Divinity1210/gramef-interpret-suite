<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Language;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * Return all active languages as id => name pairs
     */
    public function index()
    {
        try {
            $languages = Language::orderBy('name')
                ->get(['id', 'name'])
                ->map(function ($lang) {
                    return [
                        'id' => $lang->id,
                        'name' => $lang->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $languages,
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR languages.index ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load languages',
            ], 503);
        }
    }
}
