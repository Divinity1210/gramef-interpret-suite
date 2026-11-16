<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use App\User;
use App\Client;
use App\Agent;
use App\AgentJobEntry;
use App\AgentJobEntryExpense;
use App\AgentInvoice;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\InterpreterJob;
use App\Payslip;
use App\Timesheet;
use App\Mail\JobMail;

class TimesheetSameDayCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure required roles exist
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
    }

    private function createAdminUser(): User
    {
        $user = \factory(User::class)->create();
        $user->assignRole('admin');
        return $user;
    }

    private function createClientWithUser(): Client
    {
        $user = \factory(User::class)->create();
        $user->assignRole('client');
        return Client::create([
            'user_id' => $user->id,
            'contact_number' => '0000000000',
            'client_address_line_1' => '1 Test Street',
            'client_address_line_2' => 'Suite 100',
            'client_county' => 'Testshire',
            'client_postcode' => 'TS1 1ST',
            'seen_terms' => 1,
        ]);
    }

    public function test_timesheet_is_created_and_email_sent_on_same_day_cancellation()
    {
        Mail::fake();

        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        // Create agent (with user + agent role via factory)
        $agent = \factory(Agent::class)->create();

        // Create client
        $client = $this->createClientWithUser();

        // Create an interpreter job scheduled for today and assigned to the agent
        $job = \factory(InterpreterJob::class)->create([
            'appointment_date' => now()->toDateString(),
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 4, // assigned
        ]);

        // Cancel the job via the controller route
        $response = $this->post(route('interpreter-jobs.cancel', $job), [
            'message' => 'Cancelling for test',
        ]);

        $response->assertRedirect(route('interpreter-jobs.index'));
        $response->assertSessionHas('success');

        // Assert a timesheet exists linking the original assigned agent to the job
        $timesheet = Timesheet::where('job_id', $job->id)->first();
        $this->assertNotNull($timesheet, 'Timesheet should be created on same-day cancellation');
        $this->assertEquals($agent->id, (int) $timesheet->agent_id, 'Timesheet should be linked to the originally assigned agent');
        $this->assertEquals('Y', $timesheet->status, 'Timesheet should have status Y');

        // Assert the agent was emailed to sign the timesheet
        Mail::assertSent(JobMail::class, function ($mail) use ($job) {
            return $mail->view === 'emails.agents.job-timesheet-sign'
                && $mail->subject === 'Timesheet needs to be signed'
                && (is_object($mail->job) ? $mail->job->id : $mail->job['id']) === $job->id;
        });
    }

    public function test_admin_send_invoice_defaults_to_agent_entry_for_same_day_cancelled_job()
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $agent = \factory(Agent::class)->create();
        $client = $this->createClientWithUser();

        $job = \factory(InterpreterJob::class)->create([
            'appointment_date' => now()->toDateString(),
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 4,
        ]);

        $cancelResponse = $this->post(route('interpreter-jobs.cancel', $job), [
            'message' => 'Cancelling for invoice test',
        ]);
        $cancelResponse->assertRedirect(route('interpreter-jobs.index'));

        $timesheet = Timesheet::where('job_id', $job->id)->firstOrFail();
        $timesheet->agent_signature = 'signed';
        $timesheet->save();

        $entry = AgentJobEntry::create([
            'agent_id' => $agent->id,
            'job_id' => $job->id,
            'job_type' => 'App\\InterpreterJob',
            'base_amount' => 100.00,
            'expenses' => 15.00,
            'surcharge' => 0.00,
            'other_charges' => 0.00,
            'total_amount' => 115.00,
            'notes' => 'Test claim',
            'invoiced' => false,
        ]);
        AgentJobEntryExpense::create([
            'agent_job_entry_id' => $entry->id,
            'type' => 'Parking',
            'amount' => 10.00,
        ]);
        AgentJobEntryExpense::create([
            'agent_job_entry_id' => $entry->id,
            'type' => 'Travel',
            'amount' => 5.00,
        ]);

        $resp = $this->post(route('timesheet.send-invoice', $timesheet->id));
        $resp->assertRedirect();

        $invoice = ClientInvoice::orderByDesc('id')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($client->id, (int) $invoice->client_id);
        $this->assertEquals(115.00, (float) $invoice->subtotal);
        $this->assertEquals(23.00, (float) $invoice->vat_amount);
        $this->assertEquals(138.00, (float) $invoice->total);

        $item = ClientInvoiceItem::where('client_invoice_id', $invoice->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals($job->id, (int) $item->job_id);
        $this->assertEquals(100.00, (float) $item->amount);
        $this->assertEquals(5.00, (float) $item->other_charges);
        $this->assertTrue((bool) $item->vat_applicable);
        $this->assertEquals('timesheet_agent_entry', data_get($item->metadata, 'source'));
    }

    public function test_admin_can_send_invoice_for_same_day_cancelled_job_without_signatures()
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $agent = \factory(Agent::class)->create();
        $client = $this->createClientWithUser();

        $job = \factory(InterpreterJob::class)->create([
            'appointment_date' => now()->toDateString(),
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 4,
        ]);

        $cancelResponse = $this->post(route('interpreter-jobs.cancel', $job), [
            'message' => 'Cancelling for no-signature invoice test',
        ]);
        $cancelResponse->assertRedirect(route('interpreter-jobs.index'));

        $timesheet = Timesheet::where('job_id', $job->id)->firstOrFail();
        $this->assertEmpty($timesheet->agent_signature);

        AgentJobEntry::create([
            'agent_id' => $agent->id,
            'job_id' => $job->id,
            'job_type' => 'App\\InterpreterJob',
            'base_amount' => 90.00,
            'expenses' => 10.00,
            'surcharge' => 0.00,
            'other_charges' => 0.00,
            'total_amount' => 100.00,
            'notes' => 'Test claim (no signature)',
            'invoiced' => false,
        ]);

        $resp = $this->post(route('timesheet.send-invoice', $timesheet->id));
        $resp->assertRedirect();
        $resp->assertSessionMissing('failure');

        $invoice = ClientInvoice::orderByDesc('id')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($client->id, (int) $invoice->client_id);
        $this->assertEquals(100.00, (float) $invoice->subtotal);
    }

    public function test_admin_can_generate_payslip_for_agent_invoice_including_same_day_cancelled_claim()
    {
        $admin = $this->createAdminUser();
        $agent = \factory(Agent::class)->create();
        $client = $this->createClientWithUser();

        $job = \factory(InterpreterJob::class)->create([
            'appointment_date' => now()->toDateString(),
            'client_id' => $client->id,
            'agent_id' => $agent->id,
            'status' => 4,
        ]);

        $this->actingAs($admin);
        $cancelResponse = $this->post(route('interpreter-jobs.cancel', $job), [
            'message' => 'Cancelling for payslip test',
        ]);
        $cancelResponse->assertRedirect(route('interpreter-jobs.index'));

        AgentJobEntry::create([
            'agent_id' => $agent->id,
            'job_id' => $job->id,
            'job_type' => 'App\\InterpreterJob',
            'base_amount' => 80.00,
            'expenses' => 0.00,
            'surcharge' => 0.00,
            'other_charges' => 0.00,
            'total_amount' => 80.00,
            'notes' => 'Test claim for payslip',
            'invoiced' => false,
        ]);

        $this->actingAs($agent->user);
        $invResp = $this->post(route('invoices.agent.generate'), [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $invResp->assertRedirect();

        $agentInvoice = AgentInvoice::orderByDesc('id')->first();
        $this->assertNotNull($agentInvoice);
        $this->assertEquals($agent->id, (int) $agentInvoice->agent_id);

        $this->actingAs($admin);
        $resp = $this->postJson(route('invoices.admin.agent.generate-payslip', $agentInvoice->id), [
            'admin_total_amount' => (float) $agentInvoice->total_amount,
            'items' => [],
        ]);
        $resp->assertStatus(200);
        $resp->assertJson(['success' => true]);

        $payslip = Payslip::where('agent_invoice_id', $agentInvoice->id)->first();
        $this->assertNotNull($payslip);
        $this->assertEquals($agent->id, (int) $payslip->agent_id);
        $this->assertIsArray($payslip->items);
        $this->assertTrue(collect($payslip->items)->pluck('job_id')->contains((int) $job->id));
    }
}
