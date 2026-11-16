<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Helpers\RoleHelper;

class Job extends Model
{
    public function matchedAgents()
    {
        return $this->morphMany('App\\MatchedAgent', 'job');
    }

    public function quotedMatchedAgents()
    {
        return $this->matchedAgents()->whereIn('status', [2, 4])->has('quotes');
    }

    public function assignedMatched()
    {
        return $this->morphOne('App\\MatchedAgent', 'job')->where('status', 4);
        //print_r($var->toSql());
        //print_r($var->getBindings());
        //exit;
    }

    public function skill()
    {
        return $this->belongsTo('App\\Skill', 'skill_id');
    }

    public function agent()
    {
        return $this->belongsTo('App\\Agent');
    }

    public function client()
    {
        return $this->belongsTo('App\\Client');
    }

    public function toLanguage()
    {
        return $this->belongsTo('App\\Language', 'to_language_id')->withTrashed();
    }

    public function cancellation()
    {
        return $this->morphOne('App\\Cancellation', 'job');
    }

    public function getReferenceAttribute()
    {
        return "$this->id/atoz";
    }

    public function scopeHasEnabledUser($query)
    {
        $query->whereHas('client.user', function ($q) {
            return $q->where('enabled', 1);
        });
    }

    public function getStatusNameAttribute()
    {
        return config('enums.statuses')[$this->status] ?? 'pending';
    }

    public function scopeFilter($query, array $filters)
    {

        $t = $filters;

        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('id', 'like', '%' . preg_replace('/\/.*/', '', $search) . '%');
        })
            ->when($filters['language_id'] ?? null, function ($query, $language) {
                $query->where('to_language_id', $language);
            })
            ->when($filters['client'] ?? null, function ($query, $client) {
                $query->whereHas('client', function ($q) use ($client) {
                    $q->where('id', $client);
                });
            })
            ->when($filters['require_qualified'] ?? null, function ($query, $require_qualified) {
                $query->where('require_qualified', $require_qualified);
            })
            ->when($filters['dna'] ?? null, function ($query, $dna) {
                $query->where('dna', $dna);
            })
            ->when($filters['retrn'] ?? null, function ($query, $retrn) {
                $query->where('retrn', $retrn);
            })
            ->when($filters['company'] ?? null, function ($query, $company) {
                $query->whereHas('client.organisation.company', function ($query) use ($company) {
                    $query->where('id', $company);
                });
            })
            ->when($filters['skills'] ?? null, function ($query, $skills) {
                $query->whereHas('skills', function ($query) use ($skills) {
                    $query->where('id', $skills);
                });
            })

            ->when($filters['agents'] ?? null, function ($query, $agents) {
                $query->whereHas('agent.user', function ($query) use ($agents) {
                    $query->where('id', $agents);
                });
            })

            ->when($filters['date'] ?? null, function ($query, $date) {
                $dateColumn = get_class($this) == 'App\\InterpreterJob' ? 'appointment_date' : 'target_date';

                if (strpos($date, 'to') === false) {
                    return $query->where($dateColumn, $date);
                }

                $query->dateBetween($dateColumn, $date);
            })
            ->when($filters['bulk_id'] ?? null, function ($query, $bulkId) {
                $query->where('bulk_id', $bulkId);
            });

        // Timesheet: Verified filter (Interpreter jobs only)
        // Helps finance/admin quickly locate invoice-ready paper workflows
        $query->when($filters['timesheet_verified'] ?? null, function ($query, $flag) {
            // Only applies to InterpreterJob
            if (get_class($this) === 'App\\InterpreterJob') {
                // Only include jobs with paper workflow and verified timesheets
                if ((string) $flag === '1') {
                    $query->where('signature_method', 'paper')
                        ->where('timesheet_status', 'verified');
                }
                // Optionally support explicit non-verified filter
                if ((string) $flag === '0') {
                    $query->where(function ($q) {
                        $q->whereNull('signature_method')
                            ->orWhere('signature_method', '!=', 'paper')
                            ->orWhereNull('timesheet_status')
                            ->orWhere('timesheet_status', '!=', 'verified');
                    });
                }
            }
        });


        if (isset($filters['status']) && $filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }
    }

    public function scopeDateBetween($query, string $dateColumn, string $date)
    {
        $query->where([
            [$dateColumn, '>=', explode(' to ', $date)[0]],
            [$dateColumn, '<=', explode(' to ', $date)[1]]
        ]);
    }

    public function matchedLoggedInAgent()
    {
        return $this->matchedAgents->where('agent_id', optional(auth()->user()->agent)->id)->first();
    }

    public function canBeQuotedByUser(User $user): bool
    {
        if (RoleHelper::hasRole($user, 'admin')) {
            $hasAcceptedAdminQuote = $this->adminQuotes()->where('status', 1)->exists();
            return in_array($this->statusName, ['pending', 'quoted']) && $this->quotedMatchedAgents->isNotEmpty() && !$hasAcceptedAdminQuote;
        }

        if (RoleHelper::hasRole($user, 'agent')) {
            $matched = $this->matchedLoggedInAgent();
            return $this->statusName === 'pending' && $matched && $matched->quotes->isEmpty();
        }

        return false;
    }

    public function getStatusForAgent()
    {
        if ($this->statusName === 'cancelled') {
            return 'cancelled';
        }

        $matchedAgent = $this->matchedLoggedInAgent();

        if (!$matchedAgent) {
            return null;
        }

        // If the job is not assigned to this agent (agent_id is null or different), 
        // and the matched agent was previously assigned, show as cancelled
        if ($matchedAgent->status == 'assigned' && $this->agent_id != optional(auth()->user()->agent)->id) {
            return 'cancelled';
        }

        $status = $matchedAgent->status;
        return $status == 'accepted' ? 'quoted' : $status;
    }

    public function getMatchedAgentIdAttribute()
    {
        return optional($this->matchedLoggedInAgent())->id;
    }

    public function isAssignedToAgent(int $agentId)
    {
        return optional($this->assignedMatched)->id == $agentId;
    }

    public function adminQuotes()
    {
        return $this->morphMany('App\\AdminQuote', 'job');
    }

    public function hasRejectedAdminQuote()
    {
        return $this->adminQuotes()->where('status', 2)->exists();
    }

    public function hasAcceptedAdminQuote()
    {
        return $this->adminQuotes()->where('status', 1)->exists();
    }

    public function latestAdminQuote()
    {
        return $this->adminQuotes()->latest('created_at')->first();
    }

    public function showQuoteRejectedFlagForUser(User $user): bool
    {
        $latest = $this->latestAdminQuote();
        return RoleHelper::hasAnyRole($user, ['admin', 'client'])
            && $this->statusName === 'pending'
            && $latest
            && $latest->status_id === 2;
    }

    public function quotesVisibleToUser(User $user)
    {
        if (RoleHelper::hasAnyRole($user, ['admin', 'client'])) {
            return $this->adminQuotes;
        }
        $matched = $this->matchedLoggedInAgent();
        return $matched ? $matched->quotes : collect();
    }

    public function scopeVisibleToUser($query, User $user)
    {
        $query->when(RoleHelper::hasRole($user, 'admin'), function ($query) {
            $query->with('cancellation:id,job_id,job_type,message');
        })
            ->when(RoleHelper::hasRole($user, 'client'), function ($query) use ($user) {
                $query->where('client_id', optional($user->client)->id);
            })
            ->when(RoleHelper::hasRole($user, 'agent'), function ($query) use ($user) {
                $agent = $user->agent;
                if ($agent && $agent->id) {
                    $query->where(function ($q) use ($agent) {
                        // Show jobs that are still available (not cancelled/rejected/completed)
                        // regardless of target_date — agents should see all their matched jobs
                        $q->whereNotIn('status', [2, 3, 4])
                            ->matchedToAgent($agent)
                            ->orWhere('agent_id', $agent->id);
                    });
                } else {
                    $query->whereRaw('1=0');
                }
            });
    }
    public function scopeMatchedToAgent($query, Agent $agent)
    {
        $query->whereDoesntHave('agent')

            ->whereHas('matchedAgents', function ($q) use ($agent) {
                $q->where('agent_id', $agent->id)
                    ->whereNotIn('status', [1, 3]);
            })
            ->where(function ($q) use ($agent) {
                // For TranslatorJob, check language compatibility
                $q->when(get_class($this) === 'App\\TranslatorJob', function ($subQuery) use ($agent) {
                    $subQuery->where(function ($langQuery) use ($agent) {
                        $langQuery->whereRaw('from_language_id = to_language_id')
                            ->whereExists(function ($exists) use ($agent) {
                                $exists->select(DB::raw(1))
                                    ->from('agent_language')
                                    ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = from_language_id', [$agent->id]);
                            })
                            ->orWhere(function ($diffLangQuery) use ($agent) {
                                $diffLangQuery->whereRaw('from_language_id != to_language_id')
                                    ->whereExists(function ($fromExists) use ($agent) {
                                        $fromExists->select(DB::raw(1))
                                            ->from('agent_language')
                                            ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = from_language_id', [$agent->id]);
                                    })
                                    ->whereExists(function ($toExists) use ($agent) {
                                        $toExists->select(DB::raw(1))
                                            ->from('agent_language')
                                            ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = to_language_id', [$agent->id]);
                                    });
                            });
                    });
                })
                    // For InterpreterJob, check language compatibility
                    ->when(get_class($this) === 'App\\InterpreterJob', function ($subQuery) use ($agent) {
                    $subQuery->whereExists(function ($exists) use ($agent) {
                        $exists->select(DB::raw(1))
                            ->from('agent_language')
                            ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = to_language_id', [$agent->id]);
                    });
                });
            })
            ->excludeDoubleBookings($agent);
    }

    public function scopeExcludeDoubleBookings($query, Agent $agent)
    {
        // For interpreter jobs, exclude any job that overlaps in time with an already assigned job for this agent.
        $query->when(get_class($this) === 'App\\InterpreterJob', function ($q) use ($agent) {
            // Use a more efficient subquery with proper indexing
            $q->whereNotExists(function ($sub) use ($agent) {
                $sub->select(DB::raw(1))
                    ->from('interpreter_jobs as ij2')
                    ->where('ij2.agent_id', $agent->id)
                    ->where('ij2.status', 1) // Only check assigned jobs
                    ->whereColumn('ij2.appointment_date', 'interpreter_jobs.appointment_date')
                    ->where(function ($overlap) {
                        // Optimized time overlap check using indexed columns
                        $overlap->whereRaw('interpreter_jobs.start_time < ij2.end_time')
                            ->whereRaw('interpreter_jobs.end_time > ij2.start_time');
                    });
            });
        });
    }

    public function isVisible($user)
    {
        return self::visibleToUser($user)->where(self::getKeyName(), $this->getKey())->exists();
    }
    public function isShowable($user)
    {
        return self::showableToUser($user)->where(self::getKeyName(), $this->getKey())->exists();
    }
    public function scopeShowableToUser($query, User $user)
    {
        $query->when(RoleHelper::hasRole($user, 'admin'), function ($query) {
            $query->with('cancellation:id,job_id,job_type,message');
        })
            ->when(RoleHelper::hasRole($user, 'client'), function ($query) use ($user) {
                $query->where('client_id', optional($user->client)->id);
            })
            ->when(RoleHelper::hasRole($user, 'agent'), function ($query) use ($user) {
                $agent = $user->agent;
                if ($agent && $agent->id) {
                    $query->where(function ($q) use ($agent) {
                        // Show jobs that are still available (not cancelled/rejected/completed)
                        // regardless of target_date — agents should see all their matched jobs
                        $q->whereNotIn('status', [2, 3, 4])
                            ->showToAgent($agent)
                            ->orWhere('agent_id', $agent->id);
                    });
                } else {
                    $query->whereRaw('1=0');
                }
            });
    }
    public function scopeShowToAgent($query, Agent $agent)
    {
        $query->whereDoesntHave('agent')

            ->whereHas('matchedAgents', function ($q) use ($agent) {
                $q->where('agent_id', $agent->id)
                    ->whereNotIn('status', [1, 3]);
            })
            ->where(function ($q) use ($agent) {
                // For TranslatorJob, check language compatibility
                $q->when(get_class($this) === 'App\\TranslatorJob', function ($subQuery) use ($agent) {
                    $subQuery->where(function ($langQuery) use ($agent) {
                        $langQuery->whereRaw('from_language_id = to_language_id')
                            ->whereExists(function ($exists) use ($agent) {
                                $exists->select(DB::raw(1))
                                    ->from('agent_language')
                                    ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = from_language_id', [$agent->id]);
                            })
                            ->orWhere(function ($diffLangQuery) use ($agent) {
                                $diffLangQuery->whereRaw('from_language_id != to_language_id')
                                    ->whereExists(function ($fromExists) use ($agent) {
                                        $fromExists->select(DB::raw(1))
                                            ->from('agent_language')
                                            ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = from_language_id', [$agent->id]);
                                    })
                                    ->whereExists(function ($toExists) use ($agent) {
                                        $toExists->select(DB::raw(1))
                                            ->from('agent_language')
                                            ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = to_language_id', [$agent->id]);
                                    });
                            });
                    });
                })
                    // For InterpreterJob, check language compatibility
                    ->when(get_class($this) === 'App\\InterpreterJob', function ($subQuery) use ($agent) {
                    $subQuery->whereExists(function ($exists) use ($agent) {
                        $exists->select(DB::raw(1))
                            ->from('agent_language')
                            ->whereRaw('agent_language.agent_id = ? AND agent_language.language_id = to_language_id', [$agent->id]);
                    });
                });
            })

            ->excludeDoubleBookings($agent);
    }
    public function canBeCancelled(): bool
    {
        return !$this->cannotBeCancelled();
    }

    public function cannotBeCancelled(): bool
    {
        if (class_basename($this) == 'InterpreterJob') {
            $currentDate = Carbon::parse($this->appointment_date->setTimeFromTimeString($this->start_time))->timezone('Europe/London')
            ;
        } else {
            $currentDate = Carbon::parse($this->target_date)->timezone('Europe/London')
            ;
        }
        $elapsed = false;
        // Compare using Europe/London to avoid UTC vs local day mismatches
        if ($currentDate < Carbon::now('Europe/London')) {
            $elapsed = true;
        }
        // }    else{
        //     $elapsed=true;
        // }

        return in_array($this->statusName, ['cancelled', 'rejected', 'completed']) || $elapsed == true;
    }

    public function cancel(?string $message): void
    {
        $agentId = $this->agent_id;
        $nowLondon = Carbon::now('Europe/London');
        $shouldPreserveAgentId = false;

        if (!empty($agentId)) {
            if (get_class($this) === 'App\\InterpreterJob' && !empty($this->appointment_date)) {
                $jobDateLondon = Carbon::parse($this->appointment_date)->timezone('Europe/London');
                $shouldPreserveAgentId = $jobDateLondon->isSameDay($nowLondon);
            }

            if (get_class($this) === 'App\\TranslatorJob' && !empty($this->target_date)) {
                $jobDateLondon = Carbon::parse($this->target_date)->timezone('Europe/London');
                $shouldPreserveAgentId = $jobDateLondon->isSameDay($nowLondon);
            }
        }

        $updates = [
            'status' => 2,
            'cancelled_at' => now(),
        ];

        if (!$shouldPreserveAgentId) {
            $updates['agent_id'] = null;
        }

        $this->update($updates);

        if ($message) {
            $this->cancellation()->create([
                'message' => $message,
            ]);
        }
    }
    public function securityType()
    {

        return $this->belongsTo(SecurityType::class);
    }
    public function interpreterType()
    {
        return $this->belongsTo(interpreterType::class);
    }

}

?>