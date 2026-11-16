<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\ClientInvoice;

class ClientInvoiceTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_client_invoice_totals_are_correct()
    {
        $this->seed(\Database\Seeders\ClientInvoicePreviewSeeder::class);

        $invoice = ClientInvoice::where('invoice_number', 'CLI-PREVIEW-0001')->first();
        $this->assertNotNull($invoice, 'Preview client invoice not found. Run ClientInvoicePreviewSeeder.');

        $this->assertEquals(135.50, (float)$invoice->subtotal, 'Subtotal should be £135.50');
        $this->assertEquals(24.00, (float)$invoice->vat_amount, 'VAT should be £24.00');
        $this->assertEquals(159.50, (float)$invoice->total, 'Total should be £159.50');
    }
}
