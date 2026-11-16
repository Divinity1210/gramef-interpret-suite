<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use App\User;
use App\Client;
use App\Agent;
use App\InterpreterJob;
use App\AgentJobEntry;

class AgentJobEntryAuthorizationFallbackTest extends TestCase
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

    public function test_agent_can_access_job_entry_due_to_same_day_cancelled_timesheet_fallback()
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        // Create agent (with linked user assigned the 'agent' role by factory)
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

        // Cancel the job via the controller route — this creates a timesheet linking the agent
        $cancelResponse = $this->post(route('interpreter-jobs.cancel', $job), [
            'message' => 'Cancelled due to test',
        ]);
        $cancelResponse->assertRedirect(route('interpreter-jobs.index'));

        // Act as the agent's user
        $this->actingAs($agent->user);

        // Access the agent job entry create page using fallback authorization
        $getResponse = $this->get(route('agent.job-entry.create', [
            'job_type' => 'interpreter',
            'job_id' => $job->id,
        ]));
        $getResponse->assertStatus(200);
        $getResponse->assertSee('Enter Job Amounts');

        // Submit minimal valid entry data
        $postResponse = $this->post(route('agent.job-entry.store', [
            'job_type' => 'interpreter',
            'job_id' => $job->id,
        ]), [
            'base_amount' => '100.00',
            // Leave expenses empty to rely on computed 0
            'other_charges' => '0.00',
            'notes' => 'Automated test submission',
        ]);
        $postResponse->assertRedirect(route('agent.job-entry.pending'));
        $postResponse->assertSessionHas('success');

        // Verify the entry persisted and is pending (not invoiced)
        $this->assertDatabaseHas('agent_job_entries', [
            'agent_id' => $agent->id,
            'job_id' => $job->id,
            'job_type' => 'App\\InterpreterJob',
            'base_amount' => 100.00,
            'expenses' => 0.00,
            'surcharge' => 0.00,
            'other_charges' => 0.00,
            'total_amount' => 100.00,
            'invoiced' => 0,
        ]);

        // Ensure only one entry exists for this agent/job pair
        $count = AgentJobEntry::where([
            'agent_id' => $agent->id,
            'job_id' => $job->id,
            'job_type' => 'App\\InterpreterJob',
        ])->count();
        $this->assertEquals(1, $count);
    }
}