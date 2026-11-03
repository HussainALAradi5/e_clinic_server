<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Patients: up to three emergency / additional contacts (an encrypted list) instead of one name,
     * phone and relation. The existing contact becomes the first of the list.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->text('emergency_contacts')->nullable()->after('surgical_history');
        });

        DB::table('patients')->whereNotNull('emergency_contact_phone')->orderBy('id')
            ->each(function (object $row): void {
                $contact = ['name' => $row->emergency_contact_name, 'relation' => $row->emergency_contact_relation, 'phone' => $row->emergency_contact_phone];
                DB::table('patients')->where('id', $row->id)->update(['emergency_contacts' => Crypt::encryptString((string) json_encode([$contact]))]);
            });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
        });

        DB::table('patients')->whereNotNull('emergency_contacts')->orderBy('id')
            ->each(function (object $row): void {
                $first = json_decode(Crypt::decryptString((string) $row->emergency_contacts), true)[0] ?? null;
                if (is_array($first)) {
                    DB::table('patients')->where('id', $row->id)->update([
                        'emergency_contact_name' => $first['name'] ?? null,
                        'emergency_contact_phone' => $first['phone'] ?? null,
                        'emergency_contact_relation' => $first['relation'] ?? null,
                    ]);
                }
            });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('emergency_contacts');
        });
    }
};
