<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use App\User;
use App\Client;
use App\InterpreterJob;
use App\TranslatorJob;

class AdminQuoteCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force' => true]);
    }

    private function makeAdmin(): User
    {
        $admin = factory(User::class)->create();
        $admin->assignRole('admin');
        return $admin;
    }

    public function test_admin_can_create_translator_job_quote_without_error()
    {
        $admin = $this->makeAdmin();
        $clientUser = factory(User::class)->create();
        $clientUser->assignRole('client');
        $client = factory(Client::class)->create(['user_id' => $clientUser->id]);

        $job = factory(TranslatorJob::class)->create([
            'client_id' => $client->id,
            'status' => 0,
        ]);

        $this->actingAs($admin);

        $resp = $this->post(route('translator-jobs.quotes.store', $job), [
            'cost_description' => 'Admin test quote',
            'cost' => 99.99,
        ]);

        $resp->assertStatus(302);
        $this->assertTrue($job->adminQuotes()->exists());
    }

    public function test_admin_translator_quote_cost_rounds_to_two_decimals()
    {
        $admin = $this->makeAdmin();
        $clientUser = factory(User::class)->create();
        $clientUser->assignRole('client');
        $client = factory(Client::class)->create(['user_id' => $clientUser->id]);

        $job = factory(TranslatorJob::class)->create([
            'client_id' => $client->id,
            'status' => 0,
        ]);

        $this->actingAs($admin);

        $resp = $this->post(route('translator-jobs.quotes.store', $job), [
            'cost_description' => 'Admin test quote rounding',
            'cost' => 12.345,
        ]);

        $resp->assertStatus(302);
        $quote = $job->adminQuotes()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEquals(12.35, (float) $quote->cost);
    }

    public function test_admin_can_create_interpreter_job_quote_without_error()
    {
        $admin = $this->makeAdmin();
        $clientUser = factory(User::class)->create();
        $clientUser->assignRole('client');
        $client = factory(Client::class)->create([
            'user_id' => $clientUser->id,
            'always_requires_a_quote' => true,
        ]);

        $job = factory(InterpreterJob::class)->create([
            'client_id' => $client->id,
            'status' => 0,
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);

        $this->actingAs($admin);

        $resp = $this->post(route('interpreter-jobs.quotes.store', $job), [
            'interpreting_cost' => 10,
            'travel_time' => 1,
            'travel_cost' => 5,
            'mileage_miles' => 2,
            'mileage_cost' => 0.5,
            'cost_description' => 'Parking',
            'cost' => 3,
        ]);

        $resp->assertStatus(302);
        $this->assertTrue($job->adminQuotes()->exists());
    }

    public function test_admin_interpreter_quote_cost_rounds_to_two_decimals()
    {
        $admin = $this->makeAdmin();
        $clientUser = factory(User::class)->create();
        $clientUser->assignRole('client');
        $client = factory(Client::class)->create([
            'user_id' => $clientUser->id,
            'always_requires_a_quote' => true,
        ]);

        $job = factory(InterpreterJob::class)->create([
            'client_id' => $client->id,
            'status' => 0,
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);

        $this->actingAs($admin);

        $resp = $this->post(route('interpreter-jobs.quotes.store', $job), [
            'interpreting_cost' => 10.129,
            'travel_time' => 1.111,
            'travel_cost' => 5.555,
            'mileage_miles' => 2.999,
            'mileage_cost' => 0.333,
            'cost_description' => 'Parking',
            'cost' => 3.333,
        ]);

        $resp->assertStatus(302);
        $quote = $job->adminQuotes()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertEquals(3.33, (float) $quote->cost);
    }
}
