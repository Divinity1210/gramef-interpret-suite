<?php

namespace Tests\Feature;

use App\Client;
use App\ClientInvoice;
use App\Document;
use App\PaymentProof;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPaymentProofSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure roles table has the 'client' role required by route middleware
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_client_can_submit_payment_proof_with_files()
    {
        putenv('PAYMENT_PROOFS_DISK=public');
        $_ENV['PAYMENT_PROOFS_DISK'] = 'public';
        $_SERVER['PAYMENT_PROOFS_DISK'] = 'public';

        Storage::fake('public');
        Mail::fake();

        // Create a client user and attach client profile
        $user = factory(User::class)->create();
        $user->assignRole('client');

        $client = factory(Client::class)->create(['user_id' => $user->id]);

        // Create a client invoice for this client
        $invoice = ClientInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'CLI-TST-0001',
            'date_range_start' => now()->startOfMonth(),
            'date_range_end' => now()->endOfMonth(),
            'template' => 'v2',
            'include_vat' => true,
            'vat_rate' => 20.00,
            'subtotal' => 80.00,
            'vat_amount' => 16.00,
            'total' => 96.00,
            'status' => 'sent',
        ]);

        // Act as the client and submit payment proof
        $this->actingAs($user);

        $file1 = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $response = $this->post(route('invoices.client.payment-proofs.store', $invoice->id), [
            'amount' => '96.00',
            'paid_at' => now()->toDateString(),
            'method' => 'Bank',
            'reference' => 'E2E-1234',
            'notes' => 'Automated test',
            'files' => [$file1],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify database records
        $this->assertDatabaseHas('payment_proofs', [
            'client_invoice_id' => $invoice->id,
            'amount' => 96.00,
            'status' => 'pending',
        ]);

        $proof = PaymentProof::where('client_invoice_id', $invoice->id)->first();
        $this->assertNotNull($proof);

        $this->assertDatabaseHas('documents', [
            'documentable_id' => $proof->id,
            'documentable_type' => \App\PaymentProof::class,
            'type' => config('enums.document_types.file_to_be_translated'),
        ]);

        // Verify file stored on public disk under payment-proofs/{id}/
        $doc = Document::where('documentable_id', $proof->id)
            ->where('documentable_type', \App\PaymentProof::class)
            ->first();
        $this->assertNotNull($doc);
        $this->assertTrue(Storage::disk('public')->exists($doc->url));
    }
}
