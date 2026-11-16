<?php

namespace App\Console\Commands;

use App\Mail\DBSMail;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RequestAgentsUpdateDBS extends Command
{
    protected $signature = 'agents:request-dbs-update {--dry-run : Preview recipients without sending emails}';

    protected $description = 'Email all interpreters to update their DBS details';

    public function handle()
    {
        $users = User::query()
            ->where('enabled', true)
            ->whereHas('agent')
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['agent', 'new-agent']);
            })
            ->with('agent.user')
            ->get();

        $count = $users->count();
        $this->info("Found {$count} interpreter users");

        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            foreach ($users as $user) {
                $this->line("DRY-RUN: would email {$user->email} (user_id={$user->id})");
            }
            return 0;
        }

        $sent = 0;
        foreach ($users as $user) {
            try {
                Mail::to($user)->send(new DBSMail(
                    $user->agent,
                    'emails.agents.dbs-update',
                    'Please update your DBS details'
                ));
                $sent++;
                Log::info('DBS update email sent', ['user_id' => $user->id]);
            } catch (\Throwable $e) {
                $this->warn("Failed to email {$user->email}: " . $e->getMessage());
                Log::warning('DBS update email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Emails sent: {$sent}");
        return 0;
    }
}

