<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ClientInvoice;
use App\Notifications\OverdueInvoiceReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendOverdueInvoiceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send-overdue-invoice-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send overdue invoice reminders to clients for invoices not yet paid or with no payment proofs submitted.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Use UK timezone for overdue boundary
        $today = Carbon::today('Europe/London');
        Log::info('Running overdue invoice reminder checks', ['date' => $today->toDateString()]);

        $invoices = ClientInvoice::with(['client.user'])
            ->where('status', 'sent')
            ->whereNotNull('invoice_date')
            ->whereDate('invoice_date', '<', $today)
            ->whereDoesntHave('receipts')
            ->whereDoesntHave('paymentProofs')
            ->limit(500)
            ->get();

        $sentCount = 0;

        foreach ($invoices as $invoice) {
            $user = optional($invoice->client)->user;
            if (!$user || !$user->enabled) {
                Log::info('Skipping overdue reminder: client user not present or disabled', [
                    'invoice_id' => $invoice->id,
                    'client_id' => optional($invoice->client)->id,
                ]);
                continue;
            }

            try {
                $user->notify(new OverdueInvoiceReminder($invoice));
                $sentCount++;
                Log::info('Sent overdue invoice reminder', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_user_id' => $user->id,
                    'client_email' => $user->email,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send overdue invoice reminder', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Overdue invoice reminders sent: {$sentCount}");
        Log::info('Overdue invoice reminder job complete', ['sent' => $sentCount]);
    }
}