<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use App\User;
use App\Client;
use App\Agent;
use App\InterpreterJob;
use App\Language;
use App\Skill;
use App\MatchedAgent;
use App\JobSnapshot;
use App\Timesheet;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class AppointmentSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Run migrations before each test to ensure schema is present
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function makeAdmin(): User
    {
        /** @var User $admin */
        $admin = factory(User::class)->create();
        $admin->assignRole('admin');
        return $admin;
    }

    protected function makeClient(): Client
    {
        $clientUser = factory(User::class)->create();
        $clientUser->assignRole('client');
        return factory(Client::class)->create([
            'user_id' => $clientUser->id,
        ]);
    }

    protected function makeLanguage(): Language
    {
        return Language::create(['name' => 'TestLang']);
    }

    protected function makeSkill(): Skill
    {
        return Skill::create(['skill' => 'TestSkill', 'type' => 0]);
    }

    protected function makeJob(Client $client, Language $language, Skill $skill): InterpreterJob
    {
        // Use neutral values to avoid strict matching filters:
        return factory(InterpreterJob::class)->create([
            'client_id' => $client->id,
            'to_language_id' => $language->id,
            'skill_id' => $skill->id,
            'security_type_id' => 4, // no DBS requirement
            'require_qualified' => 0, // no strict role requirement
            'gender' => 2, // no gender filter
            'status' => 0,
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);
    }

    protected function makeMatchableAgent(Language $language, Skill $skill): Agent
    {
        /** @var Agent $agent */
        $agent = factory(Agent::class)->create([
            'restrict_job_notifications' => false,
        ]);
        // Ensure the created agent has the correct role for interpreter jobs
        $agent->user->assignRole('agent');
        $agent->languages()->sync([$language->id]);
        $agent->skills()->sync([$skill->id]);
        return $agent;
    }

    public function test_admin_assignment_creates_snapshot_and_timesheet()
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();
        $language = $this->makeLanguage();
        $skill = $this->makeSkill();
        $job = $this->makeJob($client, $language, $skill);
        $agent = $this->makeMatchableAgent($language, $skill);

        // Create a matched agent record
        /** @var MatchedAgent $matched */
        $matched = $job->matchedAgents()->create([
            'agent_id' => $agent->id,
            'status' => 0,
            'distance' => 0,
        ]);

        $this->actingAs($admin);

        // Admin assigns the agent via matched.update
        $response = $this->put(route('interpreter-jobs.matched.update', [$job->id, $matched->id]), [
            'status' => 4, // assigned
        ]);
        $response->assertStatus(302); // redirect back with success

        // Timesheet should exist
        $timesheet = Timesheet::where('job_id', $job->id)->first();
        $this->assertNotNull($timesheet, 'Timesheet record should be created on assignment');

        // Snapshot should exist and reference the timesheet
        $snapshot = JobSnapshot::where('job_id', $job->id)->latest('version')->first();
        $this->assertNotNull($snapshot, 'Job snapshot should be created on assignment');
        $this->assertSame(1, $snapshot->version, 'First snapshot should have version 1');
        $this->assertSame($timesheet->id, $snapshot->timesheet_id, 'Snapshot should be linked to timesheet');
        $this->assertSame('assignment', $snapshot->source, 'Snapshot should record source as assignment');
        $this->assertSame($admin->id, $snapshot->created_by, 'Snapshot should record admin as creator');
        $this->assertTrue(Str::length($snapshot->hash) >= 40, 'Snapshot hash should be present');

        // Appointment PDF should be downloadable
        $download = $this->get(route('interpreter-jobs.appointment-pdf', $job));
        $download->assertStatus(200);
        $download->assertHeader('Content-Type', 'application/pdf');

        // Cached PDF file path should exist per version
        $expectedPdf = storage_path('app/public/appointments/appointment-' . $job->id . '-v' . $snapshot->version . '.pdf');
        $this->assertFileExists($expectedPdf);
    }

    public function test_agent_acceptance_creates_snapshot()
    {
        $client = $this->makeClient();
        $language = $this->makeLanguage();
        $skill = $this->makeSkill();
        $job = $this->makeJob($client, $language, $skill);
        $agent = $this->makeMatchableAgent($language, $skill);

        // Create matched record
        $matched = $job->matchedAgents()->create([
            'agent_id' => $agent->id,
            'status' => 0,
            'distance' => 0,
        ]);

        $this->actingAs($agent->user);

        // Agent accepts via matched.update
        $resp = $this->put(route('interpreter-jobs.matched.update', [$job->id, $matched->id]), [
            'status' => 2, // accepted by agent
        ]);
        $resp->assertStatus(302);

        $timesheet = Timesheet::where('job_id', $job->id)->first();
        $this->assertNotNull($timesheet, 'Timesheet should be created/updated when agent accepts');

        $snapshot = JobSnapshot::where('job_id', $job->id)->latest('version')->first();
        $this->assertNotNull($snapshot, 'Snapshot should be created when agent accepts');
        $this->assertSame(1, $snapshot->version);
        $this->assertSame('accept', $snapshot->source);
        $this->assertSame($agent->user->id, $snapshot->created_by);
    }

    public function test_appointment_pdf_version_selection_and_regen()
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();
        $language = $this->makeLanguage();
        $skill = $this->makeSkill();
        $job = $this->makeJob($client, $language, $skill);

        $this->actingAs($admin);

        // First render creates snapshot v1
        $first = $this->get(route('interpreter-jobs.appointment-pdf', $job));
        $first->assertStatus(200);
        $first->assertHeader('Content-Type', 'application/pdf');
        $snap1 = JobSnapshot::where('job_id', $job->id)->latest('version')->first();
        $this->assertSame(1, $snap1->version);
        $pdfV1 = storage_path('app/public/appointments/appointment-' . $job->id . '-v1.pdf');
        $this->assertFileExists($pdfV1);

        // Regenerate snapshot (v2)
        $second = $this->get(route('interpreter-jobs.appointment-pdf', [$job->id]) . '?regen=1');
        $second->assertStatus(200);
        $snap2 = JobSnapshot::where('job_id', $job->id)->latest('version')->first();
        $this->assertSame(2, $snap2->version);
        $pdfV2 = storage_path('app/public/appointments/appointment-' . $job->id . '-v2.pdf');
        $this->assertFileExists($pdfV2);

        // Explicitly request v1
        $third = $this->get(route('interpreter-jobs.appointment-pdf', [$job->id]) . '?version=1');
        $third->assertStatus(200);
        $third->assertHeader('Content-Type', 'application/pdf');
        $this->assertFileExists($pdfV1);
        $this->assertFileExists($pdfV2);
    }
}