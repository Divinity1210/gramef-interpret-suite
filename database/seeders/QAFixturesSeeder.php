<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\User;
use App\Client;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\PaymentProof;
use App\Receipt;

class QAFixturesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Ensure preview admin and client exist (created by other seeders or create here if missing)
            $admin = User::firstOrCreate(
                ['email' => 'admin.preview@example.com'],
                [
                    'title' => 0,
                    'first_name' => 'Preview',
                    'last_name' => 'Admin',
                    'password' => 'password',
                    'enabled' => 1,
                    'email_verified_at' => now(),
                ]
            );

            $clientUser = User::firstOrCreate(
                ['email' => 'client.preview@example.com'],
                [
                    'title' => 0,
                    'first_name' => 'Preview',
                    'last_name' => 'Client',
                    'password' => 'password',
                    'enabled' => 1,
                ]
            );
            $clientUser->update(['password' => 'password']);

            // Assign 'client' role if available and not already assigned
            try {
                if (class_exists('Spatie\\Permission\\Models\\Role')) {
                    $clientRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
                    if (! method_exists($clientUser, 'hasRole') || ! $clientUser->hasRole('client')) {
                        $clientUser->assignRole($clientRole);
                    }
                }
            } catch (\Throwable $e) {
                // Continue silently if roles package is not configured
            }

            $client = Client::firstOrCreate(
                ['user_id' => $clientUser->id],
                [
                    'contact_number' => '0000000000',
                    'client_address_line_1' => '1 Preview Street',
                    'client_address_line_2' => 'Suite 100',
                    'client_county' => 'Previewshire',
                    'client_postcode' => 'PR3 1VW',
                    'seen_terms' => 1,
                ]
            );

            // Generate some sample PDF files to attach to payment proofs
            $this->ensureSamplePdf('samples/qa-proof-1.pdf', '<h2>QA Proof 1</h2><p>Bank transfer confirmation (sample)</p>');
            $this->ensureSamplePdf('samples/qa-proof-2.pdf', '<h2>QA Proof 2</h2><p>Remittance advice (sample)</p>');
            $this->ensureSamplePdf('samples/qa-proof-3.pdf', '<h2>QA Proof 3</h2><p>Payment screenshot (sample)</p>');

            // Invoice 1: v2 template candidate (few items) – will show almost full payment flow
            $start1 = Carbon::now()->subMonth()->startOfMonth();
            $end1 = Carbon::now()->subMonth()->endOfMonth();
            $inv1 = ClientInvoice::firstOrCreate(
                [
                    'client_id' => $client->id,
                    'invoice_number' => 'CLI-QA-0001',
                ],
                [
                    'company_id' => null,
                    'date_range_start' => $start1->toDateString(),
                    'date_range_end' => $end1->toDateString(),
                    'template' => 'v2',
                    'include_vat' => true,
                    'vat_rate' => 20.00,
                    'subtotal' => 0,
                    'vat_amount' => 0,
                    'total' => 0,
                    'status' => 'sent',
                ]
            );

            if ($inv1->items()->count() === 0) {
                $lines1 = [
                    ['description' => 'Interpreting service', 'amount' => 120.00, 'vat_applicable' => true],
                    ['description' => 'Travel expense', 'amount' => 15.50, 'vat_applicable' => false],
                ];
                $subtotal1 = 0; $vat1 = 0;
                foreach ($lines1 as $line) {
                    ClientInvoiceItem::create([
                        'client_invoice_id' => $inv1->id,
                        'description' => $line['description'],
                        'amount' => $line['amount'],
                        'surcharge' => 0,
                        'other_charges' => 0,
                        'vat_applicable' => $line['vat_applicable'],
                        'metadata' => null,
                    ]);
                    $subtotal1 += $line['amount'];
                    if ($line['vat_applicable']) {
                        $vat1 += round($line['amount'] * 0.20, 2);
                    }
                }
                $inv1->update(['subtotal' => $subtotal1, 'vat_amount' => $vat1, 'total' => $subtotal1 + $vat1]);
            }

            // Payment proofs for invoice 1
            $inv1->refresh();
            $half1 = round($inv1->total / 2, 2);
            $pendingProof1 = PaymentProof::create([
                'client_invoice_id' => $inv1->id,
                'client_id' => $client->id,
                'submitted_by' => $clientUser->id,
                'amount' => $half1,
                'currency' => 'GBP',
                'paid_at' => Carbon::now()->subDays(5),
                'method' => 'Bank Transfer',
                'reference' => 'QA-PROOF-1',
                'notes' => 'First half payment (QA seed)',
                'status' => 'pending',
            ]);
            // Use integer document type per config; default to generic file (0)
            $docType = config('enums.document_types.file_to_be_translated');
            $pendingProof1->documents()->create(['url' => 'samples/qa-proof-1.pdf', 'name' => 'QA Proof 1.pdf', 'type' => $docType]);
            $pendingProof1->documents()->create(['url' => 'samples/qa-proof-2.pdf', 'name' => 'QA Proof 2.pdf', 'type' => $docType]);

            $verifiedProof1 = PaymentProof::create([
                'client_invoice_id' => $inv1->id,
                'client_id' => $client->id,
                'submitted_by' => $clientUser->id,
                'amount' => round($inv1->total - $half1, 2),
                'currency' => 'GBP',
                'paid_at' => Carbon::now()->subDays(3),
                'method' => 'Card',
                'reference' => 'QA-PROOF-2',
                'notes' => 'Remaining payment (QA seed)',
                'status' => 'verified',
                'verified_by' => $admin->id,
                'verified_at' => Carbon::now()->subDays(2),
            ]);
            $verifiedProof1->documents()->create(['url' => 'samples/qa-proof-3.pdf', 'name' => 'QA Proof 3.pdf', 'type' => $docType]);

            // Receipt for invoice 1 covering verified proof
            $receipt1 = Receipt::firstOrCreate(
                [
                    'client_invoice_id' => $inv1->id,
                    'receipt_number' => 'RCPT-QA-0001',
                ],
                [
                    'total_paid' => $inv1->total,
                    'issued_at' => Carbon::now()->subDay(),
                    'generated_by' => $admin->id,
                    'payment_proof_ids' => [$verifiedProof1->id],
                    'metadata' => null,
                ]
            );
            $inv1->update(['status' => 'paid']);

            // Invoice 2: v3 template candidate (many items) – shows partial payments and rejected proof
            $start2 = Carbon::now()->startOfMonth();
            $end2 = Carbon::now()->endOfMonth();
            $inv2 = ClientInvoice::firstOrCreate(
                [
                    'client_id' => $client->id,
                    'invoice_number' => 'CLI-QA-0002',
                ],
                [
                    'company_id' => null,
                    'date_range_start' => $start2->toDateString(),
                    'date_range_end' => $end2->toDateString(),
                    'template' => 'v3',
                    'include_vat' => true,
                    'vat_rate' => 20.00,
                    'subtotal' => 0,
                    'vat_amount' => 0,
                    'total' => 0,
                    'status' => 'sent',
                ]
            );

            if ($inv2->items()->count() === 0) {
                $lines2 = [
                    ['description' => 'Job A – Consultation', 'amount' => 60.00, 'vat_applicable' => true],
                    ['description' => 'Job A – Travel', 'amount' => 12.00, 'vat_applicable' => false],
                    ['description' => 'Job B – On-site support', 'amount' => 90.00, 'vat_applicable' => true],
                    ['description' => 'Job B – Materials', 'amount' => 22.50, 'vat_applicable' => true],
                    ['description' => 'Job C – Interpreting', 'amount' => 130.00, 'vat_applicable' => true],
                    ['description' => 'Job C – Travel', 'amount' => 15.75, 'vat_applicable' => false],
                    ['description' => 'Job C – Parking', 'amount' => 6.20, 'vat_applicable' => false],
                ];
                $subtotal2 = 0; $vat2 = 0;
                foreach ($lines2 as $line) {
                    ClientInvoiceItem::create([
                        'client_invoice_id' => $inv2->id,
                        'description' => $line['description'],
                        'amount' => $line['amount'],
                        'surcharge' => 0,
                        'other_charges' => 0,
                        'vat_applicable' => $line['vat_applicable'],
                        'metadata' => null,
                    ]);
                    $subtotal2 += $line['amount'];
                    if ($line['vat_applicable']) {
                        $vat2 += round($line['amount'] * 0.20, 2);
                    }
                }
                $inv2->update(['subtotal' => $subtotal2, 'vat_amount' => $vat2, 'total' => $subtotal2 + $vat2]);
            }

            // Payment proofs for invoice 2: one rejected, one pending, one verified (partial)
            $inv2->refresh();
            $rejected2 = PaymentProof::create([
                'client_invoice_id' => $inv2->id,
                'client_id' => $client->id,
                'submitted_by' => $clientUser->id,
                'amount' => 30.00,
                'currency' => 'GBP',
                'paid_at' => Carbon::now()->subDays(6),
                'method' => 'Bank Transfer',
                'reference' => 'QA-INV2-REJ',
                'notes' => 'Amount mismatch (QA seed)',
                'status' => 'rejected',
                'verified_by' => $admin->id,
                'verified_at' => Carbon::now()->subDays(5),
                'rejection_reason' => 'Amount did not match remittance',
            ]);
            $rejected2->documents()->create(['url' => 'samples/qa-proof-1.pdf', 'name' => 'QA Proof 1.pdf', 'type' => $docType]);

            $pending2 = PaymentProof::create([
                'client_invoice_id' => $inv2->id,
                'client_id' => $client->id,
                'submitted_by' => $clientUser->id,
                'amount' => 50.00,
                'currency' => 'GBP',
                'paid_at' => Carbon::now()->subDays(2),
                'method' => 'Card',
                'reference' => 'QA-INV2-PEN',
                'notes' => 'Waiting verification (QA seed)',
                'status' => 'pending',
            ]);
            $pending2->documents()->create(['url' => 'samples/qa-proof-2.pdf', 'name' => 'QA Proof 2.pdf', 'type' => $docType]);

            $verified2 = PaymentProof::create([
                'client_invoice_id' => $inv2->id,
                'client_id' => $client->id,
                'submitted_by' => $clientUser->id,
                'amount' => 200.00,
                'currency' => 'GBP',
                'paid_at' => Carbon::now()->subDay(),
                'method' => 'Bank Transfer',
                'reference' => 'QA-INV2-VER',
                'notes' => 'Partial payment (QA seed)',
                'status' => 'verified',
                'verified_by' => $admin->id,
                'verified_at' => Carbon::now(),
            ]);
            $verified2->documents()->create(['url' => 'samples/qa-proof-3.pdf', 'name' => 'QA Proof 3.pdf', 'type' => $docType]);

            $receipt2 = Receipt::firstOrCreate(
                [
                    'client_invoice_id' => $inv2->id,
                    'receipt_number' => 'RCPT-QA-0002',
                ],
                [
                    'total_paid' => 200.00,
                    'issued_at' => Carbon::now(),
                    'generated_by' => $admin->id,
                    'payment_proof_ids' => [$verified2->id],
                    'metadata' => null,
                ]
            );
        });
    }

    private function ensureSamplePdf(string $path, string $html): void
    {
        $disk = env('SAMPLE_PDFS_DISK') ?: (app()->environment('production') ? 's3' : 'public');
        if (! Storage::disk($disk)->exists($path)) {
            try {
                // Use mPDF to generate a small, valid PDF for a great QA UX
                $mpdf = new \Mpdf\Mpdf(['tempDir' => storage_path('app/mpdf')]);
                $mpdf->WriteHTML($html);
                $content = $mpdf->Output('', 'S');
                Storage::disk($disk)->put($path, $content);
            } catch (\Throwable $e) {
                // Fallback: store plain text with .pdf extension if mPDF fails
                Storage::disk($disk)->put($path, 'QA sample proof document');
            }
        }
    }
}
