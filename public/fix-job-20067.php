<?php
/**
 * One-time fix for jobs with rejected-agent status mismatch.
 * Resets jobs where agent rejected but job table still shows "Assigned".
 * 
 * Upload to public/ folder and visit in browser. DELETE AFTER USE.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

echo "<pre style='font-family:monospace; background:#1a1a2e; color:#e0e0e0; padding:20px; border-radius:8px;'>";
echo "<h2 style='color:#4fc3f7;'>🔧 Fix Rejected-Agent Job Status Mismatch</h2>\n";

$jobIds = [20084];

foreach ($jobIds as $jobId) {
    echo "\n<strong style='color:#81d4fa;'>--- Job #{$jobId} ---</strong>\n";
    try {
        $job = DB::table('interpreter_jobs')->where('id', $jobId)->first();
        if (!$job) {
            echo "  <span style='color:#ffab91;'>Job not found, skipping.</span>\n";
            continue;
        }

        echo "  Current state: status={$job->status}, agent_id=" . ($job->agent_id ?? 'NULL') . "\n";

        // Check matched_agents
        $matchedAgents = DB::table('matched_agents')
            ->where('job_id', $jobId)
            ->where('job_type', 'App\\InterpreterJob')
            ->get();

        $statusMap = [0 => 'matched', 1 => 'rejected', 2 => 'accepted', 3 => 'cancelled', 4 => 'assigned'];
        foreach ($matchedAgents as $ma) {
            $statusName = $statusMap[$ma->status] ?? "unknown({$ma->status})";
            echo "  Matched Agent #{$ma->agent_id}: status={$ma->status} ({$statusName})\n";
        }

        // Check if job is still assigned but the agent rejected
        $assignedAgentId = $job->agent_id;
        if ($assignedAgentId && $job->status == 1) {
            $agentRejected = DB::table('matched_agents')
                ->where('job_id', $jobId)
                ->where('job_type', 'App\\InterpreterJob')
                ->where('agent_id', $assignedAgentId)
                ->whereIn('status', [1, 3]) // rejected or cancelled in matched_agents
                ->exists();

            if ($agentRejected) {
                DB::table('interpreter_jobs')
                    ->where('id', $jobId)
                    ->update(['agent_id' => null, 'status' => 0]);
                echo "  <strong style='color:#66bb6a;'>✅ Fixed: Reset to OPEN (status=0, agent_id=null)</strong>\n";
            } else {
                echo "  <span style='color:#fff59d;'>⚠️  Agent is still assigned and hasn't rejected. No fix needed.</span>\n";
            }
        } elseif ($job->status == 0 && !$job->agent_id) {
            echo "  <span style='color:#a5d6a7;'>Already open/unassigned. No fix needed.</span>\n";
        } else {
            echo "  Status={$job->status}, agent_id=" . ($job->agent_id ?? 'NULL') . " — reviewing manually may be needed.\n";
        }
    } catch (\Throwable $e) {
        echo "  <strong style='color:#ef5350;'>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
    }
}

echo "\n<span style='color:#ef5350;'>⚠️  DELETE this file from your server now.</span>\n";
echo "</pre>";
