<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transactions › Expenses are edited as drafts with an optimistic lock (HasVersion), like every other form.
     */
    public function up(): void
    {
        if (Schema::hasColumn('expenses', 'version')) {
            return;
        }
        Schema::table('expenses', function (Blueprint $table) {
            $table->version();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
