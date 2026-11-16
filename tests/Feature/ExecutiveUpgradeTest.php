<?php

namespace Tests\Feature;

use App\Agent;
use App\ClientInvoice;
use App\InterpreterJob;
use App\Services\PriceBookService;
use App\Services\SmartBookingParserService;
use App\Services\SmartRemittanceMatcherService;
use App\Services\TrafficLightCheckerService;
use App\TenantSetting;
use App\Timesheet;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExecutiveUpgradeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_showcase_and_login_pages_load(): void
    {
        $this->get('/showcase')->assertStatus(200)->assertSee('GRAMEF');
        $this->get('/login')->assertStatus(200)->assertSee('1-Click Demo Role Access');
    }

    public function test_no_password_magic_timesheet_signature_and_pricebook_calculation(): void
    {
        $ts = Timesheet::where('magic_sign_token', 'gramef-demo-sign-green-01')->firstOrFail();

        $this->get('/sign/timesheet/' . $ts->magic_sign_token)
            ->assertStatus(200)
            ->assertSee('10-Second Client Sign-Off');

        $response = $this->post('/sign/timesheet/' . $ts->magic_sign_token, [
            'client_name' => 'Dr. Sarah Jenkins',
            'client_designation' => 'Consultant Clinician',
            'actual_hours' => 2,
            'actual_minutes' => 0,
            'client_signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);

        $response->assertRedirect('/sign/timesheet/' . $ts->magic_sign_token);
        $ts->refresh();
        $this->assertEquals('verified', $ts->status);
        $this->assertEquals('Dr. Sarah Jenkins', $ts->client_name);
    }

    public function test_traffic_light_checker_and_price_book_ooh_split(): void
    {
        $priceBook = app(PriceBookService::class);
        [$day, $ooh] = $priceBook->splitDaytimeAndOoh('2026-11-16', '17:00', 120);
        $this->assertEquals(60, $day);
        $this->assertEquals(60, $ooh);

        $amberJob = InterpreterJob::where('client_reference', 'NHS-WOM-24811')->firstOrFail();
        $eval = app(TrafficLightCheckerService::class)->evaluate($amberJob);
        $this->assertEquals('amber', $eval['status']);
        $this->assertNotEmpty($eval['warnings']);
    }

    public function test_smart_remittance_matcher_and_never_chase_twice_protection(): void
    {
        $matcher = app(SmartRemittanceMatcherService::class);
        $matches = $matcher->parseAndMatch("CI-104201 | NHS Batch | £1420.00\nCI-104203 | NHS Batch | £645.00");
        $this->assertCount(2, $matches);
        $this->assertTrue($matches[0]['found']);
        $this->assertTrue($matches[0]['exact_match']);

        $inv = ClientInvoice::where('invoice_number', 'CI-104201')->firstOrFail();
        $this->post('/remittance/quick/' . $inv->remittance_magic_token, [
            'reference' => 'BACS-TEST-99',
            'amount' => 1420.00,
        ])->assertRedirect('/remittance/quick/' . $inv->remittance_magic_token);

        $inv->refresh();
        $this->assertTrue((bool) $inv->chaser_paused);
    }

    public function test_paste_an_email_quick_booking_parser_and_follow_up(): void
    {
        $parser = app(SmartBookingParserService::class);
        $parsed = $parser->parse("Language: Arabic\nDate: 20/11/2026\nTime: 10:30 - 12:30\nPostcode: B15 2TG\nReference: NHS-QE-88412");
        $this->assertEquals('Arabic', $parsed['language']);
        $this->assertEquals('B15 2TG', $parsed['postcode']);
        $this->assertEquals(2, $parsed['duration_hours']);
        $this->assertEquals('NHS-QE-88412', $parsed['client_reference']);

        $admin = User::where('email', 'superadmin@gramef.com')->firstOrFail();
        $job = InterpreterJob::where('client_reference', 'NHS-QE-24810')->firstOrFail();

        $this->actingAs($admin)
            ->post('/interpreter-jobs/' . $job->id . '/follow-up')
            ->assertRedirect();

        $followUp = InterpreterJob::where('client_reference', 'NHS-QE-24810-FU')->first();
        $this->assertNotNull($followUp);
        $this->assertEquals($job->agent_id, $followUp->requested_agent_id);
    }

    public function test_admin_executive_routes_and_bacs_csv_export(): void
    {
        $admin = User::where('email', 'superadmin@gramef.com')->firstOrFail();

        $this->actingAs($admin)->get('/admin/dashboard')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/traffic-light')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/price-book')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/credit-control')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/reconciliation')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/compliance')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/white-label')->assertStatus(200);

        $csvResp = $this->actingAs($admin)->get('/admin/reconciliation/bank-payout-csv');
        $csvResp->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csvResp->headers->get('content-type'));

        // White-label preset toggle
        $this->actingAs($admin)->post('/admin/white-label/preset/atoz')->assertRedirect();
        $this->assertEquals('atoz', TenantSetting::where('is_active', true)->first()->slug);
    }
}
