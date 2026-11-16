<?php

namespace Database\Seeders;

use App\Agent;
use App\AgentJobEntry;
use App\BankAccount;
use App\Client;
use App\ClientInvoice;
use App\ClientInvoiceItem;
use App\Company;
use App\ContactMethod;
use App\InterpreterJob;
use App\Language;
use App\MatchedAgent;
use App\Organisation;
use App\Payslip;
use App\RateCard;
use App\SecurityType;
use App\Skill;
use App\TenantSetting;
use App\Timesheet;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GramefEnterpriseDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Ensure core roles, contact methods, security types, skills, and UK interpreting languages exist
            $roleNames = [
                'super-admin', 'admin', 'client', 'new-client',
                'agent', 'translator', 'qualified-interpreter',
                'community-interpreter', 'new-agent',
            ];
            foreach ($roleNames as $r) {
                Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
            }

            foreach (['email', 'sms', 'phone'] as $idx => $cm) {
                ContactMethod::firstOrCreate(['contact_method' => $cm]);
            }

            $securityLevels = [
                1 => 'Enhanced DBS',
                2 => 'Standard DBS',
                3 => 'National Security Vetting',
                4 => 'DBS Not Required',
            ];
            foreach ($securityLevels as $id => $secName) {
                DB::table('security_types')->updateOrInsert(
                    ['id' => $id],
                    ['name' => $secName, 'deleted' => 0, 'details' => $secName, 'created_at' => now(), 'updated_at' => now()]
                );
            }

            $skills = [
                ['id' => 1, 'skill' => 'Face to Face', 'type' => 0],
                ['id' => 2, 'skill' => 'BSL', 'type' => 0],
                ['id' => 3, 'skill' => 'Telephone Interpreting', 'type' => 0],
                ['id' => 4, 'skill' => 'Video Interpreting', 'type' => 0],
                ['id' => 5, 'skill' => 'Simultaneous', 'type' => 0],
                ['id' => 6, 'skill' => 'Consecutive', 'type' => 0],
                ['id' => 7, 'skill' => 'Translation', 'type' => 1],
            ];
            foreach ($skills as $s) {
                DB::table('skills')->updateOrInsert(
                    ['id' => $s['id']],
                    ['skill' => $s['skill'], 'type' => $s['type']]
                );
            }

            // Replace placeholder languages (TestLang/AuthLang) if safe, and add real UK languages
            $languageNames = [
                'Arabic', 'Bengali', 'BSL', 'Cantonese', 'Dari', 'Farsi', 'French',
                'Gujarati', 'Hindi', 'Kurdish (Sorani)', 'Mandarin', 'Pashto', 'Polish',
                'Portuguese', 'Punjabi', 'Romanian', 'Russian', 'Somali', 'Spanish',
                'Turkish', 'Ukrainian', 'Urdu', 'Welsh',
            ];
            $langIds = [];
            foreach ($languageNames as $lName) {
                $lang = Language::firstOrCreate(['name' => $lName]);
                $langIds[$lName] = $lang->id;
            }
            Language::firstOrCreate(['id' => 37], ['name' => 'English']);

            // 2. White-Label Tenant Presets (GRAMEF Product Studio)
            $presets = [
                [
                    'slug' => 'gramef',
                    'brand_name' => 'GRAMEF InterpretFlow',
                    'brand_tagline' => 'Enterprise Language Operations, Automated Billing & Credit Control',
                    'logo_text' => 'GRAMEF',
                    'primary_color' => '#2563EB',
                    'accent_color' => '#10B981',
                    'currency_symbol' => '£',
                    'default_vat_rate' => 20.00,
                    'chaser_mode' => 'review',
                    'active_delivery_stage' => 4,
                    'is_active' => true,
                ],
                [
                    'slug' => 'atoz',
                    'brand_name' => 'A to Z Interpreting & Translation',
                    'brand_tagline' => 'Same Familiar System, Smoother Process — Powered by GRAMEF Digital',
                    'logo_text' => 'AtoZ',
                    'primary_color' => '#1E40AF',
                    'accent_color' => '#059669',
                    'currency_symbol' => '£',
                    'default_vat_rate' => 20.00,
                    'chaser_mode' => 'review',
                    'active_delivery_stage' => 4,
                    'is_active' => false,
                ],
                [
                    'slug' => 'nhs-trust',
                    'brand_name' => 'Midlands Health Language Desk',
                    'brand_tagline' => 'NHS Trust Clinical Interpreting & Compliance Suite',
                    'logo_text' => 'NHS-LD',
                    'primary_color' => '#005EB8',
                    'accent_color' => '#00A499',
                    'currency_symbol' => '£',
                    'default_vat_rate' => 20.00,
                    'chaser_mode' => 'review',
                    'active_delivery_stage' => 4,
                    'is_active' => false,
                ],
                [
                    'slug' => 'lex-courts',
                    'brand_name' => 'LexBridge Legal & Court Interpreting',
                    'brand_tagline' => 'MOJ & Tribunal Compliant Language Dispatch & Audit',
                    'logo_text' => 'LEX',
                    'primary_color' => '#0F172A',
                    'accent_color' => '#D97706',
                    'currency_symbol' => '£',
                    'default_vat_rate' => 20.00,
                    'chaser_mode' => 'auto',
                    'active_delivery_stage' => 4,
                    'is_active' => false,
                ],
            ];
            foreach ($presets as $p) {
                TenantSetting::updateOrCreate(['slug' => $p['slug']], $p);
            }

            // 3. Built-in Price Book (Rate Cards)
            $rateCards = [
                [
                    'name' => 'NHS & Council — Face to Face Standard',
                    'role_type' => 'client',
                    'service_type' => 'Face to Face',
                    'qualification_level' => 'All Levels',
                    'daytime_rate' => 48.00,
                    'ooh_rate' => 68.00,
                    'minimum_hours' => 1.00,
                    'travel_rate_hourly' => 24.00,
                    'mileage_rate' => 0.45,
                    'cancellation_min_hours' => 2.00,
                    'is_default' => true,
                ],
                [
                    'name' => 'Interpreter Pay — Face to Face Standard',
                    'role_type' => 'interpreter',
                    'service_type' => 'Face to Face',
                    'qualification_level' => 'Community & Court',
                    'daytime_rate' => 26.00,
                    'ooh_rate' => 38.00,
                    'minimum_hours' => 1.00,
                    'travel_rate_hourly' => 14.00,
                    'mileage_rate' => 0.45,
                    'cancellation_min_hours' => 1.50,
                    'is_default' => true,
                ],
                [
                    'name' => 'BSL Specialist — Client Rate Card',
                    'role_type' => 'client',
                    'service_type' => 'BSL',
                    'qualification_level' => 'NRCPD Registered',
                    'daytime_rate' => 75.00,
                    'ooh_rate' => 98.00,
                    'minimum_hours' => 2.00,
                    'travel_rate_hourly' => 30.00,
                    'mileage_rate' => 0.45,
                    'cancellation_min_hours' => 3.00,
                    'is_default' => false,
                ],
                [
                    'name' => 'BSL Specialist — Interpreter Rate Card',
                    'role_type' => 'interpreter',
                    'service_type' => 'BSL',
                    'qualification_level' => 'NRCPD Registered',
                    'daytime_rate' => 48.00,
                    'ooh_rate' => 64.00,
                    'minimum_hours' => 2.00,
                    'travel_rate_hourly' => 20.00,
                    'mileage_rate' => 0.45,
                    'cancellation_min_hours' => 2.00,
                    'is_default' => false,
                ],
                [
                    'name' => 'Telephone / Video — Client Rate Card',
                    'role_type' => 'client',
                    'service_type' => 'Video Interpreting',
                    'qualification_level' => 'All Levels',
                    'daytime_rate' => 38.00,
                    'ooh_rate' => 52.00,
                    'minimum_hours' => 0.50,
                    'travel_rate_hourly' => 0.00,
                    'mileage_rate' => 0.00,
                    'cancellation_min_hours' => 1.00,
                    'is_default' => false,
                ],
                [
                    'name' => 'Telephone / Video — Interpreter Rate Card',
                    'role_type' => 'interpreter',
                    'service_type' => 'Video Interpreting',
                    'qualification_level' => 'All Levels',
                    'daytime_rate' => 21.00,
                    'ooh_rate' => 30.00,
                    'minimum_hours' => 0.50,
                    'travel_rate_hourly' => 0.00,
                    'mileage_rate' => 0.00,
                    'cancellation_min_hours' => 1.00,
                    'is_default' => false,
                ],
            ];
            foreach ($rateCards as $rc) {
                RateCard::updateOrCreate(
                    ['name' => $rc['name'], 'role_type' => $rc['role_type']],
                    $rc
                );
            }

            // 4. Create Companies & Demo Users (Super-Admin, Admin, Interpreter, Client)
            $superAdminUser = User::updateOrCreate(
                ['email' => 'superadmin@gramef.com'],
                [
                    'title' => 0,
                    'first_name' => 'Muyiwa',
                    'last_name' => 'Director',
                    'password' => 'Gramef2026!',
                    'enabled' => 1,
                    'email_verified_at' => now(),
                ]
            );
            $superAdminUser->syncRoles(['super-admin', 'admin']);

            $adminUser = User::updateOrCreate(
                ['email' => 'admin@gramef.com'],
                [
                    'title' => 1,
                    'first_name' => 'Abida',
                    'last_name' => 'Operations',
                    'password' => 'Gramef2026!',
                    'enabled' => 1,
                    'email_verified_at' => now(),
                ]
            );
            $adminUser->syncRoles(['admin']);

            // Clients
            $nhsCompany = Company::firstOrCreate(
                ['name' => 'Birmingham & Solihull NHS Trust'],
                [
                    'vat_number' => 'GB654892110',
                    'company_number' => 'NHS-BS-01',
                    'address_line_1' => 'Mindelsohn Way, Edgbaston',
                    'county' => 'West Midlands',
                    'postcode' => 'B15 2GW',
                    'email' => 'ap@uhb-demo.nhs.uk',
                ]
            );

            $councilCompany = Company::firstOrCreate(
                ['name' => 'Manchester City Council Social Care'],
                [
                    'vat_number' => 'GB881234901',
                    'company_number' => 'MCC-SC-04',
                    'address_line_1' => 'Albert Square',
                    'county' => 'Greater Manchester',
                    'postcode' => 'M60 2LA',
                    'email' => 'remittances@manchester-demo.gov.uk',
                ]
            );

            $courtCompany = Company::firstOrCreate(
                ['name' => 'HMCTS Midlands Magistrates Court'],
                [
                    'vat_number' => 'GB112004509',
                    'company_number' => 'HMCTS-MID-02',
                    'address_line_1' => 'Victoria Law Courts, Corporation St',
                    'county' => 'West Midlands',
                    'postcode' => 'B4 6QA',
                    'email' => 'finance@hmcts-demo.gov.uk',
                ]
            );

            $clientUser = User::updateOrCreate(
                ['email' => 'client@gramef.com'],
                [
                    'title' => 3,
                    'first_name' => 'Sarah',
                    'last_name' => 'Jenkins',
                    'password' => 'Gramef2026!',
                    'enabled' => 1,
                    'email_verified_at' => now(),
                ]
            );
            $clientUser->syncRoles(['client']);

            $primaryClient = Client::updateOrCreate(
                ['user_id' => $clientUser->id],
                [
                    'contact_number' => '0121 371 2000',
                    'client_address_line_1' => 'Queen Elizabeth Hospital, Mindelsohn Way',
                    'client_address_line_2' => 'Outpatients Department',
                    'client_county' => 'West Midlands',
                    'client_postcode' => 'B15 2GW',
                    'seen_terms' => 1,
                    'archived' => 0,
                    'show_agents' => 1,
                    'client_category' => 'NHS Trust',
                ]
            );

            Organisation::updateOrCreate(
                ['client_id' => $primaryClient->id],
                [
                    'organisation_company' => 'Birmingham & Solihull NHS Trust',
                    'vat_number' => 'GB654892110',
                    'organisation_address_line_1' => 'Mindelsohn Way',
                    'organisation_county' => 'West Midlands',
                    'organisation_postcode' => 'B15 2GW',
                    'organisation_email' => 'ap@uhb-demo.nhs.uk',
                    'company_id' => $nhsCompany->id,
                ]
            );

            $councilClientUser = User::updateOrCreate(
                ['email' => 'council@gramef.com'],
                [
                    'title' => 0,
                    'first_name' => 'David',
                    'last_name' => 'Thorne',
                    'password' => 'Gramef2026!',
                    'enabled' => 1,
                    'email_verified_at' => now(),
                ]
            );
            $councilClientUser->syncRoles(['client']);
            $councilClient = Client::updateOrCreate(
                ['user_id' => $councilClientUser->id],
                [
                    'contact_number' => '0161 234 5000',
                    'client_address_line_1' => 'Town Hall Extension, Albert Square',
                    'client_county' => 'Greater Manchester',
                    'client_postcode' => 'M60 2LA',
                    'seen_terms' => 1,
                    'archived' => 0,
                    'client_category' => 'Local Council',
                ]
            );
            Organisation::updateOrCreate(
                ['client_id' => $councilClient->id],
                [
                    'organisation_company' => 'Manchester City Council Social Care',
                    'organisation_email' => 'remittances@manchester-demo.gov.uk',
                    'company_id' => $councilCompany->id,
                ]
            );

            // Interpreters / Agents
            $agentProfiles = [
                [
                    'email' => 'interpreter@gramef.com',
                    'first_name' => 'Amira',
                    'last_name' => 'Hassan',
                    'title' => 4,
                    'gender' => 1,
                    'postcode' => 'B15 3TT',
                    'county' => 'West Midlands',
                    'dbs_number' => '001849203411',
                    'dbs_expiry_date' => now()->addDays(24)->toDateString(), // 30-day renewal alert!
                    'reliability' => 99.4,
                    'on_time' => 99.8,
                    'sort_code' => '20-08-44',
                    'account_number' => '40918273',
                    'langs' => ['Arabic', 'French', 'Kurdish (Sorani)'],
                ],
                [
                    'email' => 'piotr.kowalski@gramef.com',
                    'first_name' => 'Piotr',
                    'last_name' => 'Kowalski',
                    'title' => 0,
                    'gender' => 0,
                    'postcode' => 'B4 7AA',
                    'county' => 'West Midlands',
                    'dbs_number' => '001792841102',
                    'dbs_expiry_date' => now()->addDays(52)->toDateString(), // 60-day alert!
                    'reliability' => 98.1,
                    'on_time' => 98.5,
                    'sort_code' => '40-11-22',
                    'account_number' => '81726354',
                    'langs' => ['Polish', 'Ukrainian', 'Russian'],
                ],
                [
                    'email' => 'tariq.mahmood@gramef.com',
                    'first_name' => 'Tariq',
                    'last_name' => 'Mahmood',
                    'title' => 0,
                    'gender' => 0,
                    'postcode' => 'M14 5PL',
                    'county' => 'Greater Manchester',
                    'dbs_number' => '001902331845',
                    'dbs_expiry_date' => now()->addDays(420)->toDateString(), // Valid
                    'reliability' => 97.6,
                    'on_time' => 97.9,
                    'sort_code' => '30-94-12',
                    'account_number' => '55219087',
                    'langs' => ['Urdu', 'Punjabi', 'Hindi'],
                ],
                [
                    'email' => 'elena.popescu@gramef.com',
                    'first_name' => 'Elena',
                    'last_name' => 'Popescu',
                    'title' => 1,
                    'gender' => 1,
                    'postcode' => 'B1 1HQ',
                    'county' => 'West Midlands',
                    'dbs_number' => '001644890211',
                    'dbs_expiry_date' => now()->subDays(12)->toDateString(), // Expired!
                    'reliability' => 95.8,
                    'on_time' => 96.2,
                    'sort_code' => '60-12-35',
                    'account_number' => '77412098',
                    'langs' => ['Romanian', 'Spanish', 'Portuguese'],
                ],
            ];

            $agents = [];
            foreach ($agentProfiles as $ap) {
                $u = User::updateOrCreate(
                    ['email' => $ap['email']],
                    [
                        'title' => $ap['title'],
                        'first_name' => $ap['first_name'],
                        'last_name' => $ap['last_name'],
                        'password' => 'Gramef2026!',
                        'enabled' => 1,
                        'email_verified_at' => now(),
                    ]
                );
                $u->syncRoles(['agent', 'qualified-interpreter']);

                $ag = Agent::updateOrCreate(
                    ['user_id' => $u->id],
                    [
                        'contact_number' => '07700 900' . rand(100, 999),
                        'address_line_1' => '14 Victoria Terrace',
                        'county' => $ap['county'],
                        'postcode' => $ap['postcode'],
                        'gender' => $ap['gender'],
                        'profile_picture' => '/img/agents.svg',
                        'dbs_number' => $ap['dbs_number'],
                        'dbs_expiry_date' => $ap['dbs_expiry_date'],
                        'dbs_level_id' => 1,
                        'induction_date' => now()->subMonths(8)->toDateString(),
                        'reliability_score' => $ap['reliability'],
                        'on_time_rate' => $ap['on_time'],
                    ]
                );

                // Attach skills & languages
                $ag->skills()->sync([1, 3, 4]);
                $syncLangs = [];
                foreach ($ap['langs'] as $ln) {
                    if (isset($langIds[$ln])) {
                        $syncLangs[] = $langIds[$ln];
                    }
                }
                if (!empty($syncLangs)) {
                    $ag->languages()->sync($syncLangs);
                }

                BankAccount::updateOrCreate(
                    ['user_id' => $u->id],
                    [
                        'bank_name' => 'Barclays UK',
                        'account_holder' => $ap['first_name'] . ' ' . $ap['last_name'],
                        'account_number' => $ap['account_number'],
                        'sort_code' => $ap['sort_code'],
                        'consent' => 1,
                        'consent_at' => now(),
                    ]
                );

                $agents[] = ['agent' => $ag, 'profile' => $ap];
            }

            $primaryAgent = $agents[0]['agent'];
            $secondAgent = $agents[1]['agent'];
            $thirdAgent = $agents[2]['agent'];

            // 5. Interpreter Jobs showcasing all 6 Deliverables (GREEN, AMBER, Live GPS Check-In, Urgent Unassigned)
            $jobFixtures = [
                [
                    'ref' => 'NHS-QE-24810',
                    'client' => $primaryClient,
                    'agent' => $primaryAgent,
                    'lang' => 'Arabic',
                    'skill_id' => 1,
                    'status' => 4,
                    'date' => now('Europe/London')->subDays(3)->toDateString(),
                    'start' => '09:30',
                    'hrs' => 2,
                    'mins' => 0,
                    'dept' => 'Cardiology Outpatients, Clinic B',
                    'address' => 'Queen Elizabeth Hospital, Mindelsohn Way',
                    'postcode' => 'B15 2GW',
                    'checked_in_at' => now('Europe/London')->subDays(3)->setTime(9, 24),
                    'checked_out_at' => now('Europe/London')->subDays(3)->setTime(11, 32),
                    'ts_hrs' => 2,
                    'ts_mins' => 0,
                    'agent_claim' => 70.00,
                    'client_charge' => 118.00,
                    'tl_status' => 'green',
                    'tl_warnings' => [],
                    'ocr' => ['receipt_amount' => 4.50, 'claimed_expense' => 4.50],
                    'magic_token' => 'gramef-demo-sign-green-01',
                ],
                [
                    'ref' => 'NHS-WOM-24811',
                    'client' => $primaryClient,
                    'agent' => $primaryAgent,
                    'lang' => 'Arabic',
                    'skill_id' => 1,
                    'status' => 4,
                    'date' => now('Europe/London')->subDays(2)->toDateString(),
                    'start' => '14:00',
                    'hrs' => 1,
                    'mins' => 30,
                    'dept' => 'Maternity Assessment Unit',
                    'address' => 'Birmingham Women’s Hospital, Metchley Park Rd',
                    'postcode' => 'B15 2TG',
                    'checked_in_at' => now('Europe/London')->subDays(2)->setTime(13, 56),
                    'checked_out_at' => now('Europe/London')->subDays(2)->setTime(15, 35),
                    'ts_hrs' => 1,
                    'ts_mins' => 30,
                    'agent_claim' => 104.50, // Claimed 3h + £14.50 parking!
                    'client_charge' => 86.50,
                    'tl_status' => 'amber',
                    'tl_warnings' => [
                        'Interpreter claimed 3h 00m (£104.50), but signed timesheet duration is 1h 30m.',
                        'Parking receipt scan shows £4.50, but £14.50 was claimed.',
                        'Warning: Interpreter pay (£104.50) is higher than Client Invoice (£86.50).',
                    ],
                    'ocr' => ['receipt_amount' => 4.50, 'claimed_expense' => 14.50],
                    'magic_token' => 'gramef-demo-sign-amber-02',
                ],
                [
                    'ref' => 'MCC-SOC-24812',
                    'client' => $councilClient,
                    'agent' => $secondAgent,
                    'lang' => 'Polish',
                    'skill_id' => 1,
                    'status' => 4,
                    'date' => now('Europe/London')->subDays(1)->toDateString(),
                    'start' => '18:30', // Out of hours!
                    'hrs' => 2,
                    'mins' => 0,
                    'dept' => 'Emergency Duty Social Care Team',
                    'address' => '12 Oakfield Avenue, Rusholme',
                    'postcode' => 'M14 5PR',
                    'checked_in_at' => now('Europe/London')->subDays(1)->setTime(18, 25),
                    'checked_out_at' => now('Europe/London')->subDays(1)->setTime(20, 30),
                    'ts_hrs' => 2,
                    'ts_mins' => 0,
                    'agent_claim' => 85.70,
                    'client_charge' => 150.70,
                    'tl_status' => 'green',
                    'tl_warnings' => [],
                    'ocr' => ['receipt_amount' => 0.00, 'claimed_expense' => 0.00],
                    'magic_token' => 'gramef-demo-sign-ooh-03',
                ],
                [
                    'ref' => 'NHS-ONC-24815',
                    'client' => $primaryClient,
                    'agent' => $primaryAgent,
                    'lang' => 'Arabic',
                    'skill_id' => 1,
                    'status' => 1, // Live Checked In On-Site!
                    'date' => now('Europe/London')->toDateString(),
                    'start' => '11:00',
                    'hrs' => 1,
                    'mins' => 30,
                    'dept' => 'Oncology Day Unit',
                    'address' => 'Queen Elizabeth Hospital, Mindelsohn Way',
                    'postcode' => 'B15 2GW',
                    'checked_in_at' => now('Europe/London')->subMinutes(25),
                    'checked_out_at' => null,
                    'ts_hrs' => 1,
                    'ts_mins' => 30,
                    'agent_claim' => 48.00,
                    'client_charge' => 88.00,
                    'tl_status' => 'green',
                    'tl_warnings' => [],
                    'ocr' => null,
                    'magic_token' => 'gramef-demo-sign-live-04',
                ],
                [
                    'ref' => 'NHS-URG-24820',
                    'client' => $primaryClient,
                    'agent' => null, // Urgent unassigned (<24h)!
                    'lang' => 'Arabic',
                    'skill_id' => 1,
                    'status' => 0,
                    'date' => now('Europe/London')->addHours(14)->toDateString(),
                    'start' => '09:00',
                    'hrs' => 2,
                    'mins' => 0,
                    'dept' => 'Paediatric Emergency Dept',
                    'address' => 'Birmingham Children’s Hospital, Steelhouse Lane',
                    'postcode' => 'B4 6NH',
                    'checked_in_at' => null,
                    'checked_out_at' => null,
                    'ts_hrs' => 0,
                    'ts_mins' => 0,
                    'agent_claim' => 62.00,
                    'client_charge' => 112.00,
                    'tl_status' => null,
                    'tl_warnings' => [],
                    'ocr' => null,
                    'magic_token' => null,
                ],
                [
                    'ref' => 'MCC-FAM-24821',
                    'client' => $councilClient,
                    'agent' => $thirdAgent,
                    'lang' => 'Urdu',
                    'skill_id' => 4,
                    'status' => 1, // Assigned & Confirmed Yesterday
                    'date' => now('Europe/London')->addDays(1)->toDateString(),
                    'start' => '10:30',
                    'hrs' => 1,
                    'mins' => 0,
                    'dept' => 'Family Safeguarding Conference',
                    'address' => 'Microsoft Teams Video Room',
                    'postcode' => 'M60 2LA',
                    'checked_in_at' => null,
                    'checked_out_at' => null,
                    'ts_hrs' => 0,
                    'ts_mins' => 0,
                    'agent_claim' => 21.00,
                    'client_charge' => 38.00,
                    'tl_status' => 'green',
                    'tl_warnings' => [],
                    'ocr' => null,
                    'magic_token' => null,
                ],
            ];

            $seededJobs = [];
            foreach ($jobFixtures as $jf) {
                $job = InterpreterJob::updateOrCreate(
                    ['client_reference' => $jf['ref']],
                    [
                        'client_id' => $jf['client']->id,
                        'agent_id' => optional($jf['agent'])->id,
                        'skill_id' => $jf['skill_id'],
                        'require_qualified' => 2,
                        'security_type_id' => 1,
                        'from_language_id' => 37,
                        'to_language_id' => $langIds[$jf['lang']] ?? 1,
                        'status' => $jf['status'],
                        'gender' => 2,
                        'appointment_date' => $jf['date'],
                        'start_time' => $jf['start'],
                        'duration_hours' => $jf['hrs'],
                        'duration_minutes' => $jf['mins'],
                        'department' => $jf['dept'],
                        'address_line_1' => $jf['address'],
                        'county' => 'West Midlands',
                        'postcode' => $jf['postcode'],
                        'contact_information' => 'Reception Desk — Ext 4412',
                        'checked_in_at' => $jf['checked_in_at'],
                        'checked_out_at' => $jf['checked_out_at'],
                        'calculated_interpreter_pay' => $jf['agent_claim'],
                        'calculated_client_charge' => $jf['client_charge'],
                        'calculated_margin' => round($jf['client_charge'] - $jf['agent_claim'], 2),
                        'traffic_light_status' => $jf['tl_status'],
                        'traffic_light_warnings' => json_encode($jf['tl_warnings']),
                        'client_confirmed_at' => $jf['status'] >= 1 ? now()->subDay() : null,
                    ]
                );

                // Seed matched agent entries for the urgent unassigned job so Travel-Time Advisory shows
                if ($jf['status'] === 0) {
                    MatchedAgent::updateOrCreate(
                        ['job_id' => $job->id, 'job_type' => 'App\InterpreterJob', 'agent_id' => $primaryAgent->id],
                        ['status' => 0, 'distance' => 1.8]
                    );
                    MatchedAgent::updateOrCreate(
                        ['job_id' => $job->id, 'job_type' => 'App\InterpreterJob', 'agent_id' => $secondAgent->id],
                        ['status' => 0, 'distance' => 2.4]
                    );
                }

                if ($jf['agent'] && $jf['magic_token']) {
                    $ts = Timesheet::updateOrCreate(
                        ['job_id' => $job->id],
                        [
                            'client_id' => $jf['client']->id,
                            'agent_id' => $jf['agent']->id,
                            'from_language_id' => 37,
                            'to_language_id' => $langIds[$jf['lang']] ?? 1,
                            'client_reference' => $jf['ref'],
                            'appointment_date' => $jf['date'],
                            'start_time' => $jf['start'],
                            'end_time' => '11:30',
                            'duration_hours' => $jf['hrs'],
                            'duration_minutes' => $jf['mins'],
                            'agent_duration_hours' => $jf['ts_hrs'],
                            'agent_duration_minutes' => $jf['ts_mins'],
                            'client_duration_hours' => $jf['ts_hrs'],
                            'client_duration_minutes' => $jf['ts_mins'],
                            'status' => $jf['status'] === 4 ? 'verified' : 'pending',
                            'agent_status' => 'Y',
                            'client_status' => $jf['status'] === 4 ? 'Y' : 'N',
                            'agent_signature' => $jf['status'] === 4 ? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="140" height="40"><path d="M8 28 Q 35 5, 62 26 T 130 18" stroke="#0f172a" stroke-width="2" fill="none"/></svg>') : null,
                            'client_signature' => $jf['status'] === 4 ? 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="140" height="40"><path d="M10 22 C 30 8, 55 34, 85 16 S 120 26, 132 14" stroke="#2563eb" stroke-width="2" fill="none"/></svg>') : null,
                            'client_name' => $jf['status'] === 4 ? 'Dr. Sarah Jenkins' : null,
                            'client_designation' => $jf['status'] === 4 ? 'Consultant Clinician' : null,
                            'magic_sign_token' => $jf['magic_token'],
                            'magic_sign_expires_at' => now()->addDays(14),
                            'ocr_confidence' => $jf['ocr'] ? 97 : null,
                            'ocr_extracted_json' => $jf['ocr'] ? json_encode($jf['ocr']) : null,
                        ]
                    );

                    AgentJobEntry::updateOrCreate(
                        ['job_id' => $job->id, 'job_type' => 'App\InterpreterJob', 'agent_id' => $jf['agent']->id],
                        [
                            'base_amount' => $jf['agent_claim'] - ($jf['ocr']['claimed_expense'] ?? 0),
                            'expenses' => $jf['ocr']['claimed_expense'] ?? 0,
                            'surcharge' => 0,
                            'other_charges' => 0,
                            'total_amount' => $jf['agent_claim'],
                            'notes' => 'Submitted via mobile companion',
                            'invoiced' => 0,
                        ]
                    );
                }

                $seededJobs[] = $job;
            }

            // 6. Client Invoices across Current, 7d, 14d, 30d, 60+d overdue buckets (Deliverable 3 & 4)
            $invoiceFixtures = [
                ['num' => 'CI-104201', 'client' => $primaryClient, 'company' => $nhsCompany, 'days_ago' => 78, 'total' => 1420.00, 'status' => 'sent', 'token' => 'gramef-remit-104201'],
                ['num' => 'CI-104202', 'client' => $councilClient, 'company' => $councilCompany, 'days_ago' => 48, 'total' => 890.50, 'status' => 'sent', 'token' => 'gramef-remit-104202'],
                ['num' => 'CI-104203', 'client' => $primaryClient, 'company' => $nhsCompany, 'days_ago' => 31, 'total' => 645.00, 'status' => 'sent', 'token' => 'gramef-remit-104203'],
                ['num' => 'CI-104204', 'client' => $councilClient, 'company' => $councilCompany, 'days_ago' => 23, 'total' => 512.00, 'status' => 'sent', 'token' => 'gramef-remit-104204'],
                ['num' => 'CI-104205', 'client' => $primaryClient, 'company' => $nhsCompany, 'days_ago' => 11, 'total' => 384.00, 'status' => 'sent', 'token' => 'gramef-remit-104205'],
                ['num' => 'CI-104206', 'client' => $primaryClient, 'company' => $nhsCompany, 'days_ago' => 4, 'total' => 240.00, 'status' => 'sent', 'token' => 'gramef-remit-104206'],
                ['num' => 'CI-104207', 'client' => $councilClient, 'company' => $councilCompany, 'days_ago' => 20, 'total' => 760.00, 'status' => 'paid', 'token' => 'gramef-remit-104207'],
            ];

            foreach ($invoiceFixtures as $idx => $inf) {
                $invDate = Carbon::today('Europe/London')->subDays($inf['days_ago']);
                $dueDate = $invDate->copy()->addDays(14);
                $sub = round($inf['total'] / 1.2, 2);
                $vat = round($inf['total'] - $sub, 2);

                $ci = ClientInvoice::updateOrCreate(
                    ['invoice_number' => $inf['num']],
                    [
                        'client_id' => $inf['client']->id,
                        'company_id' => $inf['company']->id,
                        'invoice_date' => $invDate->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'date_range_start' => $invDate->copy()->startOfMonth()->toDateString(),
                        'date_range_end' => $invDate->copy()->endOfMonth()->toDateString(),
                        'template' => 'v3',
                        'include_vat' => true,
                        'vat_rate' => 20.00,
                        'subtotal' => $sub,
                        'vat_amount' => $vat,
                        'total' => $inf['total'],
                        'status' => $inf['status'],
                        'sent_at' => $invDate,
                        'chaser_paused' => $inf['status'] === 'paid',
                        'remittance_magic_token' => $inf['token'],
                    ]
                );

                if ($ci->items()->count() === 0) {
                    ClientInvoiceItem::create([
                        'client_invoice_id' => $ci->id,
                        'job_id' => $seededJobs[0]->id ?? null,
                        'job_type' => 'App\InterpreterJob',
                        'description' => 'NHS / Council Interpreting Services Batch (' . $inf['num'] . ')',
                        'amount' => $sub,
                        'surcharge' => 0,
                        'other_charges' => 0,
                        'vat_applicable' => true,
                    ]);
                }
            }

            // 7. Payslips ready for Traffic-Light approval & 1-Click Bank Payout Sheet (Deliverable 3C)
            $payslipFixtures = [
                ['num' => 'PS-202611-01', 'agent' => $primaryAgent, 'bank' => $agents[0]['profile'], 'total' => 482.50, 'status' => 'approved'],
                ['num' => 'PS-202611-02', 'agent' => $secondAgent, 'bank' => $agents[1]['profile'], 'total' => 365.70, 'status' => 'approved'],
                ['num' => 'PS-202611-03', 'agent' => $thirdAgent, 'bank' => $agents[2]['profile'], 'total' => 294.00, 'status' => 'approved'],
                ['num' => 'PS-202611-04', 'agent' => $primaryAgent, 'bank' => $agents[0]['profile'], 'total' => 174.50, 'status' => 'pending'],
            ];

            foreach ($payslipFixtures as $pf) {
                Payslip::updateOrCreate(
                    ['payslip_number' => $pf['num']],
                    [
                        'agent_id' => $pf['agent']->id,
                        'period_start' => now('Europe/London')->startOfMonth()->toDateString(),
                        'period_end' => now('Europe/London')->endOfMonth()->toDateString(),
                        'items' => [['description' => 'Interpreting & Travel Payout', 'amount' => $pf['total']]],
                        'subtotal' => $pf['total'],
                        'adjustments_total' => 0,
                        'total' => $pf['total'],
                        'status' => $pf['status'],
                        'bank_snapshot' => [
                            'bank_name' => 'Barclays UK',
                            'sort_code' => $pf['bank']['sort_code'],
                            'account_number' => $pf['bank']['account_number'],
                        ],
                        'approved_by' => $pf['status'] === 'approved' ? $adminUser->id : null,
                        'approved_at' => $pf['status'] === 'approved' ? now() : null,
                    ]
                );
            }
        });
    }
}
