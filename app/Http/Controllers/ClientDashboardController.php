<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Client;
use App\InterpreterJob;
use App\TranslatorJob;
use App\ClientInvoice;

class ClientDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        // Resolve client by user_id; fallback to null-safe handling
        $client = Client::where('user_id', optional($user)->id)->first();

        // Use UK timezone for date windows
        $today = Carbon::today('Europe/London');
        $in7 = Carbon::today('Europe/London')->addDays(7);

        $upcomingInterpreterJobs = 0;
        $upcomingTranslatorJobs = 0;
        $recentJobs = collect();
        $invoiceStats = [
            'total' => 0,
            'sent' => 0,
            'paid' => 0,
            'overdue' => 0,
        ];
        $recentInvoices = collect();

        if ($client) {
            try {
                $upcomingInterpreterJobs = InterpreterJob::query()
                    ->where('client_id', $client->id)
                    ->whereDate('appointment_date', '>=', $today)
                    ->whereDate('appointment_date', '<=', $in7)
                    ->count();
            } catch (\Throwable $e) { /* safe fallback */ }

            try {
                $upcomingTranslatorJobs = TranslatorJob::query()
                    ->where('client_id', $client->id)
                    ->whereDate('target_date', '>=', $today)
                    ->whereDate('target_date', '<=', $in7)
                    ->count();
            } catch (\Throwable $e) { /* safe fallback */ }

            try {
                $recentJobs = InterpreterJob::with(['agent.user','toLanguage','timesheet'])
                    ->where('client_id', $client->id)
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get();
            } catch (\Throwable $e) { $recentJobs = collect(); }

            try {
                $invoiceStats['total'] = ClientInvoice::where('client_id', $client->id)->count();
                $invoiceStats['sent'] = ClientInvoice::where('client_id', $client->id)->where('status', 'sent')->count();
                $invoiceStats['paid'] = ClientInvoice::where('client_id', $client->id)->where('status', 'paid')->count();
                // Overdue: sent and invoice_date is before today
                $invoiceStats['overdue'] = ClientInvoice::where('client_id', $client->id)
                    ->where('status', 'sent')
                    ->whereNotNull('invoice_date')
                    ->whereDate('invoice_date', '<', $today)
                    ->count();
            } catch (\Throwable $e) { /* safe fallback */ }

            // Recent invoices (latest 5)
            try {
                $recentInvoices = ClientInvoice::query()
                    ->where('client_id', $client->id)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get();
            } catch (\Throwable $e) { $recentInvoices = collect(); }
        }

        return view('client.dashboard', compact(
            'client',
            'upcomingInterpreterJobs',
            'upcomingTranslatorJobs',
            'recentJobs',
            'invoiceStats',
            'recentInvoices'
        ));
    }
}