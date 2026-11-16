<?php
#

namespace App\Policies;

use App\User;
use App\Timesheet;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Helpers\RoleHelper;

class TimesheetPolicy
{
    use HandlesAuthorization;

    /**
     * Grant all abilities to admins before running ability-specific checks.
     * Ensures admin users can always access timesheets.
     */
    public function before(User $user, $ability)
    {
        // Prefer App\User helpers that leverage Spatie's role API
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
        // Also allow super-admin when present
        if (method_exists($user, 'roleNames') && $user->roleNames()->contains('super-admin')) {
            return true;
        }
        return null; // fall through to ability methods
    }

    /**
     * Determine whether the user can view the timesheet PDF or model.
     * Rules:
     * - Admins can view any timesheet (handled in before())
     * - Assigned agent can view their own timesheet
     * - Client user associated to the job can view the job's timesheet
     */
    public function view(User $user, Timesheet $timesheet)
    {
        // Assigned agent (includes 'new-agent')
        $isAgent = RoleHelper::hasAnyRole($user, ['agent', 'new-agent']);
        if ($isAgent && optional($user->agent)->id === $timesheet->agent_id) {
            return true;
        }
        if ($isAgent && optional(optional($timesheet->interpreterJob)->agent)->id === optional($user->agent)->id) {
            return true;
        }

        // Job's client user
        $jobClient = optional($timesheet->interpreterJob ?? $timesheet->interpreter)->client; // prefer belongsTo
        $isClient = method_exists($user, 'isClient') ? $user->isClient() : RoleHelper::hasRole($user, 'client');
        if ($isClient && $jobClient && optional($user->client)->is($jobClient)) {
            return true;
        }

        return false;
    }
}
