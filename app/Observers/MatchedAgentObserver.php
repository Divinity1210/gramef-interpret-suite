<?php

namespace App\Observers;

use App\MatchedAgent;
use App\Mail\MatchedAgentMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class MatchedAgentObserver
{
    public function created(MatchedAgent $matchedAgent)
    {
        try {
            $user = optional($matchedAgent->agent)->user;
            $email = optional($user)->email;
            if ($user && $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($user)->send(new MatchedAgentMail($matchedAgent));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to notify matched agent', [
                'matched_agent_id' => $matchedAgent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}