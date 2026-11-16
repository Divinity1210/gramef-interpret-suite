<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\User;
use App\Agent;
use App\AgentJobEntry;
use App\AgentInvoice;

class AgentInvoiceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_entries_all_appear_in_generated_invoice(): void
    {
        $user = factory(User::class)->create();
        $user->assignRole('agent');
        $agent = factory(Agent::class)->create(['user_id' => $user->id]);

        $e1 = AgentJobEntry::create([
            'agent_id' => $agent->id,
            'job_id' => 101,
            'job_type' => 'App\\InterpreterJob',
            'base_amount' => 100.00,
            'expenses' => 10.00,
            'surcharge' => 0.00,
            'other_charges' => 0.00,
            'total_amount' => 110.00,
            'notes' => 'Entry 1',
            'invoiced' => false,
        ]);
        $e2 = AgentJobEntry::create([
            'agent_id' => $agent->id,
            'job_id' => 102,
            'job_type' => 'App\\TranslatorJob',
            'base_amount' => 200.00,
            'expenses' => 0.00,
            'surcharge' => 0.00,
            'other_charges' => 5.00,
            'total_amount' => 205.00,
            'notes' => 'Entry 2',
            'invoiced' => false,
        ]);
        $e3 = AgentJobEntry::create([
            'agent_id' => $agent->id,
            'job_id' => 103,
            'job_type' => 'App\\InterpreterJob',
            'base_amount' => 150.00,
            'expenses' => 20.00,
            'surcharge' => 0.00,
            'other_charges' => 0.00,
            'total_amount' => 170.00,
            'notes' => 'Entry 3',
            'invoiced' => false,
        ]);

        $this->actingAs($user);

        $payload = [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'entry_ids' => [$e1->id, $e2->id, $e3->id],
        ];

        $resp = $this->post(route('invoices.agent.generate'), $payload);
        $resp->assertRedirect(route('invoices.agent.index'));

        $invoice = AgentInvoice::where('agent_id', $agent->id)->orderByDesc('id')->first();
        $this->assertNotNull($invoice);
        $items = $invoice->items()->get();
        $this->assertCount(3, $items);
        $this->assertTrue($items->pluck('job_id')->contains(101));
        $this->assertTrue($items->pluck('job_id')->contains(102));
        $this->assertTrue($items->pluck('job_id')->contains(103));
    }
}
