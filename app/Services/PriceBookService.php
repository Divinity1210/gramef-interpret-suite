<?php

namespace App\Services;

use App\InterpreterJob;
use App\RateCard;
use Carbon\Carbon;

class PriceBookService
{
    /**
     * Calculate interpreter pay, client charge, daytime/OOH split, and net margin for a job.
     */
    public function calculateForJob(InterpreterJob $job, array $overrides = []): array
    {
        $serviceType = optional($job->skill)->skill ?: 'Face to Face';

        $clientCard = $this->resolveRateCard('client', $serviceType);
        $agentCard = $this->resolveRateCard('interpreter', $serviceType);

        $bookedMinutes = (($job->duration_hours ?? 0) * 60) + ($job->duration_minutes ?? 0);
        if ($bookedMinutes <= 0) {
            $bookedMinutes = 60;
        }

        $timesheet = $job->timesheet;
        $actualMinutes = $bookedMinutes;
        if ($timesheet) {
            $tsMinutes = (($timesheet->agent_duration_hours ?? 0) * 60) + ($timesheet->agent_duration_minutes ?? 0);
            if ($tsMinutes > 0) {
                $actualMinutes = $tsMinutes;
            }
        }

        $effectiveMinutes = isset($overrides['minutes']) ? (int) $overrides['minutes'] : $actualMinutes;
        $startTimeStr = $overrides['start_time'] ?? ($job->start_time ?: '09:00');
        $dateStr = $job->appointment_date ? $job->appointment_date->toDateString() : now('Europe/London')->toDateString();

        [$daytimeMinutes, $oohMinutes] = $this->splitDaytimeAndOoh($dateStr, $startTimeStr, $effectiveMinutes);

        $minClientHours = (float) ($clientCard->minimum_hours ?? 1.0);
        $minAgentHours = (float) ($agentCard->minimum_hours ?? 1.0);

        $clientBillableHours = max($minClientHours, ($daytimeMinutes + $oohMinutes) / 60.0);
        $agentPayableHours = max($minAgentHours, ($daytimeMinutes + $oohMinutes) / 60.0);

        $dayFraction = ($daytimeMinutes + $oohMinutes) > 0 ? ($daytimeMinutes / ($daytimeMinutes + $oohMinutes)) : 1.0;
        $oohFraction = 1.0 - $dayFraction;

        $clientDayCharge = round(($clientBillableHours * $dayFraction) * (float) $clientCard->daytime_rate, 2);
        $clientOohCharge = round(($clientBillableHours * $oohFraction) * (float) $clientCard->ooh_rate, 2);

        $agentDayPay = round(($agentPayableHours * $dayFraction) * (float) $agentCard->daytime_rate, 2);
        $agentOohPay = round(($agentPayableHours * $oohFraction) * (float) $agentCard->ooh_rate, 2);

        $travelHours = (float) ($overrides['travel_hours'] ?? 0.5);
        $miles = (float) ($overrides['miles'] ?? 6.0);
        $parking = (float) ($overrides['parking'] ?? 0.0);

        // Remote/telephone/video jobs have zero travel
        if (stripos($serviceType, 'Telephone') !== false || stripos($serviceType, 'Video') !== false) {
            $travelHours = 0.0;
            $miles = 0.0;
            $parking = 0.0;
        }

        $clientTravel = round(($travelHours * (float) $clientCard->travel_rate_hourly) + ($miles * (float) $clientCard->mileage_rate) + $parking, 2);
        $agentTravel = round(($travelHours * (float) $agentCard->travel_rate_hourly) + ($miles * (float) $agentCard->mileage_rate) + $parking, 2);

        $clientTotal = round($clientDayCharge + $clientOohCharge + $clientTravel, 2);
        $agentTotal = round($agentDayPay + $agentOohPay + $agentTravel, 2);
        $margin = round($clientTotal - $agentTotal, 2);
        $marginPct = $clientTotal > 0 ? round(($margin / $clientTotal) * 100, 1) : 0.0;

        return [
            'service_type' => $serviceType,
            'client_card_name' => $clientCard->name,
            'agent_card_name' => $agentCard->name,
            'booked_minutes' => $bookedMinutes,
            'effective_minutes' => $effectiveMinutes,
            'daytime_minutes' => $daytimeMinutes,
            'ooh_minutes' => $oohMinutes,
            'client_day_rate' => (float) $clientCard->daytime_rate,
            'client_ooh_rate' => (float) $clientCard->ooh_rate,
            'agent_day_rate' => (float) $agentCard->daytime_rate,
            'agent_ooh_rate' => (float) $agentCard->ooh_rate,
            'client_travel' => $clientTravel,
            'agent_travel' => $agentTravel,
            'client_charge' => $clientTotal,
            'interpreter_pay' => $agentTotal,
            'margin' => $margin,
            'margin_pct' => $marginPct,
        ];
    }

    public function splitDaytimeAndOoh(string $dateStr, string $startTimeStr, int $durationMinutes): array
    {
        try {
            $start = Carbon::parse($dateStr . ' ' . substr($startTimeStr, 0, 5), 'Europe/London');
        } catch (\Throwable $e) {
            return [$durationMinutes, 0];
        }

        $day = 0;
        $ooh = 0;
        $cursor = $start->copy();
        $step = 15;
        $remaining = max(15, $durationMinutes);

        while ($remaining > 0) {
            $chunk = min($step, $remaining);
            $hour = (int) $cursor->format('G');
            $isWeekend = $cursor->isWeekend();
            if (!$isWeekend && $hour >= 8 && $hour < 18) {
                $day += $chunk;
            } else {
                $ooh += $chunk;
            }
            $cursor->addMinutes($chunk);
            $remaining -= $chunk;
        }

        return [$day, $ooh];
    }

    protected function resolveRateCard(string $roleType, string $serviceType): RateCard
    {
        $card = RateCard::where('role_type', $roleType)
            ->where('service_type', $serviceType)
            ->first();

        if ($card) {
            return $card;
        }

        $default = RateCard::where('role_type', $roleType)
            ->where('is_default', true)
            ->first();

        if ($default) {
            return $default;
        }

        return new RateCard([
            'name' => $roleType === 'client' ? 'Standard Public Sector Rate' : 'Standard Interpreter Rate',
            'role_type' => $roleType,
            'service_type' => $serviceType,
            'daytime_rate' => $roleType === 'client' ? 48.00 : 28.00,
            'ooh_rate' => $roleType === 'client' ? 66.00 : 38.00,
            'minimum_hours' => 1.00,
            'travel_rate_hourly' => $roleType === 'client' ? 22.00 : 14.00,
            'mileage_rate' => 0.45,
        ]);
    }
}
