<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\User;
use App\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    /**
     * Handle mobile app login
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|string',
                'password' => 'required|string',
            ]);

            $email = strtolower(trim((string) $request->input('email')));
            $password = (string) $request->input('password');

            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (!$user || !Hash::check($password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if ($user->enabled !== null && !$user->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is disabled'
                ], 403);
            }

            $token = Str::random(60);
            $user->remember_token = hash('sha256', $token);
            $user->save();

            $roles = [];
            try {
                $roles = $user->getRoleNames()->values()->all();
            } catch (Throwable $e) {
                $roles = [];
            }

            $agent = null;
            try {
                $agent = $user->agent;
                if ($agent) {
                    $agent->load(['skills', 'languages']);
                    $agent->profile_picture_url = $agent->getProfilePicture();
                    // S3 fallback if getProfilePicture() returns null
                    if (!$agent->profile_picture_url && $agent->profile_picture) {
                        try {
                            $bucket = env('AWS_BUCKET', 'atozportal');
                            $region = env('AWS_DEFAULT_REGION', 'eu-west-1');
                            $awsKey = env('AWS_ACCESS_KEY_ID');
                            $awsSecret = env('AWS_SECRET_ACCESS_KEY');
                            $prefix = trim(env('AWS_PREFIX', ''), '/');
                            $fullKey = $prefix ? ($prefix . '/' . $agent->profile_picture) : $agent->profile_picture;

                            if ($awsKey && $awsSecret) {
                                $s3 = new \Aws\S3\S3Client([
                                    'version' => 'latest',
                                    'region' => $region,
                                    'credentials' => ['key' => $awsKey, 'secret' => $awsSecret],
                                ]);
                                $cmd = $s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $fullKey]);
                                $agent->profile_picture_url = (string) $s3->createPresignedRequest($cmd, '+60 minutes')->getUri();
                            } else {
                                $agent->profile_picture_url = "https://{$bucket}.s3.{$region}.amazonaws.com/{$fullKey}";
                            }
                        } catch (\Throwable $e) {
                            $bucket = env('AWS_BUCKET', 'atozportal');
                            $region = env('AWS_DEFAULT_REGION', 'eu-west-1');
                            $prefix = trim(env('AWS_PREFIX', ''), '/');
                            $fullKey = $prefix ? ($prefix . '/' . $agent->profile_picture) : $agent->profile_picture;
                            $agent->profile_picture_url = "https://{$bucket}.s3.{$region}.amazonaws.com/{$fullKey}";
                        }
                    }
                    $agent->agent_type = $agent->getAgentType();
                }
            } catch (Throwable $e) {
                $agent = null;
            }

            $userPayload = [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->fullName,
                'roles' => $roles,
                'agent' => $agent,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $userPayload,
                'data' => [
                    'user' => $userPayload,
                    'token' => $token,
                ]
            ]);
        } catch (Throwable $e) {
            error_log('MOBILE_API_ERROR auth.login ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Login failed'
            ], 503);
        }
    }

    /**
     * Get currently authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $roles = [];
            try {
                $roles = $user->getRoleNames()->values()->all();
            } catch (Throwable $e) {
                $roles = [];
            }

            $agentData = null;
            try {
                $agent = $user->agent;
                if ($agent) {
                    $agent->load(['skills', 'languages', 'contactMethods']);

                    $agentData = [
                        'id' => $agent->id,
                        'contact_number' => $user->contact_number ?? $agent->contact_number ?? null,
                        'gender' => $agent->gender,
                        'address_line_1' => $agent->address_line_1,
                        'address_line_2' => $agent->address_line_2,
                        'county' => $agent->county,
                        'postcode' => $agent->postcode,
                        'can_provide_affidavit' => (bool) $agent->can_provide_affidavit,
                        'can_provide_affirmation' => (bool) $agent->can_provide_affirmation,
                        'skype_details' => $agent->skype_details,
                        'profile_picture' => $agent->profile_picture,
                        'profile_picture_url' => (function () use ($agent) {
                            // Try the model's built-in method first
                            $url = $agent->getProfilePicture();
                            if ($url)
                                return $url;

                            // If no profile_picture path, nothing to show
                            $path = $agent->profile_picture;
                            if (!$path)
                                return null;

                            // Fallback: generate S3 pre-signed URL directly
                            try {
                                $bucket = env('AWS_BUCKET', 'atozportal');
                                $region = env('AWS_DEFAULT_REGION', 'eu-west-1');
                                $key = env('AWS_ACCESS_KEY_ID');
                                $secret = env('AWS_SECRET_ACCESS_KEY');
                                $prefix = trim(env('AWS_PREFIX', ''), '/');
                                $fullKey = $prefix ? ($prefix . '/' . $path) : $path;

                                if ($key && $secret) {
                                    $client = new \Aws\S3\S3Client([
                                        'version' => 'latest',
                                        'region' => $region,
                                        'credentials' => [
                                            'key' => $key,
                                            'secret' => $secret,
                                        ],
                                    ]);
                                    $cmd = $client->getCommand('GetObject', [
                                        'Bucket' => $bucket,
                                        'Key' => $fullKey,
                                    ]);
                                    $req = $client->createPresignedRequest($cmd, '+60 minutes');
                                    return (string) $req->getUri();
                                }
                            } catch (\Throwable $e) {
                                // Log but don't crash
                                error_log('MOBILE_API profile_picture S3 fallback error: ' . $e->getMessage());
                            }

                            // Last resort: try direct S3 URL (works if bucket allows public read)
                            $bucket = env('AWS_BUCKET', 'atozportal');
                            $region = env('AWS_DEFAULT_REGION', 'eu-west-1');
                            $prefix = trim(env('AWS_PREFIX', ''), '/');
                            $fullKey = $prefix ? ($prefix . '/' . $path) : $path;
                            return "https://{$bucket}.s3.{$region}.amazonaws.com/{$fullKey}";
                        })(),
                        'agent_type' => $agent->getAgentType(),
                        'dbs_number' => $agent->dbs_number,
                        'dbs_expiry_date' => optional($agent->dbs_expiry_date)->toDateString(),
                        'dbs_update_reference_number' => $agent->dbs_update_reference_number,
                        'induction_date' => optional($agent->induction_date)->toDateString(),
                        'dbs_level_id' => $agent->dbs_level_id,
                        'latitude' => $agent->latitude,
                        'longitude' => $agent->longitude,
                        'bank_name' => null,
                        'account_holder' => null,
                        'account_number' => null,
                        'sort_code' => null,
                        'skills' => $agent->skills->map(function ($s) {
                            return ['id' => $s->id, 'name' => $s->skill];
                        })->values(),
                        'languages' => $agent->languages->map(function ($l) {
                            return ['id' => $l->id, 'name' => $l->name];
                        })->values(),
                        'contact_methods' => $agent->contactMethods->map(function ($cm) {
                            return ['id' => $cm->id, 'name' => $cm->contact_method];
                        })->values(),
                    ];
                }
            } catch (Throwable $e) {
                $agentData = null;
            }

            // Banking details from bank_accounts table (same as web/ProfileController)
            $bankData = [
                'bank_name' => '',
                'account_holder' => '',
                'account_number' => '',
                'sort_code' => '',
            ];
            try {
                $bankAccount = BankAccount::where('user_id', $user->id)->first();
                if ($bankAccount) {
                    $bankData['bank_name'] = $bankAccount->bank_name ?? '';
                    $bankData['account_holder'] = $bankAccount->account_holder ?? '';
                    $bankData['account_number'] = $bankAccount->account_number_full ?? '';
                    $bankData['sort_code'] = $bankAccount->sort_code_full ?? '';
                }
                // Also populate the agent data with bank details
                if ($agentData) {
                    $agentData['bank_name'] = $bankData['bank_name'];
                    $agentData['account_holder'] = $bankData['account_holder'];
                    $agentData['account_number'] = $bankData['account_number'];
                    $agentData['sort_code'] = $bankData['sort_code'];
                }
            } catch (Throwable $e) {
                // Bank account lookup failed, use defaults
            }

            return response()->json([
                'success' => true,
                'data' => array_merge([
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->fullName,
                    'title' => $user->title,
                    'contact_number' => $user->contact_number,
                    'roles' => $roles,
                    'agent' => $agentData,
                ], $bankData)
            ]);
        } catch (Throwable $e) {
            error_log('MOBILE_API_ERROR auth.me ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Profile unavailable'
            ], 503);
        }
    }

    /**
     * Update user profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $request->validate([
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|max:255',
                'contact_number' => 'nullable|string|max:50',
                'title' => 'nullable|string|max:50',
                'gender' => 'nullable|integer|in:0,1',
                'address_line_1' => 'nullable|string|max:255',
                'address_line_2' => 'nullable|string|max:255',
                'county' => 'nullable|string|max:255',
                'postcode' => 'nullable|string|max:20',
                'skype_details' => 'nullable|string|max:500',
                'can_provide_affidavit' => 'nullable|boolean',
                'can_provide_affirmation' => 'nullable|boolean',
                'bank_name' => 'nullable|string|max:255',
                'account_holder' => 'nullable|string|max:255',
                'account_number' => 'nullable|string|max:50',
                'sort_code' => 'nullable|string|max:20',
                'password' => 'nullable|string|min:6',
            ]);

            // Update user fields
            foreach (['first_name', 'last_name', 'email', 'title', 'contact_number'] as $field) {
                if ($request->has($field)) {
                    $user->$field = $request->$field;
                }
            }
            if ($request->filled('password')) {
                $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
            }
            $user->save();

            // Update agent fields
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
                    'bank_name',
                    'account_holder',
                    'account_number',
                    'sort_code',
                ];
                foreach ($agentFields as $field) {
                    if ($request->has($field)) {
                        $agent->$field = $request->$field;
                    }
                }
                $agent->save();
            }

            $roles = [];
            try {
                $roles = $user->getRoleNames()->values()->all();
            } catch (\Throwable $e) {
                $roles = [];
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->fullName,
                    'contact_number' => $user->contact_number,
                    'title' => $user->title,
                    'roles' => $roles,
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('MOBILE_API_ERROR auth.updateProfile ' . get_class($e) . ' msg=' . substr((string) $e->getMessage(), 0, 300));

            return response()->json([
                'success' => false,
                'message' => 'Profile update failed'
            ], 503);
        }
    }
}
