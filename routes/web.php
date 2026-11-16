<?php
#

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Response;

Auth::routes(['register' => false, 'verify' => true]);

// GRAMEF InterpretFlow Public Product Showcase, 1-Click Demo Launchpad & No-Password Magic Links
Route::get('/showcase', 'ExecutiveUpgradeController@showcase')->name('showcase');
Route::post('/demo/quick-login/{role}', 'ExecutiveUpgradeController@demoQuickLogin')->name('demo.quick-login');
Route::get('/demo/quick-login/{role}', 'ExecutiveUpgradeController@demoQuickLogin')->name('demo.quick-login.get');
Route::get('/sign/timesheet/{token}', 'MagicTimesheetSignController@show')->name('magic.timesheet.show');
Route::post('/sign/timesheet/{token}', 'MagicTimesheetSignController@store')->name('magic.timesheet.store');
Route::get('/remittance/quick/{token}', 'MagicTimesheetSignController@showRemittance')->name('magic.remittance.show');
Route::post('/remittance/quick/{token}', 'MagicTimesheetSignController@storeRemittance')->name('magic.remittance.store');

Route::get('/setup-account/{token}', 'AccountController@create')->name('setup-account');

// Include test routes
require __DIR__ . '/test.php';

Route::get('/session/keep-alive', function () {
    session()->put('keep_alive', now());
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
})->name('session.keep-alive');

// Temporary cron diagnostic — remove after debugging
Route::get('/debug-cron-x7m3', function (\Illuminate\Http\Request $request) {
    $info = [
        'now_london' => now('Europe/London')->toDateTimeString(),
        'cache_driver' => config('cache.default'),
        'cron_last_finished' => \Illuminate\Support\Facades\Cache::get('interpreter_reminders:last_run_finished_at'),
        'cron_last_started' => \Illuminate\Support\Facades\Cache::get('interpreter_reminders:last_run_started_at'),
    ];

    // Read cron logs (last 50 lines)
    $logFiles = [
        'cron-schedule' => '/home/ont7mbe8n4rp/cron-schedule.log',
        'cron-reminders' => '/home/ont7mbe8n4rp/cron-reminders.log',
    ];
    foreach ($logFiles as $key => $path) {
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $info[$key] = array_slice($lines, -30);
        } else {
            $info[$key] = "File not found: {$path}";
        }
    }

    // Read Laravel log (last 30 lines with "reminder" or "error")
    $laravelLog = storage_path('logs/laravel.log');
    if (file_exists($laravelLog)) {
        $lines = file($laravelLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $reminderLines = array_filter($lines, fn($l) => stripos($l, 'reminder') !== false || stripos($l, 'Reminder') !== false);
        $info['laravel_log_reminder_lines'] = array_values(array_slice($reminderLines, -15));
    }

    // Optionally trigger the reminder command
    if ($request->query('run') === '1') {
        try {
            Artisan::call('send-interpreterjob-reminders');
            $info['manual_run_output'] = Artisan::output();
        } catch (\Throwable $e) {
            $info['manual_run_error'] = $e->getMessage();
        }
    }

    return response()->json($info, 200, [], JSON_PRETTY_PRINT);
});

/*
 |--------------------------------------------------------------------------
 | Static asset passthrough (for preview/dev environments)
 |--------------------------------------------------------------------------
 |
 | Some preview servers don't serve the Laravel public/ directory as the
 | document root, which causes requests like /js/app.js or /css/app.css to
 | be routed through Laravel and (incorrectly) redirected to /login.
 |
 | The following lightweight routes explicitly serve files from public/
 | without requiring authentication. They are safe and only return files
 | that actually exist under public/. They do not expose anything outside
 | the public directory.
 */
Route::get('/css/{path}', function (string $path) {
    $file = public_path('css/' . $path);
    if (!file_exists($file)) {
        abort(404);
    }
    return response()->file($file, [
        'Content-Type' => 'text/css',
        'Cache-Control' => 'public, max-age=604800', // 7 days
    ]);
})->where('path', '.*');

Route::get('/js/{path}', function (string $path) {
    $file = public_path('js/' . $path);
    if (!file_exists($file)) {
        abort(404);
    }
    return response()->file($file, [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->where('path', '.*');

Route::get('/img/{path}', function (string $path) {
    $file = public_path('img/' . $path);
    if (!file_exists($file)) {
        abort(404);
    }
    // Detect mime type safely
    $mime = function_exists('mime_content_type') ? mime_content_type($file) : null;
    if (!$mime) {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $map = [
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
        ];
        $mime = $map[strtolower($extension)] ?? 'application/octet-stream';
    }
    return response()->file($file, [
        'Content-Type' => $mime,
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->where('path', '.*');

Route::get('/favicon.ico', function () {
    $file = public_path('favicon.ico');
    if (!file_exists($file)) {
        abort(404);
    }
    return response()->file($file, [
        'Content-Type' => 'image/x-icon',
        'Cache-Control' => 'public, max-age=604800',
    ]);
});

// Local-only quick login route for preview testing
if (app()->environment('local') || app()->environment('testing')) {
    Route::get('/dev/login', function (\Illuminate\Http\Request $request) {
        $email = $request->query('email');
        $password = $request->query('password');

        if (Auth::check()) {
            return redirect('/');
        }
        if (!$email || !$password) {
            return response('Missing email or password', 400);
        }
        if (Auth::attempt(['email' => $email, 'password' => $password], true)) {
            return redirect('/admin/payslips');
        }
        return response('Invalid credentials', 401);
    })->name('dev.login');

    // Quick local-only admin login to verify admin pages without credentials
    Route::get('/dev/login-admin', function () {
        if (Auth::check()) {
            return redirect('/admins');
        }
        try {
            $admin = \App\User::role('admin')->where('enabled', 1)->first();
            if (!$admin) {
                return response('No enabled admin user found. Please create one via seeding or the UI.', 404);
            }
            Auth::login($admin, true);
            return redirect('/admins');
        } catch (\Throwable $e) {
            return response('Failed to auto-login admin: ' . $e->getMessage(), 500);
        }
    })->name('dev.login-admin');

    // Local preview for client invoices page (bypasses auth to quickly validate UI changes)
    Route::get('/dev/preview/client-invoices', function () {
        $filters = [];
        $invoices = \App\ClientInvoice::orderByDesc('id')->paginate(10);
        return view('invoices.client.index', compact('invoices', 'filters'));
    })->name('dev.preview.client-invoices');

    // Local preview for Admin Client Invoice Preview page (bypasses auth; iframe may show login)
    Route::get('/dev/preview/admin-client-invoice/{id?}', function ($id = null) {
        $query = \App\ClientInvoice::with(['client.user', 'client.organisation', 'company', 'items.job', 'paymentProofs.documents', 'paymentProofs.submitter', 'receipts', 'receipts.generator'])
            ->orderByDesc('id');
        $invoice = $id ? $query->find($id) : $query->first();
        if (!$invoice) {
            return response('No client invoices found to preview. Please create a ClientInvoice first.', 200);
        }

        // Compute the same variables the controller provides
        $mode = session('invoice_template_mode_' . $invoice->id) ?? 'auto';
        $distinctJobs = $invoice->items->pluck('job_id')->filter()->unique()->count();
        $hasMultipleDistinctJobs = $distinctJobs > 1;
        if ($hasMultipleDistinctJobs) {
            $effectiveTemplate = 'v3';
        } else {
            $effectiveTemplate = ($mode === 'v3' || ($invoice->template ?? null) === 'v3') ? 'v3' : 'v2';
        }

        $jobOptions = [];
        $existingJobIds = collect($invoice->items)->pluck('job_id')->filter()->unique()->values()->all();
        $clientId = optional($invoice->client)->id;
        try {
            if ($clientId) {
                $interpreterJobs = \App\InterpreterJob::where('client_id', $clientId)
                    ->orderByDesc('appointment_date')->limit(100)->get();
                foreach ($interpreterJobs as $j) {
                    $jobOptions[] = [
                        'id' => $j->id,
                        'type' => 'App\\InterpreterJob',
                        'label' => sprintf(
                            '#%d • %s • %s • Ref:%s',
                            $j->id,
                            optional($j->appointment_date)->format('Y-m-d'),
                            optional($j->toLanguage)->name,
                            $j->client_reference ?? 'N/A'
                        ),
                        'date' => optional($j->appointment_date)->format('Y-m-d'),
                        'ref' => $j->client_reference,
                    ];
                }
                $translatorJobs = \App\TranslatorJob::where('client_id', $clientId)
                    ->orderByDesc('target_date')->limit(100)->get();
                foreach ($translatorJobs as $j) {
                    $jobOptions[] = [
                        'id' => $j->id,
                        'type' => 'App\\TranslatorJob',
                        'label' => sprintf(
                            '#%d • %s • %s → %s • Ref:%s',
                            $j->id,
                            optional($j->target_date)->format('Y-m-d'),
                            optional($j->fromLanguage)->name,
                            optional($j->toLanguage)->name,
                            $j->client_reference ?? 'N/A'
                        ),
                        'date' => optional($j->target_date)->format('Y-m-d'),
                        'ref' => $j->client_reference,
                    ];
                }
            }
        } catch (\Throwable $e) { /* ignore job fetch failures */
        }

        // Compute default invoice email
        $defaultInvoiceEmail = null;
        try {
            $client = $invoice->client;
            $accountEmail = optional(optional($client)->user)->email;
            $orgEmail = optional(optional($client)->organisation)->organisation_email;
            if ($client) {
                if ((int) ($client->invoice_email_same_as_account ?? 0) === 1) {
                    $defaultInvoiceEmail = $accountEmail;
                } else {
                    $defaultInvoiceEmail = $orgEmail ?: $accountEmail;
                }
            } else {
                $defaultInvoiceEmail = $accountEmail;
            }
        } catch (\Throwable $e) {
            $defaultInvoiceEmail = null;
        }

        return view('invoices.admin.client.preview', compact(
            'invoice',
            'jobOptions',
            'existingJobIds',
            'effectiveTemplate',
            'distinctJobs',
            'defaultInvoiceEmail'
        ));
    })->name('dev.preview.admin-client-invoice');

    // Developer preview for Admin Job Payments dashboard (no auth)
    Route::get('/dev/preview/admin/job-payments', 'AdminJobPaymentsController@indexDev')->name('dev.preview.admin.job-payments');
    Route::get('/dev/preview/admin/job-payments/data', 'AdminJobPaymentsController@dataDev')->name('dev.preview.admin.job-payments.data');
    Route::get('/dev/preview/admin/job-payments/clients', 'AdminJobPaymentsController@clientsLookupDev')->name('dev.preview.admin.job-payments.clients');
    Route::get('/dev/preview/admin/job-payments/companies', 'AdminJobPaymentsController@companiesLookupDev')->name('dev.preview.admin.job-payments.companies');
    Route::match(['get', 'post'], '/dev/preview/admin/job-payments/export', 'AdminJobPaymentsController@exportDev')->name('dev.preview.admin.job-payments.export');
    // Dev: Create client invoice from selected jobs (no auth gating)
    Route::post('/dev/preview/admin/job-payments/client/invoice', 'AdminJobPaymentsController@createClientInvoiceDev')->name('dev.preview.admin.job-payments.client.invoice');
    // Developer preview: export progress & preview endpoints (no auth)
    Route::get('/dev/preview/admin/job-payments/preview', 'AdminJobPaymentsController@previewDev')->name('dev.preview.admin.job-payments.preview');
    Route::post('/dev/preview/admin/job-payments/export/start', 'AdminJobPaymentsController@startExportDev')->name('dev.preview.admin.job-payments.export.start');
    Route::get('/dev/preview/admin/job-payments/export/status/{export}', 'AdminJobPaymentsController@statusExportDev')->name('dev.preview.admin.job-payments.export.status');
    Route::get('/dev/preview/admin/job-payments/export/download/{export}', 'AdminJobPaymentsController@downloadExportDev')->name('dev.preview.admin.job-payments.export.download');

    // Developer preview: job-timesheet-sign emails (no auth)
    Route::get('/dev/preview/email/job-timesheet-sign/{role}/{id?}', function ($role, $id = null) {
        $role = strtolower($role);
        $job = $id ? \App\InterpreterJob::with(['client.user', 'toLanguage', 'agent.user', 'timesheet'])->find($id)
            : \App\InterpreterJob::with(['client.user', 'toLanguage', 'agent.user', 'timesheet'])->latest('id')->first();
        if (!$job) {
            return response('No interpreter jobs found to preview.', 404);
        }
        $map = [
            'agent' => 'emails.agents.job-timesheet-sign',
            'client' => 'emails.clients.job-timesheet-sign',
            'admin' => 'emails.admin.job-timesheet-sign',
        ];
        $view = $map[$role] ?? $map['client'];
        return view($view, ['job' => $job]);
    })->name('dev.preview.email.job-timesheet-sign');

    // Developer preview: Appointment PDF (no auth)
    Route::get('/dev/preview/interpreter-jobs/{id}/appointment-pdf', function ($id) {
        $job = \App\InterpreterJob::find($id) ?? \App\InterpreterJob::latest('id')->first();
        if (!$job) {
            return response('No interpreter jobs found to preview appointment PDF.', 404);
        }
        $controller = app(\App\Http\Controllers\InterpreterJobController::class);
        return $controller->appointmentPdf($job);
    })->name('dev.preview.interpreter-jobs.appointment-pdf');
}

// In local environment, relax 'verified' middleware to unblock UI testing
// This avoids email verification redirects when previewing pages locally.
// In non-local environments, we keep the default ['auth', 'verified'] protection.
Route::middleware(app()->environment('local') ? ['auth'] : ['auth', 'verified'])->group(function () {
    // Route::get('/test-job-reminders', function () {
    //     Artisan::call('send-interpreterjob-reminders');
    //     return 'Job reminders sent!';
    // });

    Route::get('/test-job-reminders', 'TestJobReminderController@run');
    Route::get('/test-job-reminders/diagnose', 'TestJobReminderController@diagnose');
    Route::get('/test-job-reminders/log-status', 'TestJobReminderController@logStatus');
    Route::get('/test-job-reminders/channel-status', 'TestJobReminderController@channelStatus');
    Route::get('/test-job-reminders/fire/{jobId}/{hoursToGo?}', 'TestJobReminderController@fire');

    Route::middleware('redirect_new_agent_to_profile')->group(function () {
        Route::get('/', 'InterpreterJobController@index')->name('index');

        Route::get('/interpreter-jobs', 'InterpreterJobController@index')->name('interpreter-jobs.index');
        Route::get('/interpreter-jobs/{interpreterJob}/quotes', 'InterpreterJobQuoteController@index')->name('interpreter-jobs.quotes.index')->middleware('can:viewQuotes,interpreterJob');
        Route::get('/interpreter-jobs/{interpreterJob}/allupdates', 'InterpreterJobController@allupdates')->name('interpreter-jobs.allupdates.index')->middleware('role:admin');
        Route::get('/interpreter-jobs/{interpreterJob}/allupdates', 'InterpreterJobController@allupdates')->name('interpreter-jobs.allupdates.index')->middleware('role:admin');
        //dna and retrn
        Route::put('/interpreter-job/{interpreterJob}/dna', 'InterpreterJobController@dna')->name('interpreter-jobs.dna')->middleware('role:admin');
        Route::put('/interpreter-jobs/{interpreterJob}/retrn', 'InterpreterJobController@retrn')->name('interpreter-jobs.retrn')->middleware('role:admin');

        Route::get('interpreter-jobs/{interpreterJob}/documents', 'InterpreterJobDocumentController@index')->name('interpreter-jobs.documents.index');
        Route::post('interpreter-jobs/{interpreterJob}/documents', 'InterpreterJobDocumentController@store')->name('interpreter-jobs.documents.store');
        // Timesheets: dedicated tab to prevent admin-side confusion with Travel Expenses
        Route::get('interpreter-jobs/{interpreterJob}/timesheets', 'InterpreterJobDocumentController@timesheets')
            ->name('interpreter-jobs.timesheets.index');
        Route::delete('interpreter-jobs/{interpreterJob}/timesheets/signed-upload', 'InterpreterJobDocumentController@cancelSignedTimesheetUpload')
            ->name('interpreter-jobs.timesheets.cancel-upload');
        // Admin verification of paper timesheet
        Route::post('interpreter-jobs/{interpreterJob}/timesheets/verify', 'InterpreterJobDocumentController@verifyTimesheet')
            ->middleware('role:admin')
            ->name('interpreter-jobs.timesheets.verify');
        // Print Timesheet PDF directly from Interpreter Job (policy-gated)
        Route::get('/interpreter-jobs/{interpreterJob}/timesheet/pdf', 'TimeSheetController@generateTimesheetByJob')
            ->middleware('can:view,interpreterJob')
            ->name('interpreter-jobs.timesheet.pdf');
        Route::post('interpreter-jobs/travelDetails', 'InterpreterJobDocumentController@travel_details')->name('travelDetails.travel_details');

        Route::get('/translator-jobs/{translatorJob}/allupdates', 'TranslatorJobController@allupdates')->name('translator-jobs.allupdates.index')->middleware('role:admin');
        //   Route::get('/timesheet','InterpreterJobController@timesheet')->name('interpreter-jobs.timesheet.index');

        Route::get('/translator-jobs', 'TranslatorJobController@index')->name('translator-jobs.index');
        Route::get('/translator-jobs/{translatorJob}/quotes', 'TranslatorJobQuoteController@index')->name('translator-jobs.quotes.index')->middleware('can:viewQuotes,translatorJob');
    });

    Route::middleware('role:admin')->group(function () {

    });
    Route::middleware('role:admin|client')->group(function () {

        Route::group(['prefix' => '/interpreter-jobs', 'as' => 'interpreter-jobs.'], function () {

            Route::get('/export', 'InterpreterJobController@export')->name('export')->middleware('role:admin');
            Route::get('/create', 'InterpreterJobController@create')->name('create');
            Route::get('/createbulk', 'InterpreterJobController@create_bulk')->name('createbulk');

            Route::post('/', 'InterpreterJobController@store')->name('store');
            Route::post('/createbulk', 'InterpreterJobController@store_bulk')->name('storebulk');
            Route::get('/{interpreterJob}/edit', 'InterpreterJobController@edit')->name('edit')->middleware('can:edit,interpreterJob');
            Route::put('/{interpreterJob}', 'InterpreterJobController@update')->name('update')->middleware('can:update,interpreterJob');
            Route::get('/{interpreterJob}/agent', 'InterpreterJobAgentController@index')->name('agent.index')->middleware('can:edit,interpreterJob');
        });

        Route::group(['prefix' => '/translator-jobs', 'as' => 'translator-jobs.'], function () {
            Route::get('/export', 'TranslatorJobController@export')->name('export')->middleware('role:admin');
            Route::get('/create', 'TranslatorJobController@create')->name('create');
            Route::post('/', 'TranslatorJobController@store')->name('store');
            Route::get('/{translatorJob}/edit', 'TranslatorJobController@edit')->name('edit')->middleware('can:edit,translatorJob');
            Route::put('/{translatorJob}', 'TranslatorJobController@update')->name('update')->middleware('can:update,translatorJob');
        });

        Route::post('/interpreter-jobs/{interpreterJob}/cancel', 'InterpreterJobCancellationController@store')->name('interpreter-jobs.cancel')->middleware('can:edit,interpreterJob');
        Route::post('/translator-jobs/{translatorJob}/cancel', 'TranslatorJobCancellationController@store')->name('translator-jobs.cancel')->middleware('can:edit,translatorJob');

        Route::get('/report', 'ReportController@index')->name('report.index');
        Route::get('/report-export', 'ReportController@export')->name('report-export')->middleware('role:admin');

    });

    Route::middleware('role:admin|client|agent')->group(function () {
        Route::put('/interpreter-jobs/{interpreterJob}/complete', 'CompletedInterpreterJobController@update')->name('interpreter-jobs.complete')->middleware('can:complete,interpreterJob');
    });

    Route::middleware('role:admin|client')->group(function () {

        Route::group(['prefix' => '/client/profile', 'as' => 'clients.profile.'], function () {
            Route::get('/edit', 'ClientProfileController@edit')->name('edit');
            Route::put('/', 'ClientProfileController@update')->name('update');
        });
        Route::get('/addresses', 'InterpreterJobController@getaddress')->name('getaddress');
        Route::put('/quotes/{adminQuote}', 'AdminQuoteController@update')->name('admin-quotes.update');
        Route::delete('/quotes/{adminQuote}', 'AdminQuoteController@destroy')->name('admin-quotes.destroy');

        Route::put('/clients/{client}/update-seen-terms', 'ClientSeenTermsController@update')->name('client.seen-terms');


        Route::put('/translator-jobs/{translatorJob}/complete', 'CompletedTranslatorJobController@update')->name('translator-jobs.complete')->middleware('can:complete,translatorJob');
    });

    Route::middleware('role:new-agent')->group(function () {
        Route::get('/agent/profile/create', 'AgentProfileController@create')->name('agents.profile.create');
        Route::post('/agent/profile', 'AgentProfileController@store')->name('agents.profile.store');
    });

    Route::middleware('role:agent|translator|qualified-translator|admin|super-admin|client')->group(function () {
        Route::group(['prefix' => '/interpreter-jobs/{interpreterJob}', 'as' => 'interpreter-jobs.'], function () {
            Route::get('/', 'InterpreterJobController@show')->name('show')->middleware('can:view,interpreterJob');
            Route::get('/appointment-pdf', 'InterpreterJobController@appointmentPdf')->name('appointment-pdf')->middleware('can:view,interpreterJob');
        });
    });

    Route::middleware('role:agent|translator|qualified-translator|admin|super-admin')->group(function () {
        Route::group(['prefix' => '/interpreter-jobs/{interpreterJob}', 'as' => 'interpreter-jobs.'], function () {
            Route::post('/matched/reject', 'InterpreterJobMatchedAgentController@reject')->name('matched.reject')->middleware('can:view,interpreterJob');
            Route::delete('/matched/{matchedAgent}/unassign', 'InterpreterJobMatchedAgentController@unAssignForAgents')
                ->name('matched.unassign')->middleware('can:unassign,interpreterJob');
            // Allow both admin assignment and agent acceptance via optional matchedAgent parameter
            Route::put('/matched/{matchedAgent?}', 'InterpreterJobMatchedAgentController@update')->name('matched.update')->middleware('can:view,interpreterJob');
        });

        Route::group(['prefix' => '/translator-jobs/{translatorJob}', 'as' => 'translator-jobs.'], function () {
            Route::get('/', 'TranslatorJobController@show')->name('show')->middleware('can:view,translatorJob');
            Route::post('/matched/reject', 'TranslatorJobMatchedAgentController@reject')->name('matched.reject')->middleware('can:view,translatorJob');
            // Allow both admin assignment and agent acceptance via optional matchedAgent parameter
            Route::put('/matched/{matchedAgent?}', 'TranslatorJobMatchedAgentController@update')->name('matched.update')->middleware('can:view,translatorJob');
        });
    });

    Route::middleware('role:new-agent|agent')->group(function () {
        Route::group(['prefix' => '/agent/profile', 'as' => 'agents.profile.'], function () {
            Route::get('/edit', 'AgentProfileController@edit')->name('edit');
            Route::put('/', 'AgentProfileController@update')->name('update');
            Route::get('/documents', 'AgentDocumentController@edit')->name('documents.edit');
        });
    });

    Route::middleware('role:admin|super-admin')->group(function () {
        Route::group(['prefix' => '/companies', 'as' => 'companies.archived.'], function () {
            Route::get('/archived', 'ArchivedCompanyController@index')->name('index');
            Route::put('/archived/{company}', 'ArchivedCompanyController@update')->name('update');
            Route::delete('/archived/{company}', 'ArchivedCompanyController@destroy')->name('destroy');
        });

        Route::resource('/companies', 'CompanyController')->except('show');

        Route::group(['prefix' => '/interpreter-jobs/{interpreterJob}', 'as' => 'interpreter-jobs.'], function () {
            Route::get('/matched', 'InterpreterJobMatchedAgentController@index')->name('matched.index');
            Route::post('/matched', 'InterpreterJobMatchedAgentController@store')->name('matched.store')->middleware('can:assign,interpreterJob');
            Route::delete('/matched/{matchedAgent}', 'InterpreterJobMatchedAgentController@destroy')->name('matched.destroy')->middleware('can:unassign,interpreterJob');
        });

        Route::group(['prefix' => '/translator-jobs/{translatorJob}', 'as' => 'translator-jobs.'], function () {
            Route::get('/matched', 'TranslatorJobMatchedAgentController@index')->name('matched.index');
            // Allow admins to notify/unnotify agents regardless of quote acceptance; assignment remains gated elsewhere
            Route::post('/matched', 'TranslatorJobMatchedAgentController@store')->name('matched.store');
            Route::delete('/matched/{matchedAgent}', 'TranslatorJobMatchedAgentController@destroy')->name('matched.destroy')->middleware('can:unassign,translatorJob');
        });

        Route::resource('/clients', 'ClientController')->except(['show']);
        // routes/web.php


        Route::get('/clients/{client}/toggle-show-agents', 'ClientController@toggleShowAgents')->name('clients.toggleShowAgents');


        Route::group(['prefix' => '/clients', 'as' => 'clients.'], function () {

            Route::group(['prefix' => '/new-requests', 'as' => 'new-requests.'], function () {
                Route::get('/', 'NewRequestController@index')->name('index');
                Route::put('/{client}', 'NewRequestController@update')->name('update');
                Route::delete('/{client}', 'NewRequestController@destroy')->name('destroy');

                Route::get('/{client}/client', 'NewRequestClientController@edit')->name('client.edit');
                Route::put('/{client}/client', 'NewRequestClientController@update')->name('client.update');

                Route::get('/{client}/job', 'NewRequestJobController@edit')->name('job.edit');
                Route::put('/{client}/job', 'NewRequestJobController@update')->name('job.update');
            });

            Route::group(['prefix' => '/archived', 'as' => 'archived.'], function () {
                Route::get('/', 'ArchivedClientController@index')->name('index');
                Route::get('/{client}', 'ArchivedClientController@show')->name('show');
                Route::put('/{client}', 'ArchivedClientController@update')->name('update');
                Route::delete('/{client}', 'ArchivedClientController@destroy')->name('destroy');
            });

            Route::group(['prefix' => '/rejected', 'as' => 'rejected.'], function () {
                Route::get('/', 'RejectedClientController@index')->name('index');
                Route::put('/{client}', 'RejectedClientController@update')->name('update');
            });
        });

        Route::group(['prefix' => '/agents', 'as' => 'agents.'], function () {

            Route::group(['prefix' => '/archived', 'as' => 'archived.'], function () {
                Route::get('/', 'ArchivedAgentController@index')->name('index');
                Route::put('/{agent}', 'ArchivedAgentController@update')->name('update');
                Route::get('/{agent}', 'ArchivedAgentController@show')->name('show');
                Route::delete('/{agent}', 'ArchivedAgentController@destroy')->name('destroy');
            });

            Route::get('/new', 'NewAgentController@index')->name('new.index');
            Route::put('/new/{agent}', 'NewAgentController@update')->name('new.update');
        });

        Route::group(['prefix' => '/agents/{agent}', 'as' => 'agents.documents.'], function () {
            Route::get('/documents/edit', 'AgentDocumentController@edit')->name('edit');
        });

        Route::resource('/agents', 'AgentController')->except('show');

        Route::post('/users/{user}/send-password-reset-link', 'ResetUserPassword')->name('users.send-password-reset-link');

        Route::get('/admin/tools/run-dbs-update', function (\Illuminate\Http\Request $request) {
            $dry = $request->boolean('dry_run');
            \Illuminate\Support\Facades\Artisan::call('agents:request-dbs-update', $dry ? ['--dry-run' => true] : []);
            return redirect()->route('admin.dashboard')->with('success', $dry ? 'DBS update dry-run executed' : 'DBS update emails triggered');
        })->name('admin.tools.run-dbs-update');
    });

    Route::middleware('role:new-agent|agent|admin')->group(function () {
        Route::put('/agents/{agent}/documents', 'AgentDocumentController@update')->name('agents.documents.update');
    });

    Route::middleware('role:agent|translator|qualified-translator|admin|super-admin')->group(function () {

        Route::group(['prefix' => '/interpreter-jobs/{interpreterJob}', 'as' => 'interpreter-jobs.'], function () {
            Route::put('/matched/{matchedAgent?}', 'InterpreterJobMatchedAgentController@update')->name('matched.update')->middleware('can:accept,interpreterJob');
            Route::post('/quotes', 'InterpreterJobQuoteController@store')->name('quotes.store')->middleware('can:viewQuotes,interpreterJob');
            Route::put('/quotes/{quote}', 'InterpreterJobQuoteController@update')->name('quotes.update')->middleware('can:viewQuotes,interpreterJob');
        });

        Route::group(['prefix' => '/translator-jobs/{translatorJob}', 'as' => 'translator-jobs.'], function () {
            Route::put('/matched/{matchedAgent?}', 'TranslatorJobMatchedAgentController@update')->name('matched.update')->middleware('can:accept,translatorJob');
            Route::post('/quotes', 'TranslatorJobQuoteController@store')->name('quotes.store')->middleware('can:viewQuotes,translatorJob');
            Route::put('/quotes/{quote}', 'TranslatorJobQuoteController@update')->name('quotes.update')->middleware('can:viewQuotes,translatorJob');
        });
    });

    Route::middleware('role:super-admin')->group(function () {
        Route::resource('/admins', 'AdminController')->except('show');

    });
    Route::middleware('role:super-admin|admin')->group(function () {
        Route::resource('/admins', 'AdminController')->except('show');
        Route::resource('/languages', 'LanguageController');

    });

    Route::resource('/feedback', 'FeedbackController');


    Route::delete('/documents/{document}/delete', 'DocumentController@destroy')->name('documents.destroy')->middleware('can:delete,document');

    Route::post('matched/{matchedAgent}/cancel', 'MatchedAgentCancellationController@store')->name('matched.cancel');

    Route::get('translator-jobs/{translatorJob}/documents', 'TranslatorJobDocumentController@index')->name('translator-jobs.documents.index')->middleware('can:viewDocuments,translatorJob');
    Route::post('translator-jobs/{translatorJob}/documents', 'TranslatorJobDocumentController@store')->name('translator-jobs.documents.store')->middleware('can:viewDocuments,translatorJob');

    Route::post('translator-jobs/{translatorJob}/comments', 'TranslatorJobCommentController@store')->name('translator-jobs.comments.store');

    Route::get('/{user}/impersonate', 'AdminController@impersonate')->name('impersonate');
    Route::get('/stop', 'AdminController@stopImpersonate')->name('unimpersonate');

    Route::resource('/timesheet', 'TimeSheetController');
    Route::get('/timesheet_status/{id}/{status}', 'TimeSheetController@updateStatus')->name('timesheet.status');
    Route::get('/feedback_status/{id}', 'FeedbackController@updateStatus')->name('feedback.status');

    Route::get('/timesheet-pdf/{id}', 'TimeSheetController@generateTimesheet')->name('timesheet-pdf');
    Route::get('/timesheets/{timesheet}/pdf', 'TimeSheetController@generateTimesheet')->name('timesheets.pdf');

    // Deliverable 1A, 5A, 5C & 6B: Authenticated Smart Helpers across Roles
    Route::post('/timesheet/{jobId}/generate-magic-link', 'MagicTimesheetSignController@generateLink')->name('timesheet.magic-link');
    Route::post('/api/smart-booking-parse', 'ExecutiveUpgradeController@parseBookingText')->name('smart-booking.parse');
    Route::post('/interpreter-jobs/{interpreterJob}/follow-up', 'ExecutiveUpgradeController@bookFollowUp')->name('interpreter-jobs.follow-up');
    Route::post('/interpreter-jobs/{interpreterJob}/urgent-alert', 'ExecutiveUpgradeController@sendUrgentAlert')->name('interpreter-jobs.urgent-alert');

    Route::middleware('role:admin|super-admin')->group(function () {
        // Executive Upgrade Suite (All 6 Executive Proposal Deliverables + White-Label Studio)
        Route::get('/admin/traffic-light', 'ExecutiveUpgradeController@trafficLight')->name('admin.traffic-light.index');
        Route::post('/admin/traffic-light/{job}/approve', 'ExecutiveUpgradeController@approveTrafficLightJob')->name('admin.traffic-light.approve');
        Route::post('/admin/traffic-light/approve-all-green', 'ExecutiveUpgradeController@approveAllGreen')->name('admin.traffic-light.approve-all-green');
        Route::post('/admin/smart-reader/timesheet/{job}', 'ExecutiveUpgradeController@smartPaperReader')->name('admin.smart-reader.timesheet');

        Route::get('/admin/price-book', 'ExecutiveUpgradeController@priceBook')->name('admin.price-book.index');
        Route::post('/admin/price-book', 'ExecutiveUpgradeController@storeRateCard')->name('admin.price-book.store');
        Route::put('/admin/price-book/{rateCard}', 'ExecutiveUpgradeController@updateRateCard')->name('admin.price-book.update');

        Route::get('/admin/credit-control', 'ExecutiveUpgradeController@creditControl')->name('admin.credit-control.index');
        Route::post('/admin/credit-control/send-reminders', 'ExecutiveUpgradeController@sendPoliteReminders')->name('admin.credit-control.send-reminders');
        Route::post('/admin/credit-control/toggle-mode', 'ExecutiveUpgradeController@toggleChaserMode')->name('admin.credit-control.toggle-mode');
        Route::post('/admin/credit-control/{invoice}/toggle-pause', 'ExecutiveUpgradeController@toggleInvoicePause')->name('admin.credit-control.toggle-pause');

        Route::get('/admin/reconciliation', 'ExecutiveUpgradeController@reconciliation')->name('admin.reconciliation.index');
        Route::post('/admin/reconciliation/match-remittance', 'ExecutiveUpgradeController@matchRemittance')->name('admin.reconciliation.match-remittance');
        Route::post('/admin/reconciliation/apply-remittance', 'ExecutiveUpgradeController@applyRemittanceMatches')->name('admin.reconciliation.apply-remittance');
        Route::post('/admin/reconciliation/bulk-mark-paid', 'ExecutiveUpgradeController@bulkMarkPaid')->name('admin.reconciliation.bulk-mark-paid');
        Route::get('/admin/reconciliation/bank-payout-csv', 'ExecutiveUpgradeController@exportBankPayoutCsv')->name('admin.reconciliation.bank-payout-csv');

        Route::get('/admin/compliance', 'ExecutiveUpgradeController@compliance')->name('admin.compliance.index');
        Route::post('/admin/compliance/scan-dbs/{agent}', 'ExecutiveUpgradeController@scanDbsDocument')->name('admin.compliance.scan-dbs');
        Route::post('/admin/compliance/remind-dbs/{agent}', 'ExecutiveUpgradeController@sendDbsReminder')->name('admin.compliance.remind-dbs');

        Route::get('/admin/white-label', 'ExecutiveUpgradeController@whiteLabel')->name('admin.white-label.index');
        Route::post('/admin/white-label/preset/{slug}', 'ExecutiveUpgradeController@activatePreset')->name('admin.white-label.preset');
        Route::post('/admin/white-label/update', 'ExecutiveUpgradeController@updateWhiteLabel')->name('admin.white-label.update');
    });

    Route::middleware('role:admin')->group(function () {
        // Admin Dashboard
        Route::get('/admin/dashboard', 'AdminController@dashboard')->name('admin.dashboard');
        // JSON endpoint for Jobs Needing Assignment (periodic refresh)
        Route::get('/admin/dashboard/jobs-needing-assignment/data', 'AdminController@jobsNeedingAssignmentData')->name('admin.dashboard.jobs-needing-assignment.data');
        // Admin Activity Log
        Route::get('/admin/activity', 'AdminController@activity')->name('admin.activity.index');

        // Admin Chat / Messaging
        Route::get('/admin/chat', 'AdminChatController@index')->name('admin.chat.index');
        Route::post('/admin/chat', 'AdminChatController@store')->name('admin.chat.store');
        Route::get('/admin/chat/{id}', 'AdminChatController@show')->name('admin.chat.show');
        Route::post('/admin/chat/{id}/send', 'AdminChatController@sendMessage')->name('admin.chat.sendMessage');
        Route::get('/admin/chat/{id}/poll', 'AdminChatController@poll')->name('admin.chat.poll');
        Route::get('/admin/chat-unread-count', 'AdminChatController@unreadCount')->name('admin.chat.unreadCount');

        // Admin Job Payments Dashboard (Client/Agent perspectives)
        Route::get('/admin/job-payments', 'AdminJobPaymentsController@index')->name('admin.job-payments.index');
        Route::get('/admin/job-payments/data', 'AdminJobPaymentsController@data')->name('admin.job-payments.data');
        Route::get('/admin/job-payments/clients', 'AdminJobPaymentsController@clientsLookup')->name('admin.job-payments.clients');
        Route::get('/admin/job-payments/companies', 'AdminJobPaymentsController@companiesLookup')->name('admin.job-payments.companies');
        Route::post('/admin/job-payments/export', 'AdminJobPaymentsController@export')->name('admin.job-payments.export');
        // Admin: Create client invoice from selected jobs
        Route::post('/admin/job-payments/client/invoice', 'AdminJobPaymentsController@createClientInvoice')->name('admin.job-payments.client.invoice');
        // Admin Job Payments: export progress & preview endpoints
        Route::get('/admin/job-payments/preview', 'AdminJobPaymentsController@preview')->name('admin.job-payments.preview');
        Route::post('/admin/job-payments/export/start', 'AdminJobPaymentsController@startExport')->name('admin.job-payments.export.start');
        Route::get('/admin/job-payments/export/status/{export}', 'AdminJobPaymentsController@statusExport')->name('admin.job-payments.export.status');
        Route::get('/admin/job-payments/export/download/{export}', 'AdminJobPaymentsController@downloadExport')->name('admin.job-payments.export.download');

        // Timesheet -> Client Invoice actions
        Route::post('/timesheet/{id}/send-invoice', 'TimeSheetController@sendInvoice')->name('timesheet.send-invoice')->where('id', '[0-9]+');
        Route::post('/timesheet/send-invoice', 'TimeSheetController@sendInvoiceBatch')->name('timesheet.send-invoice.batch');
        // Collective invoice builder (row-based detail entry)
        Route::get('/timesheet/collective/create', 'TimeSheetController@collectiveCreate')->name('timesheet.collective.create');
        // JSON endpoint used by the builder for AJAX filtering and capping meta
        Route::get('/timesheet/collective/data', 'TimeSheetController@collectiveData')->name('timesheet.collective.data');
        Route::post('/timesheet/collective/store', 'TimeSheetController@collectiveStore')->name('timesheet.collective.store');

        // Admin Payslip Management
        Route::get('/admin/payslips', 'AdminController@payslips')->name('admin.payslips.index');
        Route::get('/admin/payslips/{payslip}', 'AdminController@showPayslip')->name('admin.payslips.show');
        Route::patch('/admin/payslips/{payslip}/status', 'AdminController@updatePayslipStatus')->name('admin.payslips.update-status');
        Route::patch('/admin/payslips/{payslip}/recall', 'AdminController@recallPayslip')->name('admin.payslips.recall');

        // Admin Remittance Management
        Route::get('/admin/remittances', 'AdminController@remittances')->name('admin.remittances.index');
        Route::patch('/admin/remittances/{remittance}/status', 'AdminController@updateRemittanceStatus')->name('admin.remittances.update-status');
        // Admin Receipts Listing
        Route::get('/admin/receipts', 'AdminController@receipts')->name('admin.receipts.index');

        // Invoices (Admin)
        Route::get('/invoices/admin', 'AdminInvoiceController@index')->name('invoices.admin.index');
        // Friendly redirect to avoid confusion between "/admin/invoices" and "/invoices/admin"
        Route::redirect('/admin/invoices', '/invoices/admin');
        Route::get('/invoices/admin/agent/{id}/pdf', 'AdminInvoiceController@pdfAgent')->name('invoices.admin.agent.pdf');
        Route::get('/invoices/admin/client/{id}/pdf', 'AdminInvoiceController@pdfClient')->name('invoices.admin.client.pdf');
        // Dev convenience: allow /debug/invoices/... to resolve by redirecting to the canonical route
        Route::get('/debug/invoices/admin/client/{id}/pdf', function ($id) {
            return redirect()->route('invoices.admin.client.pdf', ['id' => $id]);
        });
        Route::get('/invoices/admin/agent/{id}/preview', 'AdminInvoiceController@previewAgent')->name('invoices.admin.agent.preview');
        Route::get('/invoices/admin/client/{id}/preview', 'AdminInvoiceController@previewClient')->name('invoices.admin.client.preview');
        Route::get('/invoices/admin/export', 'AdminInvoiceController@export')->name('invoices.admin.export');
        Route::post('/invoices/admin/bulk-action', 'AdminInvoiceController@bulkAction')->name('invoices.admin.bulk-action');

        // Admin Client Invoice Builder & lifecycle (Phase 2)
        Route::get('/invoices/admin/client/new', 'AdminInvoiceController@createClient')->name('invoices.admin.client.create');
        Route::post('/invoices/admin/client', 'AdminInvoiceController@storeClient')->name('invoices.admin.client.store');
        Route::patch('/invoices/admin/client/{id}', 'AdminInvoiceController@updateClient')->name('invoices.admin.client.update')->where('id', '[0-9]+');
        Route::post('/invoices/admin/client/{id}/send', 'AdminInvoiceController@sendClient')->name('invoices.admin.client.send')->where('id', '[0-9]+');

        // Admin Invoice Actions
        Route::get('/invoices/admin/agent/{id}', 'AdminInvoiceController@showAgent')->name('invoices.admin.agent.show')->where('id', '[0-9]+');
        Route::get('/invoices/admin/client/{id}', 'AdminInvoiceController@showClient')->name('invoices.admin.client.show')->where('id', '[0-9]+');
        Route::get('/invoices/agent/{id}', 'AdminInvoiceController@showAgent')->name('invoices.admin.agent.show.legacy')->where('id', '[0-9]+');
        Route::get('/invoices/client/{id}', 'AdminInvoiceController@showClient')->name('invoices.admin.client.show.legacy')->where('id', '[0-9]+');
        Route::post('/invoices/agent/{id}/approve', 'AdminInvoiceController@approveAgent')->name('invoices.admin.agent.approve')->where('id', '[0-9]+');
        Route::post('/invoices/agent/{id}/mark-paid', 'AdminInvoiceController@markPaidAgent')->name('invoices.admin.agent.mark-paid')->where('id', '[0-9]+');
        Route::post('/invoices/client/{id}/approve', 'AdminInvoiceController@approveClient')->name('invoices.admin.client.approve')->where('id', '[0-9]+');
        Route::post('/invoices/client/{id}/mark-paid', 'AdminInvoiceController@markPaidClient')->name('invoices.admin.client.mark-paid')->where('id', '[0-9]+');
        Route::get('/invoices/agent/{id}/edit', 'AdminInvoiceController@editAgent')->name('invoices.admin.agent.edit')->where('id', '[0-9]+');
        Route::get('/invoices/client/{id}/edit', 'AdminInvoiceController@editClient')->name('invoices.admin.client.edit')->where('id', '[0-9]+');
        Route::post('/invoices/agent/{id}/duplicate', 'AdminInvoiceController@duplicateAgent')->name('invoices.admin.agent.duplicate')->where('id', '[0-9]+');
        Route::post('/invoices/client/{id}/duplicate', 'AdminInvoiceController@duplicateClient')->name('invoices.admin.client.duplicate')->where('id', '[0-9]+');
        Route::delete('/invoices/agent/{id}', 'AdminInvoiceController@deleteAgent')->name('invoices.admin.agent.delete')->where('id', '[0-9]+');
        Route::delete('/invoices/client/{id}', 'AdminInvoiceController@deleteClient')->name('invoices.admin.client.delete')->where('id', '[0-9]+');
        Route::post('/invoices/agent/{id}/generate-payslip', 'AdminInvoiceController@generatePayslip')->name('invoices.admin.agent.generate-payslip')->where('id', '[0-9]+');
        Route::get('/invoices/agent/{id}/payslip-data', 'AdminInvoiceController@getPayslipData')->name('invoices.admin.agent.payslip-data')->where('id', '[0-9]+');

        // Job search routes
        Route::get('/jobs/search/{identifier}', 'JobController@search')->name('jobs.search');

        // Payment Proofs verification (Admin)
        Route::patch('/payment-proofs/{paymentProof}/verify', 'PaymentProofController@verify')->name('payment-proofs.verify');
        Route::patch('/payment-proofs/{paymentProof}/reject', 'PaymentProofController@reject')->name('payment-proofs.reject');

        // Receipts (Admin) - view and download existing receipt PDFs
        Route::get('/receipts/{receipt}/view', 'ReceiptController@view')->name('receipts.view');
        Route::get('/receipts/{receipt}/download', 'ReceiptController@download')->name('receipts.download');
        // Remittances (Admin)
        // Remittances Routes
        Route::resource('remittances', 'RemittanceController');
        Route::patch('remittances/{remittance}/approve', 'RemittanceController@approve')->name('remittances.approve');
        Route::patch('remittances/{remittance}/reject', 'RemittanceController@reject')->name('remittances.reject');
        Route::get('remittances/{remittance}/download', 'RemittanceController@download')->name('remittances.download');

        // Export routes
        Route::get('/exports', 'ExportController@index')->name('exports.index');
        Route::post('/exports/invoices', 'ExportController@exportInvoices')->name('exports.invoices');
        Route::post('/exports/payslips', 'ExportController@exportPayslips')->name('exports.payslips');
        Route::post('/exports/remittances', 'ExportController@exportRemittances')->name('exports.remittances');
        Route::post('/exports/bulk', 'ExportController@bulkExport')->name('exports.bulk');
        Route::get('/exports/{export}/download', 'ExportController@download')->name('exports.download');
    });

    Route::middleware('role:agent')->group(function () {
        // Agent Invoices
        Route::get('/invoices/agent', 'AgentInvoiceController@index')->name('invoices.agent.index');
        // Agent invoice creation from pending job entries - MUST come before parameterized routes
        Route::get('/invoices/agent/new', 'AgentInvoiceController@create')->name('invoices.agent.create');
        Route::post('/invoices/agent/generate', 'AgentInvoiceController@generate')->name('invoices.agent.generate');
        // Parameterized routes - MUST come after specific routes
        Route::get('/invoices/agent/{id}', 'AgentInvoiceController@show')->name('invoices.agent.show')->where('id', '[0-9]+');
        Route::delete('/invoices/agent/{id}', 'AgentInvoiceController@destroy')->name('invoices.agent.delete')->where('id', '[0-9]+');
        Route::post('/invoices/agent/items/{itemId}/vat', 'AgentInvoiceController@updateItemVat')->name('invoices.agent.items.vat')->where('itemId', '[0-9]+');
        Route::get('/invoices/agent/{id}/pdf', 'AgentInvoiceController@pdf')->name('invoices.agent.pdf')->where('id', '[0-9]+');
    });

    // Payslips - accessible by both agents and admins
    Route::middleware('role:agent|admin|super-admin')->group(function () {
        Route::get('/payslips', 'PayslipController@index')->name('payslips.index');
        Route::get('/payslips/create', 'PayslipController@create')->name('payslips.create');
        Route::post('/payslips', 'PayslipController@store')->name('payslips.store');
        Route::get('/payslips/{payslip}', 'PayslipController@show')->name('payslips.show');
        Route::get('/payslips/{payslip}/edit', 'PayslipController@edit')->name('payslips.edit');
        Route::put('/payslips/{payslip}', 'PayslipController@update')->name('payslips.update');
        Route::delete('/payslips/{payslip}', 'PayslipController@destroy')->name('payslips.destroy');
        Route::post('/payslips/{payslip}/approve', 'PayslipController@approve')->name('payslips.approve');
        Route::post('/payslips/{payslip}/mark-paid', 'PayslipController@markPaid')->name('payslips.mark-paid');
        Route::get('/payslips/{payslip}/pdf', 'PayslipController@pdf')->name('payslips.pdf');
    });

    Route::middleware('role:agent')->group(function () {

        // Agent Job Entry Routes
        Route::group(['prefix' => '/agent/job-entry', 'as' => 'agent.job-entry.'], function () {
            Route::get('/pending', 'AgentJobEntryController@pendingJobs')->name('pending');
            Route::get('/create/{job_type}/{job_id}', 'AgentJobEntryController@create')->name('create');
            // include job params in POST so controller receives them
            Route::post('/{job_type}/{job_id}', 'AgentJobEntryController@store')->name('store');
            Route::get('/{entry}/edit', 'AgentJobEntryController@edit')->name('edit');
            Route::put('/{entry}', 'AgentJobEntryController@update')->name('update');
            Route::delete('/{entry}', 'AgentJobEntryController@destroy')->name('destroy');
        });

        // Agent Dashboard
        Route::get('/agent/dashboard', 'AgentDashboardController@index')->name('agent.dashboard');
    });

    Route::middleware('role:client')->group(function () {
        // Client Invoices
        Route::get('/invoices/client', 'ClientInvoiceController@index')->name('invoices.client.index');
        Route::get('/invoices/client/{id}', 'ClientInvoiceController@show')->name('invoices.client.show');
        Route::get('/invoices/client/{id}/pdf', 'ClientInvoiceController@pdf')->name('invoices.client.pdf');
        // Client Payment Proofs
        // Primary RESTful endpoint with invoice id in the URL
        Route::post('/invoices/client/{id}/payment-proofs', 'PaymentProofController@store')->name('invoices.client.payment-proofs.store');
        // Fallback endpoint to make UX resilient in case the form action isn't set by JS
        // This posts to the index path and relies on a hidden invoice_id field
        Route::post('/invoices/client', 'PaymentProofController@store')->name('invoices.client.payment-proofs.store.fallback');
        Route::delete('/payment-proofs/{paymentProof}', 'PaymentProofController@destroy')->name('payment-proofs.destroy');
        // Client Receipts
        Route::get('/receipts/client', 'ClientReceiptController@index')->name('receipts.client.index');
        Route::get('/receipts/client/{receipt}/view', 'ClientReceiptController@show')->name('receipts.client.show');
        Route::get('/receipts/client/{receipt}/download', 'ClientReceiptController@download')->name('receipts.client.download');
        // Client Remittances
        Route::get('/remittances/client', 'ClientRemittanceController@index')->name('remittances.client.index');

        // Client Dashboard
        Route::get('/client/dashboard', 'ClientDashboardController@index')->name('client.dashboard');
    });
});

// Developer preview routes (local only)
if (app()->environment('local')) {
    Route::get('/dev/preview/interpreter-job-reminder/{hours?}', function ($hours = 24) {
        $hours = (int) $hours;
        $job = new \App\InterpreterJob([
            'id' => 999999,
            'status' => 1,
            'appointment_date' => now('Europe/London')->addDay(),
            'start_time' => now('Europe/London')->setTime(10, 0)->format('H:i'),
            'duration_hours' => 2,
            'duration_minutes' => 30,
            'department' => 'Community Health Centre',
            'address_line_1' => '123 King Street',
            'address_line_2' => 'South Wing',
            'county' => 'Greater London',
            'postcode' => 'SW1A 1AA',
        ]);
        $job->setRelation('toLanguage', new \App\Language(['name' => 'Polish', 'id' => 37]));
        $job->setRelation('skill', new \App\Skill(['skill' => 'Court Interpreting']));

        return view('emails.agents.interpreter-job-reminder', [
            'job' => $job,
            'hours_to_go' => $hours,
        ]);
    })->name('dev.preview.interpreter-job-reminder');
}

// Banking API under web + auth to support SPA session cookies and avoid throttle:api
Route::prefix('api')->namespace('Api')->middleware(['auth'])->group(function () {
    // Agent self-management
    Route::get('bank-account', 'BankAccountController@show')->name('bank-account.show');
    Route::post('bank-account', 'BankAccountController@store')->name('bank-account.store');
    Route::put('bank-account', 'BankAccountController@update')->name('bank-account.update');
    Route::delete('bank-account', 'BankAccountController@destroy')->name('bank-account.destroy');

    // Admin view of agent bank info
    Route::middleware('role:admin')->group(function () {
        Route::get('admin/agents/{agent}/bank-account', 'BankAccountController@showAgent')->name('admin.bank-account.show');
    });
});
Route::get('/documents/{document}', 'DocumentController@show')->name('documents.show');
