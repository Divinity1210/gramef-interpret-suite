<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use App\Client;
use App\Agent;
use App\User;
use App\InterpreterJob;
use App\TranslatorJob;
use App\Quote;
use App\AdminQuote;
use Spatie\Permission\Models\Role;

class QuoteTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Artisan::call('migrate', ['--force' => true]);
    }

    public function test_admin_quote_totals_for_interpreter_and_translator()
    {
        $user = factory(User::class)->create();
        $client = factory(Client::class)->create(['user_id' => $user->id]);
        $agent = factory(Agent::class)->create();

        $interpreterJob = factory(InterpreterJob::class)->create([
            'client_id' => $client->id,
            'duration_hours' => 2,
            'duration_minutes' => 30,
        ]);

        $translatorJob = factory(TranslatorJob::class)->create([
            'client_id' => $client->id,
        ]);

        $iq = new AdminQuote([
            'interpreting_cost' => 10.0,
            'travel_time' => 2.0,
            'travel_cost' => 5.0,
            'mileage_miles' => 10.0,
            'mileage_cost' => 0.5,
            'cost_description' => 'Other',
            'cost' => 20.0,
        ]);
        $iq->setRelation('job', $interpreterJob);

        $tq = new AdminQuote([
            'cost_description' => 'Translator cost',
            'cost' => 35.75,
        ]);
        $tq->setRelation('job', $translatorJob);

        $expectedInterpreter = '£' . number_format((10.0 * 2.5) + (2.0 * 5.0) + (10.0 * 0.5) + 20.0, 2);
        $expectedTranslator = '£' . number_format(35.75, 2);

        $this->assertEquals($expectedInterpreter, $iq->totalAmount);
        $this->assertEquals($expectedTranslator, $tq->totalAmount);
    }

    public function test_agent_quote_totals_for_interpreter_and_translator()
    {
        $user = factory(User::class)->create();
        $client = factory(Client::class)->create(['user_id' => $user->id]);
        $agent = factory(Agent::class)->create();

        $interpreterJob = factory(InterpreterJob::class)->create([
            'client_id' => $client->id,
            'duration_hours' => 1,
            'duration_minutes' => 0,
        ]);
        $interpreterMatched = $interpreterJob->matchedAgents()->create([
            'agent_id' => $agent->id,
        ]);

        $translatorJob = factory(TranslatorJob::class)->create([
            'client_id' => $client->id,
        ]);
        $translatorMatched = $translatorJob->matchedAgents()->create([
            'agent_id' => $agent->id,
        ]);

        $iq = new Quote([
            'interpreting_cost' => 12.0,
            'travel_time' => 1.5,
            'travel_cost' => 4.0,
            'mileage_miles' => 6.0,
            'mileage_cost' => 0.5,
            'cost_description' => 'Other',
            'cost' => 10.0,
        ]);
        $iq->setRelation('matchedAgent', $interpreterMatched);

        $tq = new Quote([
            'cost_description' => 'Translator cost',
            'cost' => 50.0,
        ]);
        $tq->setRelation('matchedAgent', $translatorMatched);

        $expectedInterpreter = '£' . number_format((12.0 * 1.0) + (1.5 * 4.0) + (6.0 * 0.5) + 10.0, 2);
        $expectedTranslator = '£' . number_format(50.0, 2);

        $this->assertEquals($expectedInterpreter, $iq->totalAmount);
        $this->assertEquals($expectedTranslator, $tq->totalAmount);
    }
}
