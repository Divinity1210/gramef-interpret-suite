<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemittanceController extends Controller
{
    /**
     * List remittances for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            if (!$this->tableExists('remittances')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
                ]);
            }

            $user = $request->user();
            $perPage = (int) ($request->input('per_page', 15));

            $query = DB::table('remittances')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            if ($request->has('status') && $request->input('status')) {
                $query->where('status', $request->input('status'));
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
            error_log('MOBILE_API_ERROR remittances.index ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]);
        }
    }

    /**
     * Show a specific remittance
     */
    public function show(Request $request, $id)
    {
        try {
            if (!$this->tableExists('remittances')) {
                return response()->json(['success' => false, 'message' => 'Remittance not found'], 404);
            }

            $remittance = DB::table('remittances')->where('id', $id)->first();

            if (!$remittance) {
                return response()->json(['success' => false, 'message' => 'Remittance not found'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $remittance,
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR remittances.show ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Unable to load remittance'], 503);
        }
    }

    /**
     * Create a new remittance
     */
    public function store(Request $request)
    {
        try {
            if (!$this->tableExists('remittances')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Remittance feature is not yet available',
                ], 501);
            }

            $user = $request->user();

            $id = DB::table('remittances')->insertGetId([
                'user_id' => $user->id,
                'amount' => $request->input('amount', 0),
                'reference' => $request->input('reference', ''),
                'notes' => $request->input('notes', ''),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Remittance created successfully',
                'data' => ['id' => $id],
            ], 201);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR remittances.store ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to create remittance',
            ], 500);
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
