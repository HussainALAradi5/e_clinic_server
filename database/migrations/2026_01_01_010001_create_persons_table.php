<?php

use App\Shared\Database\Constraints;
use App\Shared\Enums\Gender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › People: root record of every human (patients and staff). Personal data lives here only.
     */
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->version();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('first_name_ar', 100)->nullable();
            $table->string('last_name_ar', 100)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->text('national_id')->nullable()->comment('encrypted');
            $table->string('national_id_hash', 64)->nullable()->unique()->comment('HMAC blind index');
            $table->char('nationality_code', 2)->nullable()->index()->comment('FK countries.code (Reference Data)');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->uuid('address_id')->nullable()->index()->comment('FK addresses.id (Reference Data)');
            $table->char('language', 2)->default('en');
            $table->jsonb('notification_opt_out')->default(new Expression("'[]'"));

            $table->index(['phone', 'date_of_birth'], 'persons_phone_dob_index');
            $table->index(['last_name', 'first_name'], 'persons_name_index');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('deleted_by');
        });

        // One login / contact email per living person (case-insensitive).
        DB::statement(Constraints::isPostgres()
            ? 'CREATE UNIQUE INDEX persons_email_unique ON persons (lower(email)) WHERE email IS NOT NULL AND is_deleted = false'
            : 'CREATE UNIQUE INDEX persons_email_unique ON persons (lower(email)) WHERE email IS NOT NULL AND is_deleted = 0');

        Constraints::enum('persons', 'gender', Gender::class);
        Constraints::check('persons', 'chk_persons_dob', 'date_of_birth <= CURRENT_DATE');
        Constraints::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        Constraints::statement("CREATE INDEX persons_search_trgm ON persons USING gin ((first_name || ' ' || last_name) gin_trgm_ops)");
    }

    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
