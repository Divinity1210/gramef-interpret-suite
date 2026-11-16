<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use App\User;
use App\Client;
use App\Agent;
use App\InterpreterJob;
use App\Language;
use App\Skill;
use App\Timesheet;
use Illuminate\Support\Facades\DB;

class TimesheetPdfAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure tests use SQLite file database
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', database_path('testing.sqlite'));
        config()->set('database.connections.sqlite.foreign_key_constraints', true);

        // Run base migrations
        Artisan::call('migrate', ['--force' => true]);

        // Force timesheets table to use signatures schema with job_id FK
        Schema::dropIfExists('timesheets');
        // Re-enable the migrations for timesheets by removing their entries so we can rerun with specific schema
        DB::table('migrations')->whereIn('migration', [
            '2024_07_20_121006_create_timesheets_with_languages_table',
            '2025_10_30_120000_add_missing_timesheet_signature_and_job_columns',
            '2024_08_25_145426_add_columns_to_timesheets',
        ])->delete();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2024_07_20_121006_create_timesheets_with_languages_table.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2025_10_30_120000_add_missing_timesheet_signature_and_job_columns.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2024_08_25_145426_add_columns_to_timesheets.php',
            '--force' => true,
        ]);
    }

    protected function makeAdmin(): User
    {
        $admin = factory(User::class)->create();
        $admin->assignRole('admin');
        return $admin;
    }

    protected function makeClient(): Client
    {
        $clientUser = factory(User::class)->create();
        $clientUser->assignRole('client');
        return factory(Client::class)->create(['user_id' => $clientUser->id]);
    }

    protected function makeLanguage(): Language
    {
        return Language::create(['name' => 'AuthLang']);
    }

    protected function makeSkill(): Skill
    {
        return Skill::create(['skill' => 'AuthSkill', 'type' => 0]);
    }

    protected function makeAgent(): Agent
    {
        $agent = factory(Agent::class)->create();
        $agent->user->assignRole('agent');
        return $agent;
    }

    protected function makeJob(Client $client, Language $language, Skill $skill): InterpreterJob
    {
        return factory(InterpreterJob::class)->create([
            'client_id' => $client->id,
            'to_language_id' => $language->id,
            'skill_id' => $skill->id,
            'status' => 0,
            'appointment_date' => now()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);
    }

    protected function createTimesheetForJob(InterpreterJob $job, ?Agent $agent = null): Timesheet
    {
        return Timesheet::create([
            'job_id' => $job->id,
            'agent_id' => optional($agent)->id,
            'client_id' => $job->client_id,
            'from_language_id' => $job->from_language_id ?? $job->to_language_id,
            'to_language_id' => $job->to_language_id,
            'client_reference' => $job->client_reference ?? '',
            'appointment_date' => $job->appointment_date,
            'start_time' => $job->start_time,
            'end_time' => $job->end_time,
            'duration_hours' => $job->duration_hours,
            'duration_minutes' => $job->duration_minutes,
            'agent_duration_hours' => 1,
            'agent_duration_minutes' => 0,
            'client_name' => optional(optional($job->client)->user)->getFullName(),
            'status' => 'Y',
        ]);
    }

    public function test_admin_can_view_timesheet_pdf()
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();
        $lang = $this->makeLanguage();
        $skill = $this->makeSkill();
        $job = $this->makeJob($client, $lang, $skill);
        $agent = $this->makeAgent();
        $ts = $this->createTimesheetForJob($job, $agent);

        $this->actingAs($admin);
        $resp = $this->get(route('timesheets.pdf', $ts));
        $resp->assertStatus(200);
        $resp->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_assigned_agent_can_view_timesheet_pdf()
    {
        $client = $this->makeClient();
        $lang = $this->makeLanguage();
        $skill = $this->makeSkill();
        $job = $this->makeJob($client, $lang, $skill);
        $agent = $this->makeAgent();
        $ts = $this->createTimesheetForJob($job, $agent);

        $this->actingAs($agent->user);
        $resp = $this->get(route('timesheets.pdf', $ts));
        $resp->assertStatus(200);
        $resp->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_job_client_user_can_view_timesheet_pdf()
    {
        $client = $this->makeClient();
        $lang = $this->makeLanguage();
        $skill = $this->makeSkill();
        $job = $this->makeJob($client, $lang, $skill);
        $agent = $this->makeAgent();
        $ts = $this->createTimesheetForJob($job, $agent);

        $this->actingAs($client->user);
        $resp = $this->get(route('timesheets.pdf', $ts));
        $resp->assertStatus(200);
        $resp->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unrelated_user_gets_403()
    {
        $client = $this->makeClient();
        $lang = $this->makeLanguage();
        $skill = $this->makeSkill();
        $job = $this->makeJob($client, $lang, $skill);
        $agent = $this->makeAgent();
        $ts = $this->createTimesheetForJob($job, $agent);

        $stranger = factory(User::class)->create();
        $this->actingAs($stranger);
        $resp = $this->get(route('timesheets.pdf', $ts));
        $resp->assertStatus(403);
    }
}
