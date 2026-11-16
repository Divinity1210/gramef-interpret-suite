<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['namespace' => 'Api', 'as' => 'api.'], function () {
    Route::post('clients', 'NewRequestController@store')->name('new-clients.store');

    Route::apiResource('languages', 'LanguageController')->only('index')->names(['index' => 'languages.index']);
    Route::apiResource('skills', 'SkillController')->only('index')->names(['index' => 'skills.index']);

    Route::post('organisation', 'OrganisationController@show')->name('organisation.show');

    Route::post('company/{company}', 'CompanyController@show')->name('companies.show');
    Route::get('company/{company}', 'CompanyController@show')
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:300,1')
        ->name('companies.show.get');
    // Use GET for read-only client company lookup to avoid CSRF issues across environments
    // Also relax throttling to avoid 429 when users change client quickly in forms
    Route::get('client/{client}', 'ClientController@show')
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:300,1')
        ->name('clients.show');


    // Increase throttle for file uploads to avoid 429s during repeated attempts
    Route::post('documents', 'DocumentController@store')
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:300,1')
        ->name('documents.store');
    Route::post('documents/delete', 'DocumentController@destroy')
        ->withoutMiddleware('throttle:api')
        ->middleware('throttle:300,1')
        ->name('documents.destroy');

    // Avoid conflict with web resource route name "agents.index"
    // Increase throttle for this endpoint to reduce 429s during Select2 usage in forms
    Route::get('available-interpreters/{job_type}', 'AgentController@index')
        // Remove default api throttling to avoid compounded limits
        ->withoutMiddleware('throttle:api')
        // Apply a more generous throttle just for this endpoint
        ->middleware('throttle:300,1')
        ->name('agents.available');

    // [Moved] Banking routes are now under web.php with prefix('api') and auth middleware
    // This avoids the global 'throttle:api' and ensures session auth via cookies.
});

/*
|--------------------------------------------------------------------------
| Mobile API Routes
|--------------------------------------------------------------------------
| The frontend (mobile/src/api/client.ts) calls these without any prefix
| beyond /api (the base URL). Auth routes use /api/auth/* and all other
| mobile routes use /api/* directly.
*/

// Auth routes (no auth middleware needed)
Route::group(['namespace' => 'Api\Mobile', 'prefix' => 'auth'], function () {
    Route::post('login', 'AuthController@login')->withoutMiddleware('throttle:120,1')->withoutMiddleware('throttle:api');
    Route::post('register', 'AuthController@register')->withoutMiddleware('throttle:120,1')->withoutMiddleware('throttle:api');
});

// PDF routes that handle their own auth via ?token= query parameter (outside mobile.auth)
Route::group(['namespace' => 'Api\Mobile'], function () {
    Route::get('timesheets/{id}/pdf', 'TimesheetController@pdf');
    Route::get('agent-invoices/{id}/pdf', 'AgentInvoiceController@pdf');
    Route::get('client-invoices/{id}/pdf', 'ClientInvoiceController@pdf');
});

// Protected mobile routes (these go to /api/*)
Route::group(['namespace' => 'Api\Mobile', 'middleware' => 'mobile.auth'], function () {

    // Auth
    Route::get('auth/me', 'AuthController@me')
        ->withoutMiddleware('throttle:120,1')
        ->withoutMiddleware('throttle:api');
    Route::post('auth/logout', 'AuthController@logout');
    Route::put('auth/profile', 'AuthController@updateProfile');

    // Interpreter Jobs
    Route::get('interpreter-jobs', 'InterpreterJobController@index');
    Route::get('interpreter-jobs/{id}', 'InterpreterJobController@show');
    Route::post('interpreter-jobs/{id}/accept', 'InterpreterJobController@accept');
    Route::post('interpreter-jobs/{id}/decline', 'InterpreterJobController@decline');
    Route::post('interpreter-jobs/{id}/complete', 'InterpreterJobController@complete');
    Route::post('interpreter-jobs/{id}/dna', 'InterpreterJobController@dna');
    Route::post('interpreter-jobs/{id}/return', 'InterpreterJobController@return');
    Route::post('interpreter-jobs/{id}/check-in', 'InterpreterJobController@checkIn');
    Route::post('interpreter-jobs/{id}/check-out', 'InterpreterJobController@checkOut');
    Route::get('interpreter-jobs/{id}/amount', 'InterpreterJobController@getAmount');
    Route::post('interpreter-jobs/{id}/amount', 'InterpreterJobController@setAmount');
    Route::post('interpreter-jobs/{id}/upload-timesheet', 'InterpreterJobController@uploadTimesheet');
    Route::post('interpreter-jobs/{id}/quotes', 'InterpreterJobController@submitQuote');
    Route::put('interpreter-jobs/{id}/quotes/{quoteId}', 'InterpreterJobController@updateQuote');
    Route::get('interpreter-jobs/{id}/quotes', 'InterpreterJobController@getQuotes');
    Route::post('interpreter-jobs/{id}/expenses', 'InterpreterJobController@submitExpenses');
    Route::get('interpreter-jobs/{id}/expenses', 'InterpreterJobController@getExpenses');

    // Translator Jobs
    Route::get('translator-jobs', 'TranslatorJobController@index');
    Route::get('translator-jobs/{id}', 'TranslatorJobController@show');
    Route::post('translator-jobs/{id}/accept', 'TranslatorJobController@accept');
    Route::post('translator-jobs/{id}/decline', 'TranslatorJobController@decline');
    Route::post('translator-jobs/{id}/quotes', 'TranslatorJobController@submitQuote');
    Route::get('translator-jobs/{id}/quotes', 'TranslatorJobController@getQuotes');

    // Documents
    Route::get('documents', 'DocumentController@index');
    Route::get('documents/{id}', 'DocumentController@show');
    Route::get('documents/{id}/download', 'DocumentController@download');
    Route::post('documents/upload', 'DocumentController@store');
    Route::delete('documents/{id}', 'DocumentController@destroy');

    // Finances (legacy)
    Route::get('finances', 'FinanceController@index');
    Route::get('invoices/{id}', 'FinanceController@showInvoice');

    // Agent Invoices (Payslips)
    Route::get('agent-invoices', 'AgentInvoiceController@index');
    Route::get('agent-invoices/{id}', 'AgentInvoiceController@show');

    // Dashboard Stats (server-side computed)
    Route::get('dashboard-stats', 'DashboardStatsController@index');

    // Activities (real agent activity feed)
    Route::get('activities', 'ActivityController@index');

    // Profile
    Route::get('profile', 'ProfileController@show');
    Route::put('profile', 'ProfileController@update');

    // Push Subscriptions
    Route::post('push-subscriptions', 'PushSubscriptionController@store');
    Route::delete('push-subscriptions', 'PushSubscriptionController@destroy');

    // Test Push (remove after testing)
    Route::get('test-push', 'TestPushController@status');
    Route::post('test-push', 'TestPushController@send');

    // Notifications
    Route::get('notifications', 'NotificationController@index');
    Route::get('notifications/unread-count', 'NotificationController@unreadCount');
    Route::post('notifications/{id}/read', 'NotificationController@markAsRead');
    Route::post('notifications/read-all', 'NotificationController@markAllAsRead');

    // Timesheets
    Route::get('timesheets', 'TimesheetController@index');
    Route::get('timesheets/export', 'TimesheetController@export');
    Route::get('timesheets/{id}', 'TimesheetController@show');
    Route::post('timesheets', 'TimesheetController@store');
    Route::post('timesheets/{id}/sign', 'TimesheetController@sign');
    Route::put('timesheets/{id}', 'TimesheetController@update');
    Route::delete('timesheets/{id}', 'TimesheetController@destroy');
    Route::post('timesheets/{id}/expense-evidence', 'TimesheetController@uploadExpenseEvidence');

    // Conversations & Messages
    Route::get('conversations', 'ConversationController@index');
    Route::get('conversations/{id}', 'ConversationController@show');
    Route::post('conversations', 'ConversationController@store');
    Route::post('conversations/{id}/messages', 'ConversationController@sendMessage');
    Route::patch('messages/{id}/read', 'MessageController@markAsRead');

    // Languages & Statuses
    Route::get('languages', 'LanguageController@index');
    Route::get('statuses', 'StatusController@index');

    // Client Invoices
    Route::get('client-invoices', 'ClientInvoiceController@index');
    Route::get('client-invoices/{id}', 'ClientInvoiceController@show');

    // Remittances
    Route::get('remittances', 'RemittanceController@index');
    Route::get('remittances/{id}', 'RemittanceController@show');
    Route::post('remittances', 'RemittanceController@store');
});
