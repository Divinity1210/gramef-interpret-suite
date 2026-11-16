<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Language;
use App\Skill;

class LanguagesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Seed a small set of languages expected by factories (IDs 1..3)
        $languages = ['English', 'Spanish', 'French'];
        foreach ($languages as $name) {
            Language::firstOrCreate(['name' => $name]);
        }

        // Seed interpreter skills expected by factories (IDs 1..6)
        $skills = [
            'General Interpretation',
            'Medical Interpretation',
            'Legal Interpretation',
            'Community Interpretation',
            'Business Interpretation',
            'Technical Interpretation',
        ];
        foreach ($skills as $index => $skillName) {
            Skill::firstOrCreate([
                'skill' => $skillName,
                'type' => 0, // interpreter
            ]);
        }
    }
}