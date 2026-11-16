<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Agent;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SAFETY GUARANTEES:
 * ─────────────────
 * 1. ADDITIVE ONLY — uses assignRole() which only ADDS a role; never removes existing roles.
 * 2. TRANSACTION-WRAPPED — all changes are inside a DB transaction; any error rolls back everything.
 * 3. PRE-CHANGE SNAPSHOT — records every agent's current roles BEFORE any modification, logged to
 *    both console and Laravel log so you can manually revert if needed.
 * 4. DRY-RUN DEFAULT — if --execute is not passed, the command only reports; no DB writes occur.
 * 5. INTERACTIVE CONFIRMATION — even with --execute, prompts for explicit yes/no before committing.
 * 6. IDEMPOTENT — running it twice is safe; assignRole() is a no-op if the role already exists.
 */
class RestoreAgentRoles extends Command
{
    protected $signature = 'agents:restore-roles
        {--execute : Actually apply changes. Without this flag, the command is a safe dry-run.}';

    protected $description = 'ADDITIVE-ONLY: Restore missing interpreter-type roles for agents who only have the "agent" role. Safe dry-run by default.';

    // Skill names that indicate interpreting work
    private const INTERPRETER_SKILLS = [
        'Face to Face',
        'Telephone Interpreting',
        'Video Interpreting',
        'Consecutive',
    ];

    // Skill names that indicate translation work
    private const TRANSLATOR_SKILLS = [
        'Translation',
        'Transcription',
        'Text to Speech',
    ];

    public function handle()
    {
        $execute = $this->option('execute');

        $this->newLine();
        if (!$execute) {
            $this->info('═══════════════════════════════════════════════════════');
            $this->info('  🔍 DRY RUN — no database changes will be made.');
            $this->info('  Pass --execute to apply changes.');
            $this->info('═══════════════════════════════════════════════════════');
        } else {
            $this->warn('═══════════════════════════════════════════════════════');
            $this->warn('  ⚠️  LIVE MODE — changes will be written to the DB.');
            $this->warn('═══════════════════════════════════════════════════════');
        }
        $this->newLine();

        // ── Step 1: Find affected agents ──────────────────────────────────
        // Only agents with EXACTLY the 'agent' role and no interpreter-type role.
        $affectedAgents = DB::select("
            SELECT a.id AS agent_id, u.id AS user_id, u.first_name, u.last_name,
                   GROUP_CONCAT(DISTINCT r.name ORDER BY r.name) AS current_roles
            FROM agents a
            JOIN users u ON a.user_id = u.id
            JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\\\User'
            JOIN roles r ON mhr.role_id = r.id
            WHERE u.enabled = 1
            GROUP BY a.id, u.id, u.first_name, u.last_name
            HAVING current_roles = 'agent'
            ORDER BY a.id
        ");

        if (empty($affectedAgents)) {
            $this->info('✅ No affected agents found. All enabled agents have proper roles.');
            return 0;
        }

        $this->info('Found ' . count($affectedAgents) . " agent(s) with only the 'agent' role:");
        $this->newLine();

        // ── Step 2: Determine correct role for each agent ─────────────────
        $headers = ['Agent ID', 'User ID', 'Name', 'Current Roles', 'Skills', 'Will Add Role'];
        $rows = [];
        $restorations = [];

        foreach ($affectedAgents as $agent) {
            $skills = DB::table('agent_skill')
                ->join('skills', 'skills.id', '=', 'agent_skill.skill_id')
                ->where('agent_skill.agent_id', $agent->agent_id)
                ->pluck('skills.skill')
                ->toArray();

            $hasInterpreterSkills = !empty(array_intersect($skills, self::INTERPRETER_SKILLS));
            $hasTranslatorSkills = !empty(array_intersect($skills, self::TRANSLATOR_SKILLS));

            $addRole = null;
            if ($hasInterpreterSkills) {
                $addRole = 'community-interpreter';
            } elseif ($hasTranslatorSkills) {
                $addRole = 'qualified-translator';
            }

            $rows[] = [
                $agent->agent_id,
                $agent->user_id,
                $agent->first_name . ' ' . $agent->last_name,
                $agent->current_roles,
                implode(', ', $skills) ?: '(none)',
                $addRole ?? '⚠️ SKIP — manual review',
            ];

            if ($addRole) {
                $restorations[] = [
                    'user_id'  => $agent->user_id,
                    'agent_id' => $agent->agent_id,
                    'name'     => $agent->first_name . ' ' . $agent->last_name,
                    'current'  => $agent->current_roles,
                    'add_role' => $addRole,
                ];
            }
        }

        $this->table($headers, $rows);
        $this->newLine();

        if (empty($restorations)) {
            $this->warn('No agents could be automatically assigned a role. Manual review needed.');
            return 1;
        }

        // ── Dry-run exit ──────────────────────────────────────────────────
        if (!$execute) {
            $this->info("🔍 DRY RUN complete. " . count($restorations) . ' agent(s) would receive an additional role.');
            $this->info('   Re-run with --execute to apply.');
            return 0;
        }

        // ── Step 3: Interactive confirmation ──────────────────────────────
        $this->warn('The following ADDITIVE changes will be made (no roles will be removed):');
        foreach ($restorations as $r) {
            $this->line("  Agent #{$r['agent_id']} ({$r['name']}): ADD role '{$r['add_role']}'  (keeps: {$r['current']})");
        }
        $this->newLine();

        if (!$this->confirm('Proceed with adding these roles? This is ADDITIVE only — no existing data will be removed.')) {
            $this->info('Cancelled. No changes made.');
            return 0;
        }

        // ── Step 4: Record pre-change snapshot ───────────────────────────
        $this->info('📸 Recording pre-change snapshot...');
        $snapshot = [];
        foreach ($restorations as $r) {
            $currentRoles = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $r['user_id'])
                ->where('model_has_roles.model_type', 'App\\User')
                ->pluck('roles.name')
                ->toArray();

            $snapshot[] = [
                'agent_id' => $r['agent_id'],
                'user_id'  => $r['user_id'],
                'name'     => $r['name'],
                'roles_before' => $currentRoles,
            ];

            $logMsg = sprintf(
                '[RestoreAgentRoles] SNAPSHOT agent=#%d user=#%d name="%s" roles_before=[%s]',
                $r['agent_id'], $r['user_id'], $r['name'], implode(', ', $currentRoles)
            );
            Log::info($logMsg);
            $this->line('  ' . $logMsg);
        }
        $this->newLine();

        // ── Step 5: Apply changes inside a transaction ───────────────────
        $this->info('🔒 Applying changes inside DB transaction...');

        try {
            DB::beginTransaction();

            $restored = 0;
            foreach ($restorations as $r) {
                $user = User::find($r['user_id']);
                if (!$user) {
                    throw new \RuntimeException("User #{$r['user_id']} not found — aborting entire operation.");
                }

                // assignRole() is ADDITIVE — it only inserts if the role doesn't exist.
                // It never deletes existing roles. This is the safest Spatie method.
                $user->assignRole($r['add_role']);
                $restored++;

                $msg = "ADDED role '{$r['add_role']}' to agent #{$r['agent_id']} ({$r['name']})";
                Log::info('[RestoreAgentRoles] ' . $msg);
                $this->info("  ✅ {$msg}");
            }

            DB::commit();

            $this->newLine();
            $this->info("✅ Transaction committed. Restored roles for {$restored} agent(s).");
            Log::info("[RestoreAgentRoles] COMMITTED: restored {$restored} agents");

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error("❌ ERROR — transaction rolled back. No changes were saved.");
            $this->error("   Reason: " . $e->getMessage());
            Log::error('[RestoreAgentRoles] ROLLED BACK: ' . $e->getMessage());
            return 1;
        }

        // ── Step 6: Post-change verification ─────────────────────────────
        $this->newLine();
        $this->info('🔎 Post-change verification:');
        foreach ($snapshot as $s) {
            $newRoles = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $s['user_id'])
                ->where('model_has_roles.model_type', 'App\\User')
                ->pluck('roles.name')
                ->toArray();

            $before = implode(', ', $s['roles_before']);
            $after  = implode(', ', $newRoles);
            $this->line("  Agent #{$s['agent_id']}: [{$before}] → [{$after}]");
        }

        return 0;
    }
}
