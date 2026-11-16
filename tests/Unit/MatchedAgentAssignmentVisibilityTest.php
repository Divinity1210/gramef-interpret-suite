<?php

namespace Tests\Unit;

use App\MatchedAgent;
use Tests\TestCase;

class MatchedAgentAssignmentVisibilityTest extends TestCase
{
    public function test_assign_is_hidden_when_job_has_direct_assigned_agent()
    {
        $matchedAgent = new MatchedAgent();
        $matchedAgent->id = 10;
        $matchedAgent->job_type = 'App\InterpreterJob';
        $matchedAgent->setRelation('job', $this->makeJobStub(55, null, false, false));

        $this->assertFalse($matchedAgent->canBeAssigned());
    }

    public function test_assign_is_hidden_when_job_has_assigned_matched_record()
    {
        $matchedAgent = new MatchedAgent();
        $matchedAgent->id = 10;
        $matchedAgent->job_type = 'App\InterpreterJob';
        $matchedAgent->setRelation('job', $this->makeJobStub(null, (object) ['id' => 77], false, false));

        $this->assertFalse($matchedAgent->canBeAssigned());
    }

    public function test_assign_is_visible_after_unassignment_for_non_quote_clients()
    {
        $matchedAgent = new MatchedAgent();
        $matchedAgent->id = 10;
        $matchedAgent->job_type = 'App\InterpreterJob';
        $matchedAgent->setRelation('job', $this->makeJobStub(null, null, false, false));

        $this->assertTrue($matchedAgent->canBeAssigned());
    }

    public function test_assign_is_visible_for_quote_clients_only_with_accepted_admin_quote()
    {
        $matchedAgent = new MatchedAgent();
        $matchedAgent->id = 10;
        $matchedAgent->job_type = 'App\InterpreterJob';
        $matchedAgent->setRelation('job', $this->makeJobStub(null, null, true, true));

        $this->assertTrue($matchedAgent->canBeAssigned());
    }

    private function makeJobStub($agentId, $assignedMatched, bool $alwaysRequiresQuote, bool $hasAcceptedAdminQuote): object
    {
        return new class($agentId, $assignedMatched, $alwaysRequiresQuote, $hasAcceptedAdminQuote) {
            public $agent_id;
            public $assignedMatched;
            public $client;
            private $hasAcceptedAdminQuote;

            public function __construct($agentId, $assignedMatched, bool $alwaysRequiresQuote, bool $hasAcceptedAdminQuote)
            {
                $this->agent_id = $agentId;
                $this->assignedMatched = $assignedMatched;
                $this->client = (object) ['always_requires_a_quote' => $alwaysRequiresQuote];
                $this->hasAcceptedAdminQuote = $hasAcceptedAdminQuote;
            }

            public function adminQuotes()
            {
                return new class($this->hasAcceptedAdminQuote) {
                    private $hasAcceptedAdminQuote;

                    public function __construct(bool $hasAcceptedAdminQuote)
                    {
                        $this->hasAcceptedAdminQuote = $hasAcceptedAdminQuote;
                    }

                    public function where($column, $value)
                    {
                        return $this;
                    }

                    public function exists(): bool
                    {
                        return $this->hasAcceptedAdminQuote;
                    }
                };
            }
        };
    }
}
