<?php

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\BalanceSide;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounting › Chart of accounts: the account tree and the logical-role → account mappings of automatic postings.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('type', 20)->index();
            $table->string('normal_balance', 10);
            $table->foreignUuid('parent_id')->nullable()->index()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_group')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('level')->default(1);
            $table->text('description')->nullable();
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('account_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 40)->unique();
            $table->foreignUuid('account_id')->index()->constrained('accounts')->restrictOnDelete();
            $table->timestampsTz();
            $table->blameable();
        });

        Constraints::enum('accounts', 'type', AccountType::class);
        Constraints::enum('accounts', 'normal_balance', BalanceSide::class);
        Constraints::enum('account_mappings', 'key', AccountMappingKey::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('accounts');
    }
};
