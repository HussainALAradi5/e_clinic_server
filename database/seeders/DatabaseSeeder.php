<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
            ChartOfAccountsSeeder::class,
            FeeServicesSeeder::class,
            FiscalYearSeeder::class,
            LeaveTypeSeeder::class,
            Icd10Seeder::class,
            DocumentTemplateSeeder::class,
        ]);

        // Demo accounts and fake data for testing (see e_clinic_documentation/DEMO_USERS.md); never in production.
        if (! app()->isProduction() || config('app.seed_demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
