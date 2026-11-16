<?php

namespace App;

use App\Language;
use App\Helpers\RoleHelper;
use Carbon\Carbon;
use App\Collections\Collection;
use Illuminate\Support\Facades\Log;

class InterpreterJob extends Job
{
    protected $fillable = [
        'client_id',
        'agent_id',
        'skill_id',
        'require_qualified',
        'security_type_id',
        'from_language_id',
        'to_language_id',
        'requested_agent_id',
        'status',
        'gender',
        'special_requirements',
        'contact_information',
        'personal_identity_number',
        'contact_person_details',
        'user_title',
        'user_first_name',
        'user_last_name',
        'client_reference',
        'appointment_date',
        'start_time',
        'end_time',
        'department',
        'address_line_1',
        'address_line_2',
        'county',
        'postcode',
        'file_reference',
        'date_of_birth',
        'latitude',
        'longitude',
        'duration',
        'duration_minutes',
        'duration_hours',
        'service_user_required',
        'contact_information_is_same_as_account',
        'cancelled_at',
        'dna',
        'retrn',
        'bulk_id',
        'checked_in_at',
        'checked_in_lat',
        'checked_in_lng',
        'checked_out_at',
        'checked_out_lat',
        'checked_out_lng',
    ];

    protected $dates = [
        'appointment_date',
        'date_of_birth',
        'cancelled_at',
        'checked_in_at',
        'checked_out_at',
    ];

    protected $casts = [
        'service_user_required' => 'boolean',
        'contact_information_is_same_as_account' => 'boolean',
        'require_qualified' => 'integer',
        // Ensure security_type_id is consistently treated as an integer.
        // This prevents strict comparisons elsewhere (e.g., matching scopes)
        // from misclassifying 'DBS Not required' jobs when the value is stored as a string.
        'security_type_id' => 'integer',
        'gender' => 'integer',
        'dna' => 'boolean',
        'retrn' => 'boolean'
    ];

    protected $appends = [
        'fromLanguage',
        'formattedDuration',
        'totalHours'
    ];

    public function requiresQuote(): bool
    {
        return (bool) data_get($this, 'client.always_requires_a_quote', false);
    }

    public function getGenderName()
    {
        return config('enums.genders')[$this->gender];
    }

    public function getFromLanguageAttribute()
    {
        return new Language(['name' => 'English', 'id' => 37]);
    }
    public function getFormattedDurationAttribute()
    {
        $hours = $this->duration_hours ?? 0;
        $minutes = $this->duration_minutes ?? 0;

        return "$hours hours $minutes minutes";
    }

    /* public function getFormattedDurationAttribute()
    {
        return "$this->duration_hours hours $this->duration_minutes minutes";
    }
 public function getStartTimeAttribute($startTime)
      {
          return Carbon::parse($startTime)->format('H:i');
      }*/
    public function getStartTimeAttribute($startTime)
    {
        // Use plain string trimming instead of Carbon::parse to avoid
        // timezone drift (UTC vs Europe/London) when rendering emails.
        return $startTime ? substr($startTime, 0, 5) : null;
    }
    public function getEndTimeAttribute($endTime)
    {
        // Use plain string trimming instead of Carbon::parse to avoid
        // timezone drift (UTC vs Europe/London) when rendering emails.
        return $endTime ? substr($endTime, 0, 5) : null;
    }

    public function getTotalHoursAttribute()
    {
        return ($this->duration_minutes / 60) + $this->duration_hours;
    }

    public function scopeActive($query)
    {
        $query->whereNotIn('status', [2, 3, 4, 6]);
    }

    public function isActive(): bool
    {
        if (in_array($this->status, [2, 3, 4])) {
            return false;
        }

        /*
        if ($this->appointment_date->endOfDay()->isFuture()) {
            return true;
        }
*/

        // return false;
        return true;
    }

    public function isWithin24Hours(): bool
    {
        return $this->appointment_date->setTimeFromTimeString($this->start_time)->subDay()->isPast();
    }

    public function isCancelledWithin24Hours(): bool
    {
        if ($this->statusName !== 'cancelled' || empty($this->cancelled_at) || empty($this->appointment_date)) {
            return false;
        }

        $tz = 'Europe/London';
        $startTime = $this->start_time ?: '00:00:00';
        $appointmentDateTime = Carbon::parse($this->appointment_date->setTimeFromTimeString($startTime))->timezone($tz);
        $cancelledAt = Carbon::parse($this->cancelled_at)->timezone($tz);
        $windowStart = $appointmentDateTime->copy()->subDay();

        return $cancelledAt->greaterThanOrEqualTo($windowStart) && $cancelledAt->lessThanOrEqualTo($appointmentDateTime);
    }

    /**
     * Determine if the job is a "late cancellation" — cancelled same-day OR within 24 hours
     * before the appointment. Late cancellations are eligible for invoicing and timesheet actions.
     */
    public function isLateCancellation(): bool
    {
        return $this->isCancelledSameDay() || $this->isCancelledWithin24Hours();
    }

    // removed invalid static helper that returned a boolean but declared EloquentCollection

    // Instance method to check if the job has exactly 24 hours remaining
    public function isExactly24HoursRemaining(): bool
    {
        if (!$this->appointment_date) {
            return false;
        }

        $now = Carbon::now('Europe/London');
        $timeStr = $this->start_time ?: '00:00:00';
        $startDateTime = Carbon::parse($this->appointment_date->format('Y-m-d') . ' ' . $timeStr, 'Europe/London');
        $diffInMinutes = $startDateTime->diffInMinutes($now, false);

        // Log the relevant times and difference
        Log::info("Checking job ID: {$this->id}");
        Log::info("Current time: {$now}");
        Log::info("Start time: {$startDateTime}");
        Log::info("Difference in minutes: {$diffInMinutes}");

        return $diffInMinutes >= -1470 && $diffInMinutes <= -1410;
    }

    /**
     * Check if the job starts in approximately N hours from now (within a tolerance window)
     *
     * @param int $hours Number of hours to check
     * @param int $toleranceMinutes Allowed tolerance in minutes (default 5)
     * @return bool
     */
    public function isApproximatelyHoursRemaining(int $hours, int $toleranceMinutes = 10): bool
    {
        if (!$this->appointment_date) {
            return false;
        }

        $now = Carbon::now('Europe/London');
        $timeStr = $this->start_time ?: '00:00:00';
        $startDateTime = Carbon::parse($this->appointment_date->format('Y-m-d') . ' ' . $timeStr, 'Europe/London');
        $diffInMinutes = $startDateTime->diffInMinutes($now, false);

        $target = -($hours * 60);
        return $diffInMinutes >= ($target - $toleranceMinutes) && $diffInMinutes <= ($target + $toleranceMinutes);
    }


    //function created by muhammad nouman
    public function getInterpreterRole()
    {
        $roleMapping = [
            1 => 'court-qualified-interpreter',
            2 => 'community-interpreter',
            3 => 'level-community-interpreter',
            4 => 'qualified-translator',
        ];

        return $roleMapping[(int) $this->require_qualified] ?? null;
    }
    public function shouldBeRematched(): bool
    {
        if (!$this->agent) {
            return true;
        }

        if ($this->isDirty('to_language_id') && !$this->agent->languages->contains('id', $this->toLanguage->id)) {
            return true;
        }

        if ($this->isDirty('skill_id') && !$this->agent->skills->contains('id', $this->skill->id)) {
            return true;
        }

        $interpreterRole = $this->getInterpreterRole();
        if ($this->isDirty('require_qualified') && $interpreterRole && !RoleHelper::hasRole($this->agent->user, $interpreterRole)) {
            return true;
        }

        if ($this->isDirty('gender') && (int) $this->gender !== 2 && (int) $this->agent->gender !== (int) $this->gender) {
            return true;
        }

        return false;
    }
    public function canBeDna(): bool
    {
        if ($this->dna) {

            return false;
        }
        if (!in_array($this->status, [1, 5])) {
            return false;
        }
        // Use Europe/London for consistent comparisons with appointment_date
        $now = Carbon::now('Europe/London');
        $appointmentDateTime = Carbon::parse($this->appointment_date->setTimeFromTimeString($this->start_time))->timezone('Europe/London');
        if (!($appointmentDateTime < $now)) {
            return false;
        }
        return true;
    }
    public function canBeRetrn(): bool
    {
        if ($this->retrn) {

            return false;
        }
        if (!in_array($this->status, [1, 5])) {
            return false;
        }
        // Use Europe/London for consistent comparisons with appointment_date
        $now = Carbon::now('Europe/London');
        $appointmentDateTime = Carbon::parse($this->appointment_date->setTimeFromTimeString($this->start_time))->timezone('Europe/London');
        if (!($appointmentDateTime < $now)) {
            return false;
        }
        return true;
    }
    public function canBeCompleted(): bool
    {
        if (!in_array($this->status, [1, 5])) {
            return false;
        }

        if (!$this->agent_id) {
            return false;
        }

        // Hard guard: Prevent completion before the appointment time has elapsed
        try {
            if ($this->appointment_date) {
                $timeStr = $this->start_time ?? ($this->appointment_time ?? '00:00:00');
                $dtStr = $this->appointment_date->format('Y-m-d') . ' ' . $timeStr;
                $appointmentDateTime = Carbon::parse($dtStr, 'Europe/London');

                // If now is strictly less than the appointment time, it cannot be completed yet
                if (Carbon::now('Europe/London')->lt($appointmentDateTime)) {
                    return false;
                }
            }
        } catch (\Throwable $e) {
            // Fallback to strict validation if parsing fails
            \Illuminate\Support\Facades\Log::warning("canBeCompleted date parse failed for job {$this->id}: " . $e->getMessage());
        }

        return true;
    }

    public function hasAddressFields(): bool
    {
        return $this->postcode || $this->address_line_1 || $this->address_line_2 || $this->county;
    }

    public function getDurationWithoutSpacesAttribute()
    {
        return str_replace(' mins', 'mins', str_replace(' hr', 'hr', $this->duration));
    }

    public function timesheet()
    {
        return $this->hasOne(Timesheet::class, 'job_id');
    }
    public function client()
    {
        return $this->hasOne(Client::class, 'id', 'client_id');
    }

    public function to_language()
    {
        return $this->hasOne(Language::class, 'id', 'to_language_id');
    }

    public function feedback()
    {
        return $this->hasOne(Feedback::class, 'job_id');
    }

    public function documents()
    {
        return $this->morphMany('App\Document', 'documentable');
    }

    public function agentJobEntry()
    {
        return $this->hasOne('App\AgentJobEntry', 'job_id')->where('job_type', 'App\InterpreterJob');
    }

    // public function isWithin48Hours(): bool
    // {
    //     $now = Carbon::now(); // Get the current date and time
    //     $appointmentDateTime = $this->appointment_date->setTimeFromTimeString($this->start_time); // Set the appointment time

    //     // Get the time 48 hours ago
    //     $twoDaysAgo = $now->copy()->subHours(48);

    //     // Check if the appointment time is within the past 48 hours from now
    //     return $appointmentDateTime->greaterThan($twoDaysAgo) && $appointmentDateTime->lessThanOrEqualTo($now);
    // }



    public function isWithin48Hours(): bool
    {
        // Get the current date and time in the Europe/London timezone
        $now = Carbon::now('Europe/London');

        // Set the appointment time in the Europe/London timezone
        $appointmentDateTime = $this->appointment_date
            ->setTimezone('Europe/London')
            ->setTimeFromTimeString($this->start_time);

        // Get the time 48 hours ago in the Europe/London timezone
        $twoDaysAgo = $now->copy()->subHours(48);

        // Check if the appointment time is within the past 48 hours from now
        return $appointmentDateTime->greaterThan($twoDaysAgo) && $appointmentDateTime->lessThanOrEqualTo($now);
    }

    /**
     * Determine if the job was cancelled on the same day as the appointment.
     * This enables same-day cancelled jobs to be eligible for timesheet signing
     * and booked time pay claims.
     */
    public function isCancelledSameDay(): bool
    {
        if ($this->statusName !== 'cancelled' || empty($this->cancelled_at) || empty($this->appointment_date)) {
            return false;
        }

        $appointmentDate = Carbon::parse($this->appointment_date)->timezone('Europe/London');
        $cancelledAt = Carbon::parse($this->cancelled_at)->timezone('Europe/London');

        return $appointmentDate->isSameDay($cancelledAt);
    }


}

?>