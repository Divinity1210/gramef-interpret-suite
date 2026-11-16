<?php

namespace App\Services;

use App\Language;
use Carbon\Carbon;

class SmartBookingParserService
{
    /**
     * Parse unstructured booking referral email/text into structured InterpreterJob fields.
     */
    public function parse(string $rawText): array
    {
        $text = trim($rawText);
        $result = [
            'language' => null,
            'language_id' => null,
            'appointment_date' => null,
            'start_time' => null,
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'postcode' => null,
            'address_line_1' => null,
            'department' => null,
            'gender' => 2, // Either
            'security_level' => 'Enhanced DBS',
            'security_type_id' => 1,
            'service_type' => 'Face to Face',
            'client_reference' => null,
            'user_first_name' => null,
            'user_last_name' => null,
            'personal_identity_number' => null,
            'date_of_birth' => null,
            'special_requirements' => null,
        ];

        // 1. Language detection against known Language table + common aliases
        $languages = Language::orderBy('name')->get(['id', 'name']);
        foreach ($languages as $lang) {
            if (preg_match('/\b' . preg_quote($lang->name, '/') . '\b/i', $text)) {
                $result['language'] = $lang->name;
                $result['language_id'] = $lang->id;
                break;
            }
        }

        // 2. UK Postcode
        if (preg_match('/\b([A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2})\b/i', $text, $m)) {
            $result['postcode'] = strtoupper(preg_replace('/\s+/', ' ', trim($m[1])));
        }

        // 3. Date (YYYY-MM-DD, DD/MM/YYYY, or "18 Nov 2026" / "tomorrow")
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $m)) {
            $result['appointment_date'] = $m[1];
        } elseif (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $text, $m)) {
            try {
                $result['appointment_date'] = Carbon::createFromFormat(
                    strpos($m[1], '/') !== false ? 'd/m/Y' : 'd-m-Y',
                    $m[1]
                )->toDateString();
            } catch (\Throwable $e) {
            }
        } elseif (preg_match('/\b(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4})\b/i', $text, $m)) {
            try {
                $result['appointment_date'] = Carbon::parse($m[1])->toDateString();
            } catch (\Throwable $e) {
            }
        } elseif (stripos($text, 'tomorrow') !== false) {
            $result['appointment_date'] = now('Europe/London')->addDay()->toDateString();
        }

        // 4. Time range or start time (e.g. "10:30 - 12:30" or "at 14:00")
        if (preg_match('/\b(\d{1,2}[:.]\d{2})\s*(?:-|to|until|–)\s*(\d{1,2}[:.]\d{2})\b/i', $text, $m)) {
            $startStr = str_replace('.', ':', $m[1]);
            $endStr = str_replace('.', ':', $m[2]);
            $result['start_time'] = strlen($startStr) === 4 ? '0' . $startStr : $startStr;
            try {
                $s = Carbon::createFromFormat('H:i', $result['start_time']);
                $e = Carbon::createFromFormat('H:i', strlen($endStr) === 4 ? '0' . $endStr : $endStr);
                if ($e->greaterThan($s)) {
                    $mins = $s->diffInMinutes($e);
                    $result['duration_hours'] = intdiv($mins, 60);
                    $result['duration_minutes'] = $mins % 60;
                }
            } catch (\Throwable $e) {
            }
        } elseif (preg_match('/\b(\d{1,2}:\d{2})\b/', $text, $m)) {
            $result['start_time'] = strlen($m[1]) === 4 ? '0' . $m[1] : $m[1];
        }

        // Duration fallback (e.g. "2 hours", "90 mins")
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hours?|hrs?)/i', $text, $m)) {
            $hrs = (float) $m[1];
            $result['duration_hours'] = (int) floor($hrs);
            $result['duration_minutes'] = (int) round(($hrs - floor($hrs)) * 60);
        }

        // 5. Gender preference
        if (preg_match('/\bfemale\s+interpreter\b|\bgender[:\s]+female\b|\bprefer\s+female\b/i', $text)) {
            $result['gender'] = 1;
        } elseif (preg_match('/\bmale\s+interpreter\b|\bgender[:\s]+male\b|\bprefer\s+male\b/i', $text)) {
            $result['gender'] = 0;
        }

        // 6. Security / DBS level
        if (preg_match('/national\s+security|NPPV|vetting/i', $text)) {
            $result['security_level'] = 'National Security Vetting';
            $result['security_type_id'] = 3;
        } elseif (preg_match('/standard\s+dbs/i', $text)) {
            $result['security_level'] = 'Standard DBS';
            $result['security_type_id'] = 2;
        } elseif (preg_match('/no\s+dbs|dbs\s+not\s+required/i', $text)) {
            $result['security_level'] = 'DBS Not Required';
            $result['security_type_id'] = 4;
        }

        // 7. Reference numbers (PO, NHS Ref, Case Ref, File Ref)
        if (preg_match('/(?:ref(?:erence)?|case|po|booking\s*#?)[:#\s]+([A-Z0-9\-\/]{4,24})/i', $text, $m)) {
            $result['client_reference'] = strtoupper(trim($m[1]));
        }

        // 8. NHS / PID number
        if (preg_match('/(?:nhs|pid|hospital\s*no)[:#\s]+([0-9\-\s]{6,15})/i', $text, $m)) {
            $result['personal_identity_number'] = trim($m[1]);
        }

        // 9. Department / Ward / Courtroom
        if (preg_match('/(?:department|ward|clinic|courtroom|location)[:\s]+([^\n\r,]+)/i', $text, $m)) {
            $result['department'] = trim($m[1]);
        }

        // 10. Address line
        if (preg_match('/(?:address|venue|hospital|court)[:\s]+([^\n\r]+)/i', $text, $m)) {
            $result['address_line_1'] = trim($m[1]);
        }

        // 11. Service User Name
        if (preg_match('/(?:service\s*user|patient|client\s*name)[:\s]+([A-Z][a-z]+)\s+([A-Z][a-z]+)/i', $text, $m)) {
            $result['user_first_name'] = trim($m[1]);
            $result['user_last_name'] = trim($m[2]);
        }

        // 12. Service Type
        if (preg_match('/\b(telephone|phone)\b/i', $text)) {
            $result['service_type'] = 'Telephone Interpreting';
        } elseif (preg_match('/\b(video|teams|zoom)\b/i', $text)) {
            $result['service_type'] = 'Video Interpreting';
        } elseif (preg_match('/\bBSL|sign\s+language\b/i', $text)) {
            $result['service_type'] = 'BSL';
        }

        return $result;
    }
}
