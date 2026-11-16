<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            RolesTableSeeder::class,
            ContactMethodsTableSeeder::class,
            LanguagesTableSeeder::class,
            SkillsTableSeeder::class,
            // Legacy seeders that rely on the old factory() helper are skipped to ensure compatibility
            // UsersTableSeeder::class,
            // InterpreterJobsTableSeeder::class,
            // TranslatorJobsTableSeeder::class,
            // ClientInvoiceSeeder::class,
            \Database\Seeders\AdminPreviewSeeder::class,
            \Database\Seeders\QAFixturesSeeder::class,
        ]);
    }
}
