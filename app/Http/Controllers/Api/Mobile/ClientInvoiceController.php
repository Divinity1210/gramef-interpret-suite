<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientInvoiceController extends Controller
{
    /**
     * List client invoices for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Check if the client_invoices table exists
            if (!$this->tableExists('client_invoices')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
                ]);
            }

            $perPage = (int) ($request->input('per_page', 15));
            $query = DB::table('client_invoices')
                ->where('client_id', optional($user->client)->id ?? 0)
                ->orderBy('created_at', 'desc');

            if ($request->has('status') && $request->input('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->has('from_date') && $request->input('from_date')) {
                $query->where('created_at', '>=', $request->input('from_date'));
            }
            if ($request->has('to_date') && $request->input('to_date')) {
                $query->where('created_at', '<=', $request->input('to_date') . ' 23:59:59');
            }

            $paginated = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $paginated->items(),
                'meta' => [
                    'total' => $paginated->total(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR client-invoices.index ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]);
        }
    }

    /**
     * Show a specific client invoice
     */
    public function show(Request $request, $id)
    {
        try {
            if (!$this->tableExists('client_invoices')) {
                return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
            }

            $invoice = DB::table('client_invoices')->where('id', $id)->first();

            if (!$invoice) {
                return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $invoice,
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR client-invoices.show ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to load invoice'], 503);
        }
    }

    /**
     * Download client invoice PDF (stub)
     */
    public function pdf(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'PDF generation not yet available for client invoices',
        ], 501);
    }

    private function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
