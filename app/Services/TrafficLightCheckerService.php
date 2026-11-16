<?php

namespace App\Services;

use App\InterpreterJob;

class TrafficLightCheckerService
{
    protected PriceBookService $priceBook;

    public function __construct(PriceBookService $priceBook)
    {
        $this->priceBook = $priceBook;
    }

    /**
     * Evaluate booked time, GPS check-in/out, signed timesheet, receipt OCR, and margin.
     */
    public function evaluate(InterpreterJob $job): array
    {
        $warnings = [];
        $calc = $this->priceBook->calculateForJob($job);

        $bookedMinutes = $calc['booked_minutes'];
        $timesheet = $job->timesheet;
        $entry = $job->agentJobEntry;

        $gpsMinutes = null;
        if ($job->checked_in_at && $job->checked_out_at) {
            $gpsMinutes = max(1, $job->checked_in_at->diffInMinutes($job->checked_out_at));
        }

        $timesheetMinutes = null;
        if ($timesheet) {
            $tsMins = (($timesheet->agent_duration_hours ?? 0) * 60) + ($timesheet->agent_duration_minutes ?? 0);
            if ($tsMins > 0) {
                $timesheetMinutes = $tsMins;
            }
        }

        // Check 1: Claimed interpreter amount vs Price Book calculated pay
        $claimedPay = $entry ? (float) $entry->total_amount : (float) ($job->calculated_interpreter_pay ?? $calc['interpreter_pay']);
        $clientCharge = (float) ($job->calculated_client_charge ?? $calc['client_charge']);

        if ($timesheetMinutes !== null && $bookedMinutes > 0 && abs($timesheetMinutes - $bookedMinutes) > 45 && $entry && $claimedPay > ($calc['interpreter_pay'] + 15)) {
            $warnings[] = sprintf(
                'Interpreter claimed %s (£%.2f), but signed timesheet duration is %s.',
                $this->fmtMinutes($bookedMinutes),
                $claimedPay,
                $this->fmtMinutes($timesheetMinutes)
            );
        }

        // Check 2: GPS check-in vs Timesheet mismatch
        if ($gpsMinutes !== null && $timesheetMinutes !== null && abs($gpsMinutes - $timesheetMinutes) > 40) {
            $warnings[] = sprintf(
                'GPS check-in/out elapsed time was %s, while timesheet recorded %s.',
                $this->fmtMinutes($gpsMinutes),
                $this->fmtMinutes($timesheetMinutes)
            );
        }

        // Check 3: OCR receipt check (if OCR metadata exists)
        if ($timesheet && !empty($timesheet->ocr_extracted_json)) {
            $ocr = json_decode($timesheet->ocr_extracted_json, true);
            if (is_array($ocr) && isset($ocr['receipt_amount'], $ocr['claimed_expense'])) {
                $receiptAmt = (float) $ocr['receipt_amount'];
                $claimedExp = (float) $ocr['claimed_expense'];
                if ($claimedExp > ($receiptAmt + 0.50)) {
                    $warnings[] = sprintf(
                        'Parking/travel receipt scan shows £%.2f, but £%.2f was claimed.',
                        $receiptAmt,
                        $claimedExp
                    );
                }
            }
        }

        // Check 4: Negative or low margin safety alert
        if ($claimedPay > $clientCharge && $clientCharge > 0) {
            $warnings[] = sprintf(
                'Warning: Interpreter pay (£%.2f) is higher than Client Invoice (£%.2f).',
                $claimedPay,
                $clientCharge
            );
        }

        // Check 5: Missing client signature on completed job
        if ((int) $job->status === 4 && (!$timesheet || empty($timesheet->client_signature))) {
            $warnings[] = 'Completed job is missing a digital client signature — send a 1-click No-Password signature link.';
        }

        // Preserve explicit pre-seeded warnings if present and none computed dynamically
        if (empty($warnings) && !empty($job->traffic_light_warnings)) {
            $decoded = json_decode($job->traffic_light_warnings, true);
            if (is_array($decoded) && !empty($decoded)) {
                $warnings = $decoded;
            }
        }

        $status = empty($warnings) ? 'green' : 'amber';
        $margin = round($clientCharge - $claimedPay, 2);

        return [
            'status' => $status,
            'badge_label' => $status === 'green' ? 'All Checked & Matches' : 'Please Check',
            'warnings' => $warnings,
            'booked_display' => $this->fmtMinutes($bookedMinutes),
            'gps_display' => $gpsMinutes !== null ? $this->fmtMinutes($gpsMinutes) : 'No GPS ping',
            'timesheet_display' => $timesheetMinutes !== null ? $this->fmtMinutes($timesheetMinutes) : 'Pending signature',
            'interpreter_pay' => $claimedPay,
            'client_charge' => $clientCharge,
            'margin' => $margin,
        ];
    }

    protected function fmtMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) {
            return "{$h}h {$m}m";
        }
        if ($h > 0) {
            return "{$h}h 00m";
        }
        return "{$m}m";
    }
}
