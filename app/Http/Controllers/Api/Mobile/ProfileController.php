<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\BankAccount;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Show the authenticated user's profile (agent details)
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            $profile = [
                'id' => $user->id,
                'title' => $user->title ?? '',
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email ?? '',
                'contact_number' => $user->contact_number ?? '',
            ];

            // Safely try to get agent data
            try {
                $agent = $user->agent;
                if ($agent) {
                    $profile['agent_id'] = $agent->id;
                    $profile['gender'] = $agent->gender;
                    $profile['address_line_1'] = $agent->address_line_1 ?? '';
                    $profile['address_line_2'] = $agent->address_line_2 ?? '';
                    $profile['county'] = $agent->county ?? '';
                    $profile['postcode'] = $agent->postcode ?? '';
                    $profile['skype_details'] = $agent->skype_details ?? '';
                    $profile['can_provide_affidavit'] = (bool) ($agent->can_provide_affidavit ?? false);
                    $profile['can_provide_affirmation'] = (bool) ($agent->can_provide_affirmation ?? false);

                    // Banking details from bank_accounts table (same as web version)
                    try {
                        $bankAccount = BankAccount::where('user_id', $user->id)->first();
                        if ($bankAccount) {
                            $profile['bank_name'] = $bankAccount->bank_name ?? '';
                            $profile['account_holder'] = $bankAccount->account_holder ?? '';
                            $profile['account_number'] = $bankAccount->account_number_full ?? '';
                            $profile['sort_code'] = $bankAccount->sort_code_full ?? '';
                        } else {
                            $profile['bank_name'] = '';
                            $profile['account_holder'] = '';
                            $profile['account_number'] = '';
                            $profile['sort_code'] = '';
                        }
                    } catch (\Throwable $e) {
                        $profile['bank_name'] = '';
                        $profile['account_holder'] = '';
                        $profile['account_number'] = '';
                        $profile['sort_code'] = '';
                    }

                    // Languages (safely)
                    try {
                        $profile['languages'] = $agent->languages()
                            ->get(['languages.id', 'languages.name'])
                            ->map(function ($l) {
                                return ['id' => $l->id, 'name' => $l->name];
                            })
                            ->values();
                    } catch (\Throwable $e) {
                        $profile['languages'] = [];
                    }

                    // Skills (safely)
                    try {
                        $profile['skills'] = $agent->skills()
                            ->get(['skills.id', 'skills.name'])
                            ->map(function ($s) {
                                return ['id' => $s->id, 'name' => $s->name];
                            })
                            ->values();
                    } catch (\Throwable $e) {
                        $profile['skills'] = [];
                    }
                }
            } catch (\Throwable $e) {
                // Agent relationship failed — still return basic user profile
                error_log('MOBILE_API_WARNING profile.show agent-data ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => $profile,
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR profile.show ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load profile',
            ], 503);
        }
    }

    /**
     * Update the authenticated user's profile
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }

            // Update user fields
            $userFields = ['title', 'first_name', 'last_name', 'email', 'contact_number'];
            foreach ($userFields as $field) {
                if ($request->has($field)) {
                    $user->{$field} = $request->input($field);
                }
            }

            if ($request->has('password') && $request->input('password')) {
                $user->password = bcrypt($request->input('password'));
            }

            $user->save();

            // Update agent fields (safely) — excluding banking fields
            try {
                $agent = $user->agent;
                if ($agent) {
                    $agentFields = [
                        'gender',
                        'address_line_1',
                        'address_line_2',
                        'county',
                        'postcode',
                        'skype_details',
                        'can_provide_affidavit',
                        'can_provide_affirmation',
                    ];
                    foreach ($agentFields as $field) {
                        if ($request->has($field)) {
                            try {
                                $agent->{$field} = $request->input($field);
                            } catch (\Throwable $e) {
                                // Column may not exist, skip
                            }
                        }
                    }
                    $agent->save();
                }
            } catch (\Throwable $e) {
                error_log('MOBILE_API_WARNING profile.update agent ' . $e->getMessage());
            }

            // Update banking details — use bank_accounts table (same as web)
            $hasBankFields = $request->has('bank_name') || $request->has('account_number')
                || $request->has('sort_code') || $request->has('account_holder');
            if ($hasBankFields) {
                try {
                    $retainDays = config('bank.retention_days', 365);
                    BankAccount::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'bank_name' => $request->input('bank_name', ''),
                            'account_holder' => $request->input('account_holder', $user->full_name ?? ''),
                            'account_number' => $request->input('account_number', ''),
                            'sort_code' => $request->input('sort_code', ''),
                            'consent' => true,
                            'consent_at' => now(),
                            'retain_until' => now()->addDays($retainDays)->toDateString(),
                        ]
                    );
                } catch (\Throwable $e) {
                    error_log('MOBILE_API_WARNING profile.update bank ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to update banking details: ' . $e->getMessage(),
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR profile.update ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to update profile: ' . $e->getMessage(),
            ], 500);
        }
    }
}
