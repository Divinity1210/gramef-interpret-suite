<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\User;
use App\Client;
use App\Language;
use App\Skill;
use App\InterpreterJob;
use App\TranslatorJob;
use Spatie\Permission\Models\Role;

class JobPreviewSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 0) Ensure interpreter-related roles exist to prevent matching exceptions during observers
            // These roles are referenced in Agent scopes used by MatchAgents when jobs are created
            foreach (['court-qualified-interpreter', 'level-community-interpreter', 'qualified-translator', 'community-interpreter'] as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }
            // 1) Ensure a few languages exist
            $english = Language::firstOrCreate(['name' => 'English']);
            $french = Language::firstOrCreate(['name' => 'French']);
            $spanish = Language::firstOrCreate(['name' => 'Spanish']);

            // 2) Ensure core skills exist (with stable IDs used across the app)
            // Interpreter-related
            $skillsWanted = [
                1 => ['skill' => 'Court Qualified Interpreting', 'type' => 0],
                3 => ['skill' => 'Community Interpreting (Level 3)', 'type' => 0],
                4 => ['skill' => 'Qualified Translator (Interpreting context)', 'type' => 0],
                // Translator-related
                7 => ['skill' => 'Translation', 'type' => 1],
                8 => ['skill' => 'Transcription', 'type' => 1],
                9 => ['skill' => 'Text to Speech', 'type' => 1],
            ];

            foreach ($skillsWanted as $id => $attrs) {
                if (! DB::table('skills')->where('id', $id)->exists()) {
                    DB::table('skills')->insert([
                        'id' => $id,
                        'skill' => $attrs['skill'],
                        'type' => $attrs['type'],
                    ]);
                } else {
                    // Keep names/types up to date if they differ
                    DB::table('skills')->where('id', $id)->update([
                        'skill' => $attrs['skill'],
                        'type' => $attrs['type'],
                    ]);
                }
            }

            // 3) Ensure preview client exists (re-use existing if present)
            $clientUser = User::where('email', 'client.preview@example.com')->first();
            if (! $clientUser) {
                $clientUser = User::create([
                    'email' => 'client.preview@example.com',
                    'title' => 0,
                    'first_name' => 'Preview',
                    'last_name' => 'Client',
                    'password' => 'password', // User model hashes this
                    'enabled' => 1,
                ]);
            }

            $client = Client::firstOrCreate(
                ['user_id' => $clientUser->id],
                [
                    'contact_number' => '0000000000',
                    'client_address_line_1' => '1 Preview Street',
                    'client_address_line_2' => 'Suite 100',
                    'client_county' => 'Previewshire',
                    'client_postcode' => 'PR3 1VW',
                    'seen_terms' => 1,
                ]
            );

            // 4) Seed Interpreter jobs (if database is sparsely populated)
            if (InterpreterJob::count() < 8) {
                $base = Carbon::now()->startOfDay();
                $skillIds = [1, 3, 4];
                $statuses = [0, 1, 5, 4, 2, 3]; // pending, assigned, quoted, completed, cancelled, rejected

                for ($i = 0; $i < 10; $i++) {
                    $date = $base->copy()->subDays(5 - $i)->toDateString(); // spread across past/future
                    $start = Carbon::createFromTime(9 + ($i % 4), 30);
                    $end = $start->copy()->addMinutes(90);

                    InterpreterJob::create([
                        'client_id' => $client->id,
                        'skill_id' => $skillIds[$i % count($skillIds)],
                        'require_qualified' => ($i % 3) + 1, // 1..3 variety
                        'from_language_id' => $english->id,
                        'to_language_id' => $french->id,
                        'status' => $statuses[$i % count($statuses)],
                        'gender' => 2, // Either
                        'special_requirements' => $i % 2 === 0 ? 'Wheelchair access required' : null,
                        'contact_information' => 'Primary contact: John Doe, 01234 567890',
                        'personal_identity_number' => 'XYZ123',
                        'user_title' => 4, // Ms. per config('enums.titles')
                        'user_first_name' => 'Requester',
                        'user_last_name' => 'Name',
                        'client_reference' => 'IP-SEED-' . $i,
                        'appointment_date' => $date,
                        'address_line_1' => '10 High Street',
                        'address_line_2' => 'Room ' . $i,
                        'county' => 'Previewshire',
                        'postcode' => 'PR3 1VW',
                        'start_time' => $start->format('H:i:s'),
                        'end_time' => $end->format('H:i:s'),
                    ]);
                }
            }

            // 5) Seed Translator jobs (if database is sparsely populated)
            if (TranslatorJob::count() < 8) {
                $base = Carbon::now()->startOfDay();
                $skillIdsT = [7, 8, 9];
                $statusesT = [0, 1, 5, 4, 2]; // pending, assigned, quoted, completed, cancelled

                for ($i = 0; $i < 10; $i++) {
                    $date = $base->copy()->subDays(7 - $i)->toDateString();
                    $wc = 300 + ($i * 100);
                    // Re-use sample PDFs from QA fixtures for convenience; string path is sufficient for list pages
                    $fileName = 'samples/qa-proof-' . (($i % 3) + 1) . '.pdf';

                    TranslatorJob::create([
                        'client_id' => $client->id,
                        'skill_id' => $skillIdsT[$i % count($skillIdsT)],
                        'from_language_id' => $english->id,
                        'to_language_id' => $spanish->id,
                        'status' => $statusesT[$i % count($statusesT)],
                        'word_count' => $wc,
                        'notes' => $i % 2 === 0 ? 'Legal document' : 'Medical report',
                        'target_date' => $date,
                        'client_reference' => 'TR-SEED-' . $i,
                        'affirmation' => false,
                        'affidavit' => false,
                    ]);
                }
            }
        });
    }
}