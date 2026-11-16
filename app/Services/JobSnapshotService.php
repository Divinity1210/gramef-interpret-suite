<?php
#

namespace App\Services;

use App\InterpreterJob;
use App\Timesheet;
use App\JobSnapshot;

class JobSnapshotService
{
    /**
     * Build a canonical snapshot array from a job (and optional timesheet) with normalized keys
     * suitable for hashing and auditing.
     */
    public static function buildSnapshotData(InterpreterJob $job, ?Timesheet $timesheet = null): array
    {
        $job->loadMissing([
            'toLanguage:id,name',
            'client.user:id,first_name,last_name,title',
            'client.organisation.company:id,name',
            'agent.user:id,first_name,last_name,title',
            'skill:id,skill',
            'securityType:id,name',
        ]);

        $clientUser = optional(optional($job->client)->user);
        $agentUser = optional(optional($job->agent)->user);

        // Compose client name defensively
        $clientName = null;
        try {
            $clientName = $clientUser ? ($clientUser->getFullNameWithTitle() ?? $clientUser->getFullName()) : null;
        } catch (\Throwable $e) {
            $clientName = $clientUser ? $clientUser->getFullName() : null;
        }

        // Resolve company name defensively to avoid null property access
        $companyName = optional(optional(optional($job->client)->organisation)->company)->name;

        $data = [
            'snapshot_type' => 'appointment',
            'job' => [
                'id' => (int) $job->id,
                'client_reference' => $job->client_reference,
                'company_name' => $companyName,
                'client_name' => $clientName,
                'language' => optional($job->toLanguage)->name,
                // Cast robustly in case appointment_date is a raw string during tests
                'appointment_date' => $job->appointment_date
                    ? \Carbon\Carbon::parse($job->appointment_date)->toDateString()
                    : null,
                'start_time' => $job->start_time,
                'end_time' => $job->end_time,
                'duration_hours' => (int) $job->duration_hours,
                'duration_minutes' => (int) $job->duration_minutes,
                'department' => $job->department,
                'skill' => optional($job->skill)->skill,
                'qualification_required' => $job->require_qualified,
                'qualification_required_label' => self::qualificationLabel($job->require_qualified),
                'security' => optional($job->securityType)->name,
                'gender' => method_exists($job, 'getGenderName') ? $job->getGenderName() : null,
                'special_requirements' => $job->special_requirements,
            ],
            'agent' => [
                'id' => optional($job->agent)->id,
                'name' => $agentUser ? $agentUser->getFullNameWithTitle() : null,
                'phone' => optional($job->agent)->contact_number,
            ],
            'address' => [
                'line_1' => $job->address_line_1,
                'line_2' => $job->address_line_2,
                'county' => $job->county,
                'postcode' => $job->postcode,
                'latitude' => $job->latitude,
                'longitude' => $job->longitude,
                'on_arrival_contact' => ($job->contact_information_is_same_as_account ?? false)
                    ? 'Same as client account'
                    : ($job->contact_information ?: null),
                'directions_url' => self::computeDirectionsUrl($job),
            ],
            'timesheet' => [
                'id' => optional($timesheet)->id,
                'agent_signature' => optional($timesheet)->agent_signature,
                'client_signature' => optional($timesheet)->client_signature,
                'agent_duration_hours' => (int) (optional($timesheet)->agent_duration_hours ?? 0),
                'agent_duration_minutes' => (int) (optional($timesheet)->agent_duration_minutes ?? 0),
            ],
        ];

        return self::ksortRecursive($data);
    }

    public static function qualificationLabel(?int $code): ?string
    {
        $map = [
            1 => 'Court Qualified Interpreter',
            2 => 'Community Interpreter',
            3 => 'Level 2/3/4 Community Interpreter',
            4 => 'Qualified Translator',
        ];
        return $code ? ($map[$code] ?? null) : null;
    }

    public static function computeDirectionsUrl(InterpreterJob $job): ?string
    {
        $dest = null;
        if ($job->latitude && $job->longitude) {
            $dest = $job->latitude . ',' . $job->longitude;
        } else {
            $dest = trim(implode(', ', array_filter([
                $job->address_line_1,
                $job->address_line_2,
                $job->county,
                $job->postcode,
            ])));
        }
        return $dest ? ('https://www.google.com/maps/dir/?api=1&destination=' . urlencode($dest)) : null;
    }

    /**
     * Create and persist a snapshot row for a job (and optional timesheet), returning the JobSnapshot.
     */
    public static function createSnapshot(InterpreterJob $job, ?Timesheet $timesheet = null, string $source = 'assignment', ?int $createdBy = null): JobSnapshot
    {
        $data = self::buildSnapshotData($job, $timesheet);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $json);
        $latestVersion = JobSnapshot::where('job_id', $job->id)->max('version');
        $version = ($latestVersion ? ($latestVersion + 1) : 1);

        return JobSnapshot::create([
            'job_id' => (int) $job->id,
            'timesheet_id' => optional($timesheet)->id,
            'version' => $version,
            'hash' => $hash,
            'source' => $source,
            'data' => $data,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Find latest snapshot for a job, optionally a specific version.
     */
    public static function latestSnapshotForJob(int $jobId, ?int $version = null): ?JobSnapshot
    {
        $query = JobSnapshot::where('job_id', $jobId);
        if ($version) {
            $query->where('version', $version);
        } else {
            $query->orderByDesc('version');
        }
        return $query->first();
    }

    /**
     * Recursively sort array keys for canonical JSON ordering.
     */
    protected static function ksortRecursive(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::ksortRecursive($value);
            }
        }
        ksort($array);
        return $array;
    }
}