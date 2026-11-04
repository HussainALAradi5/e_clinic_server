<?php

declare(strict_types=1);

use App\Enums\Payments\PatientPackageStatus;
use App\Models\Accounting\Account;
use App\Utilities\Access\RightsGranter;
use App\Utilities\Database\Constraints;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepaid packages: the catalog (PKG — so many sessions of one service for a set price, valid so many days),
 * what patients bought (PPK) and each session used (one per visit). Adds 2510 Unearned package revenue and the
 * `packages.*` / `patient_packages.*` rights.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->foreignUuid('covered_service_id')->constrained('billable_services')->restrictOnDelete();
            $table->foreignUuid('sale_service_id')->unique()->constrained('billable_services')->restrictOnDelete();
            $table->unsignedSmallInteger('sessions');
            $table->money('price');
            $table->unsignedSmallInteger('validity_days');
            $table->boolean('is_active')->default(true);
        });
        Constraints::check('service_packages', 'chk_service_packages_values', 'sessions BETWEEN 2 AND 100 AND price > 0 AND validity_days BETWEEN 7 AND 730');

        Schema::create('patient_packages', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('service_package_id')->constrained('service_packages')->restrictOnDelete();
            $table->foreignUuid('covered_service_id')->constrained('billable_services')->restrictOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sessions_total');
            $table->unsignedSmallInteger('sessions_used')->default(0);
            $table->money('price');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->index(['patient_id', 'status']);
        });
        Constraints::enum('patient_packages', 'status', PatientPackageStatus::class);
        Constraints::check('patient_packages', 'chk_patient_packages_sessions', 'sessions_used <= sessions_total');

        Schema::create('package_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_package_id')->constrained('patient_packages')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->unique()->constrained('appointments')->restrictOnDelete();
            $table->money('value');
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestampsTz();
        });

        if (Account::query()->exists()) {
            (new ChartOfAccountsSeeder)->run();
        }
        RightsGranter::grant('packages', ['read_own', 'read_all', 'create', 'write', 'delete'], 'payments', 'package catalog');
        RightsGranter::grant('patient_packages', ['read_own', 'read_all', 'create', 'write', 'delete'], 'payments', 'patients\' packages');
    }

    public function down(): void
    {
        Schema::dropIfExists('package_sessions');
        Schema::dropIfExists('patient_packages');
        Schema::dropIfExists('service_packages');
    }
};
