<?php

use Illuminate\Database\Seeder;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Find a client user created by UsersTableSeeder
        $user = User::where('email', 'client@example.com')->first();
        if (!$user || !$user->client) {
            $this->command->warn('No client user found (client@example.com). Skipping ClientInvoiceSeeder.');
            return;
        }

        $clientId = $user->client->id;

        DB::transaction(function () use ($clientId) {
            // Create a simple invoice covering the last month
            $start = Carbon::now()->subMonth()->startOfMonth();
            $end = Carbon::now()->subMonth()->endOfMonth();

            $invoice = ClientInvoice::create([
                'client_id' => $clientId,
                'invoice_number' => 'CLI-0001',
                'company_id' => null, // optional
                'date_range_start' => $start->toDateString(),
                'date_range_end' => $end->toDateString(),
                'template' => 'standard',
                'include_vat' => true,
                'vat_rate' => 20.00,
                'subtotal' => 0,
                'vat_amount' => 0,
                'total' => 0,
                'status' => 'pending',
            ]);

            // Add a couple of items
            $items = [
                [
                    'description' => 'Interpreting service',
                    'amount' => 100.00,
                    'surcharge' => 0,
                    'other_charges' => 0,
                    'vat_applicable' => true,
                ],
                [
                    'description' => 'Travel expense',
                    'amount' => 25.00,
                    'surcharge' => 0,
                    'other_charges' => 0,
                    'vat_applicable' => false,
                ],
            ];

            $subtotal = 0;
            $vatAmount = 0;

            foreach ($items as $item) {
                $lineVat = 0;
                if ($item['vat_applicable']) {
                    $lineVat = round($item['amount'] * 0.20, 2);
                }

                ClientInvoiceItem::create([
                    'client_invoice_id' => $invoice->id,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'surcharge' => $item['surcharge'],
                    'other_charges' => $item['other_charges'],
                    'vat_applicable' => $item['vat_applicable'],
                    'metadata' => null,
                ]);

                $subtotal += $item['amount'] + $item['surcharge'] + $item['other_charges'];
                $vatAmount += $lineVat;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $subtotal + $vatAmount,
            ]);
        });
    }
}