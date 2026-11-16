<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use App\User;
use App\Client;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\Mail\ClientInvoiceMail;
use Spatie\Permission\Models\Role;

class AdminClientInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure required roles exist for middleware checks
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    }

    private function createVerifiedUser(): User
    {
        $user = User::create([
            'title' => 0,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => 'password',
            'enabled' => 1,
            // email_verified_at is NOT fillable; set after creation
        ]);
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole('admin');
        return $user;
    }

    private function createClientWithUser(): Client
    {
        $user = User::create([
            'title' => 0,
            'first_name' => 'Client',
            'last_name' => 'User',
            'email' => 'client-'.uniqid().'@example.com',
            'password' => 'password',
            'enabled' => 1,
            // email_verified_at is NOT fillable; set after creation
        ]);
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole('client');
        $client = Client::create([
            'user_id' => $user->id,
            'contact_number' => '0000000000',
            'client_address_line_1' => '1 Test Street',
            'client_address_line_2' => 'Suite 100',
            'client_county' => 'Testshire',
            'client_postcode' => 'TS1 1ST',
            'seen_terms' => 1,
        ]);
        return $client;
    }

    public function test_admin_can_access_client_invoice_builder()
    {
        $admin = $this->createVerifiedUser();
        $this->actingAs($admin);

        $response = $this->get(route('invoices.admin.client.create'));
        $response->assertStatus(200);
        $response->assertSee('Create Client Invoice');
    }

    public function test_admin_can_store_client_invoice_and_redirect_to_pdf()
    {
        $admin = $this->createVerifiedUser();
        $this->actingAs($admin);

        $client = $this->createClientWithUser();

        $payload = [
            'client_id' => $client->id,
            'invoice_date' => now()->toDateString(),
            'include_vat' => true,
            'vat_rate' => 20,
            'service_description' => 'Interpreting service',
            'service_date' => now()->toDateString(),
            'service_amount' => 100.00,
            'parking_amount' => 0,
        ];

        $response = $this->post(route('invoices.admin.client.store'), $payload);
        $response->assertStatus(302);

        // Retrieve the created invoice
        $invoice = ClientInvoice::first();
        $this->assertNotNull($invoice, 'Client invoice should be created');

        // Ensure we are redirected to the PDF route for the created invoice
        $response->assertRedirect(route('invoices.admin.client.pdf', $invoice->id));

        // Basic totals check
        $this->assertEquals(100.00, (float)$invoice->subtotal);
        $this->assertEquals(20.00, (float)$invoice->vat_amount);
        $this->assertEquals(120.00, (float)$invoice->total);
    }

    public function test_admin_can_update_client_invoice_recalculates_totals()
    {
        $admin = $this->createVerifiedUser();
        $this->actingAs($admin);

        $client = $this->createClientWithUser();

        // Create a basic invoice and one item
        $invoice = ClientInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'CI-000001',
            'invoice_date' => now()->toDateString(),
            'include_vat' => true,
            'vat_rate' => 20,
            'subtotal' => 0,
            'vat_amount' => 0,
            'total' => 0,
            'status' => 'pending',
            'template' => 'v2',
        ]);

        ClientInvoiceItem::create([
            'client_invoice_id' => $invoice->id,
            'description' => 'Service line 1',
            'amount' => 100.00,
            'surcharge' => 0,
            'other_charges' => 0,
            'vat_applicable' => true,
            'metadata' => [
                'date' => now()->toDateString(),
            ],
        ]);

        // Update with parking and ensure totals recalc
        $payload = [
            'parking_amount' => 10.00,
            'include_vat' => true,
            'vat_rate' => 20,
        ];

        $response = $this->patch(route('invoices.admin.client.update', $invoice->id), $payload);
        $response->assertStatus(302);
        $response->assertRedirect(route('invoices.admin.index', ['type' => 'client']));

        $invoice->refresh();
        $this->assertEquals(110.00, (float)$invoice->subtotal);
        $this->assertEquals(22.00, (float)$invoice->vat_amount);
        $this->assertEquals(132.00, (float)$invoice->total);
    }

    public function test_admin_can_send_client_invoice_marks_sent_and_dispatches_mail()
    {
        $admin = $this->createVerifiedUser();
        $this->actingAs($admin);

        $client = $this->createClientWithUser();

        $invoice = ClientInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'CI-000002',
            'invoice_date' => now()->toDateString(),
            'include_vat' => true,
            'vat_rate' => 20,
            'subtotal' => 100.00,
            'vat_amount' => 20.00,
            'total' => 120.00,
            'status' => 'pending',
            'template' => 'v2',
        ]);

        ClientInvoiceItem::create([
            'client_invoice_id' => $invoice->id,
            'description' => 'Service line',
            'amount' => 100.00,
            'surcharge' => 0,
            'other_charges' => 0,
            'vat_applicable' => true,
            'metadata' => [
                'date' => now()->toDateString(),
            ],
        ]);

        Mail::fake();

        $response = $this->post(route('invoices.admin.client.send', $invoice->id));
        $response->assertStatus(200);

        $invoice->refresh();
        $this->assertEquals('sent', $invoice->status);
        $this->assertNotNull($invoice->sent_at);

        // The mailable implements ShouldQueue, so it will be queued when using Mail::fake
        Mail::assertQueued(ClientInvoiceMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->user->email);
        });
    }
}