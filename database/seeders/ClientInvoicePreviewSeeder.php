<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\User;
use App\Client;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use Spatie\Permission\Models\Role;

class ClientInvoicePreviewSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Ensure the 'client' role exists
            $clientRole = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

            // Create (or fetch) a preview client user
            $user = User::firstOrCreate(
                ['email' => 'client.preview@example.com'],
                [
                    'title' => 0,
                    'first_name' => 'Preview',
                    'last_name' => 'Client',
                    // Set plain password; User model mutator will hash it once
                    'password' => 'password',
                    'enabled' => 1,
                ]
            );

            // Ensure password updated (in case existing user was seeded with double-hashed password)
            $user->update(['password' => 'password']);

            // Assign role if not already assigned
            if (! $user->hasRole('client')) {
                $user->assignRole($clientRole);
            }

            // Create (or fetch) a Client record
            $client = Client::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'contact_number' => '0000000000',
                    'client_address_line_1' => '1 Preview Street',
                    'client_address_line_2' => 'Suite 100',
                    'client_county' => 'Previewshire',
                    'client_postcode' => 'PR3 1VW',
                    'seen_terms' => 1,
                ]
            );

            // Create a client invoice for last month
            $start = Carbon::now()->subMonth()->startOfMonth();
            $end = Carbon::now()->subMonth()->endOfMonth();

            $invoice = ClientInvoice::firstOrCreate(
                [
                    'client_id' => $client->id,
                    'invoice_number' => 'CLI-PREVIEW-0001',
                ],
                [
                    'company_id' => null,
                    'date_range_start' => $start->toDateString(),
                    'date_range_end' => $end->toDateString(),
                    // Use v2 template for horizontal A4 layout
                    'template' => 'v2',
                    'include_vat' => true,
                    'vat_rate' => 20.00,
                    'subtotal' => 0,
                    'vat_amount' => 0,
                    'total' => 0,
                    'status' => 'pending',
                ]
            );

            // Ensure template is set to v2 even if the invoice already existed
            if ($invoice->template !== 'v2') {
                $invoice->update(['template' => 'v2']);
            }

            // If no items yet, add a couple
            if ($invoice->items()->count() === 0) {
                $lines = [
                    [
                        'description' => 'Interpreting service',
                        'amount' => 120.00,
                        'surcharge' => 0,
                        'other_charges' => 0,
                        'vat_applicable' => true,
                    ],
                    [
                        'description' => 'Travel expense',
                        'amount' => 15.50,
                        'surcharge' => 0,
                        'other_charges' => 0,
                        'vat_applicable' => false,
                    ],
                ];

                $subtotal = 0; $vatAmount = 0;
                foreach ($lines as $line) {
                    ClientInvoiceItem::create([
                        'client_invoice_id' => $invoice->id,
                        'description' => $line['description'],
                        'amount' => $line['amount'],
                        'surcharge' => $line['surcharge'],
                        'other_charges' => $line['other_charges'],
                        'vat_applicable' => $line['vat_applicable'],
                        'metadata' => null,
                    ]);

                    $subtotal += $line['amount'] + $line['surcharge'] + $line['other_charges'];
                    if ($line['vat_applicable']) {
                        $vatAmount += round($line['amount'] * 0.20, 2);
                    }
                }

                $invoice->update([
                    'subtotal' => $subtotal,
                    'vat_amount' => $vatAmount,
                    'total' => $subtotal + $vatAmount,
                ]);
            }
        });
    }
}