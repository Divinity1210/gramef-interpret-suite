<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Agent;
use App\InterpreterJob;
use App\TranslatorJob;
use App\Payslip;

class AgentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $agent = Agent::where('user_id', optional($user)->id)->first();

        // Use UK timezone for date windows
        $today = Carbon::today('Europe/London');
        $in7 = Carbon::today('Europe/London')->addDays(7);

        $upcomingJobs = 0;
        $upcomingJobsSoon = 0; // within 48 hours
        $recentJobs = collect();
        $earnings30d = 0.0;

        if ($agent) {
            try {
                $upcomingJobs = InterpreterJob::where('agent_id', $agent->id)
                    ->whereDate('appointment_date', '>=', $today)
                    ->whereDate('appointment_date', '<=', $in7)
                    ->count() +
                    TranslatorJob::where('agent_id', $agent->id)
                    ->whereDate('target_date', '>=', $today)
                    ->whereDate('target_date', '<=', $in7)
                    ->count();
            } catch (\Throwable $e) { /* safe fallback */ }

            try {
                $soon = Carbon::now('Europe/London')->addHours(48);
                $upcomingJobsSoon = InterpreterJob::where('agent_id', $agent->id)
                    ->where('appointment_date', '>=', Carbon::now('Europe/London'))
                    ->where('appointment_date', '<=', $soon)
                    ->count() +
                    TranslatorJob::where('agent_id', $agent->id)
                    ->where('target_date', '>=', Carbon::now('Europe/London'))
                    ->where('target_date', '<=', $soon)
                    ->count();
            } catch (\Throwable $e) { /* safe fallback */ }

            try {
                $recentJobs = InterpreterJob::with(['client.user','toLanguage'])
                    ->where('agent_id', $agent->id)
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get();
            } catch (\Throwable $e) { $recentJobs = collect(); }

            try {
                $earnings30d = (float) Payslip::where('agent_id', $agent->id)
                    ->whereDate('created_at', '>=', Carbon::today('Europe/London')->subDays(30))
                    ->sum('total');
            } catch (\Throwable $e) { $earnings30d = 0.0; }
        }

        return view('agent.dashboard', compact(
            'agent',
            'upcomingJobs',
            'upcomingJobsSoon',
            'recentJobs',
            'earnings30d'
        ));
    }
}