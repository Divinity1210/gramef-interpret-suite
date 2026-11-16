<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Agent;
use App\InterpreterJob;
use Illuminate\Support\Carbon;

class AgentScopeTest extends TestCase
{
    public function test_non_dbs_job_does_not_apply_dbs_filters()
    {
        $job = InterpreterJob::make([
            'security_type_id' => '4', // DBS not required (string to test casting)
            'to_language_id' => 1,
            'skill_id' => 1,
            'gender' => 2,
            'appointment_date' => Carbon::now()->format('Y-m-d'),
            'start_time' => '09:00:00',
        ]);

        $query = Agent::query()->canBeMatchedToInterpreterJobs($job);
        $wheres = $query->getQuery()->wheres ?? [];

        $columns = array_map(function ($where) {
            return $where['column'] ?? null;
        }, $wheres);

        // Should NOT have DBS-related filters
        $this->assertNotContains('dbs_expiry_date', $columns);
        $this->assertNotContains('dbs_number', $columns);
        $this->assertNotContains('induction_date', $columns);

        // Should include restrict_job_notifications filter
        $this->assertContains('restrict_job_notifications', $columns);
    }

    public function test_dbs_required_job_applies_dbs_filters()
    {
        $job = InterpreterJob::make([
            'security_type_id' => 1, // DBS required
            'to_language_id' => 1,
            'skill_id' => 1,
            'gender' => 2,
            'appointment_date' => Carbon::now()->format('Y-m-d'),
            'start_time' => '09:00:00',
        ]);

        $query = Agent::query()->canBeMatchedToInterpreterJobs($job);
        $wheres = $query->getQuery()->wheres ?? [];
        $columns = array_map(function ($where) {
            return $where['column'] ?? null;
        }, $wheres);

        // Should include DBS-related filters
        $this->assertContains('dbs_expiry_date', $columns);
        $this->assertContains('dbs_number', $columns);
        $this->assertContains('induction_date', $columns);

        // Should include restrict_job_notifications filter
        $this->assertContains('restrict_job_notifications', $columns);
    }
}