<?php

namespace App\Http\Controllers\Api;

use App\AuditLog;
use App\BankAccount;
use App\Agent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Helpers\RoleHelper;

class BankAccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $account = BankAccount::where('user_id', $user->id)->first();
        $this->log('view', $user->id, 'BankAccount', optional($account)->id, [ 'self' => true ]);
        if (!$account) {
            return response()->json([ 'exists' => false ]);
        }
        return response()->json([
            'exists' => true,
            'bank_name' => $account->bank_name,
            'account_holder' => $account->account_holder ?: ($user->full_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))),
            'account_number_masked' => $account->account_number_masked,
            'sort_code_masked' => $account->sort_code_masked,
            // provide full details explicitly for own user
            'account_number' => $account->account_number_full,
            'sort_code' => $account->sort_code_full,
            'consent' => (bool)$account->consent,
            'consent_at' => optional($account->consent_at)->toIso8601String(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->all();
        $validator = Validator::make($data, [
            'bank_name' => 'required|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'account_number' => 'required|string|min:6|max:34',
            'sort_code' => 'nullable|string|min:6|max:20',
            'consent' => 'accepted',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $retainDays = config('bank.retention_days', 365);
        $account = BankAccount::updateOrCreate(
            ['user_id' => $user->id],
            [
                'bank_name' => $data['bank_name'],
                'account_holder' => $data['account_holder'] ?? null,
                'account_number' => $data['account_number'],
                'sort_code' => $data['sort_code'] ?? null,
                'consent' => true,
                'consent_at' => now(),
                'retain_until' => now()->addDays($retainDays)->toDateString(),
            ]
        );

        $this->log('create', $user->id, 'BankAccount', $account->id, []);

        return response()->json([
            'success' => true,
            'message' => 'Banking details saved',
            'account' => [
                'bank_name' => $account->bank_name,
                'account_holder' => $account->account_holder,
                'account_number_masked' => $account->account_number_masked,
                'sort_code_masked' => $account->sort_code_masked,
            ]
        ], 201);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $account = BankAccount::where('user_id', $user->id)->first();
        if (!$account) {
            return $this->store($request);
        }
        $data = $request->all();
        $validator = Validator::make($data, [
            'bank_name' => 'required|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'account_number' => 'required|string|min:6|max:34',
            'sort_code' => 'nullable|string|min:6|max:20',
            'consent' => 'accepted',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $retainDays = config('bank.retention_days', 365);
        $account->fill([
            'bank_name' => $data['bank_name'],
            'account_holder' => $data['account_holder'] ?? null,
            'account_number' => $data['account_number'],
            'sort_code' => $data['sort_code'] ?? null,
            'consent' => true,
            'consent_at' => now(),
            'retain_until' => now()->addDays($retainDays)->toDateString(),
        ])->save();

        $this->log('update', $user->id, 'BankAccount', $account->id, []);

        return response()->json([
            'success' => true,
            'message' => 'Banking details updated',
            'account' => [
                'bank_name' => $account->bank_name,
                'account_holder' => $account->account_holder,
                'account_number_masked' => $account->account_number_masked,
                'sort_code_masked' => $account->sort_code_masked,
            ]
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $account = BankAccount::where('user_id', $user->id)->first();
        if (!$account) {
            return response()->json(['success' => true]);
        }
        $this->log('delete', $user->id, 'BankAccount', $account->id, ['rtbf' => true]);
        $account->forceDelete();
        return response()->json(['success' => true, 'message' => 'Banking details deleted']);
    }

    // Admin can view an agent's bank details for payment processing
    public function showAgent(Request $request, Agent $agent)
    {
        $admin = $request->user();
        if (!RoleHelper::hasRole($admin, 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $account = BankAccount::where('user_id', $agent->user_id)->first();
        $this->log('view', $agent->user_id, 'BankAccount', optional($account)->id, [ 'admin' => true ]);
        if (!$account) {
            return response()->json(['exists' => false]);
        }
        return response()->json([
            'exists' => true,
            'bank_name' => $account->bank_name,
            'account_holder' => $account->account_holder ?: data_get($agent, 'user.full_name', ''),
            'account_number' => $account->account_number_full,
            'sort_code' => $account->sort_code_full,
            'consent' => (bool)$account->consent,
            'consent_at' => optional($account->consent_at)->toIso8601String(),
        ]);
    }

    protected function log(string $action, ?int $userId, ?string $entityType, ?int $entityId, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'actor_id' => optional(Auth::user())->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => $meta,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Avoid breaking flow due to audit failures
        }
    }
}