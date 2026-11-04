<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use Database\Seeders\FeeServicesSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Existing databases: the price-list items of the charged documents (medical report, fitness certificate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Account::query()->exists()) {
            app()->call([new FeeServicesSeeder, 'run']);
        }
    }

    public function down(): void
    {
        // Price-list items stay: invoices may already use them.
    }
};
