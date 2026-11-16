<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Company;
use App\User;
use App\Client;
use App\Organisation;
use App\InterpreterJob;
use App\TranslatorJob;
use App\ClientInvoice;
use App\ClientInvoiceItem;

class AdminJobPaymentsCompanyFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeClientUnderCompany(Company $company): Client
    {
        $user = User::create([
            'title' => 0,
            'first_name' => 'Client',
            'last_name' => 'User',
            'email' => 'client-' . uniqid() . '@example.com',
            'password' => 'password',
            'enabled' => 1,
        ]);
        $user->email_verified_at = now();
        $user->save();

        $client = Client::create([
            'user_id' => $user->id,
            'contact_number' => '0000000000',
            'client_address_line_1' => '1 Test Street',
            'client_address_line_2' => 'Suite 100',
            'client_county' => 'Testshire',
            'client_postcode' => 'TS1 1ST',
            'seen_terms' => 1,
        ]);

        Organisation::create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'organisation_company' => $company->name,
        ]);

        return $client;
    }

    public function test_not_invoiced_filters_by_company()
    {
        $companyA = Company::create(['name' => 'Acme Hospital']);
        $companyB = Company::create(['name' => 'Other Corp']);

        $clientA1 = $this->makeClientUnderCompany($companyA);
        $clientA2 = $this->makeClientUnderCompany($companyA);
        $clientB1 = $this->makeClientUnderCompany($companyB);

        $ijA = factory(InterpreterJob::class)->create(['client_id' => $clientA1->id]);
        $tjA = factory(TranslatorJob::class)->create(['client_id' => $clientA2->id]);
        $ijB = factory(InterpreterJob::class)->create(['client_id' => $clientB1->id]);

        $resp = $this->getJson(route('dev.preview.admin.job-payments.data', [
            'mode' => 'client',
            'status' => 'not_invoiced',
            'company_id' => $companyA->id,
            'per_page' => 100,
        ]));
        $resp->assertStatus(200);
        $payload = $resp->json();
        $rows = collect($payload['data'] ?? []);

        $ids = $rows->pluck('job_id')->all();
        $this->assertContains($ijA->id, $ids);
        $this->assertContains($tjA->id, $ids);
        $this->assertNotContains($ijB->id, $ids);
    }

    public function test_unpaid_invoices_filter_by_company()
    {
        $companyA = Company::create(['name' => 'Acme Hospital']);
        $clientA = $this->makeClientUnderCompany($companyA);

        $ij = factory(InterpreterJob::class)->create(['client_id' => $clientA->id]);

        $invoice = ClientInvoice::create([
            'client_id' => $clientA->id,
            'company_id' => $companyA->id,
            'invoice_number' => 'CI-TEST-' . uniqid(),
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
            'job_id' => $ij->id,
            'description' => 'Interpreting service',
            'amount' => 0,
            'surcharge' => 0,
            'other_charges' => 0,
            'vat_applicable' => true,
            'metadata' => [
                'job_type' => \App\InterpreterJob::class,
                'date' => now()->toDateString(),
            ],
        ]);

        $resp = $this->getJson(route('dev.preview.admin.job-payments.data', [
            'mode' => 'client',
            'status' => 'unpaid',
            'company_id' => $companyA->id,
            'per_page' => 100,
        ]));
        $resp->assertStatus(200);
        $payload = $resp->json();
        $rows = collect($payload['data'] ?? []);

        $row = $rows->firstWhere('job_id', $ij->id);
        $this->assertNotNull($row, 'Job row should be returned when filtering by company');
        $this->assertEquals($invoice->invoice_number, $row['invoice_number']);
        $this->assertEquals('Unpaid', $row['payment_status']);
    }
}

