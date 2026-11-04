<?php

declare(strict_types=1);

use App\Enums\Documents\DocumentType;
use App\Enums\Identity\AttachmentCategory;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Documents:
 * - generated_documents (DOC-2026-000001): every PDF the server makes of a record (sick leave, result, invoice…),
 *   with a unique file name; the file is an attachment (category `generated_document`). Reused while the record is
 *   unchanged and the same person prints it, otherwise a new one is made.
 * - clinic_profiles (CLP-000001): the one clinic profile printed on every PDF — name, tagline, contacts, the logo and
 *   the stamp (attachments, category `clinic_logo`). Rights `clinic_profile.*` (admins).
 * - attachment categories `generated_document`, `signature` (a staff member's drawn or uploaded signature, kept on
 *   their person record) and `clinic_logo`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('type', 30);
            $table->uuid('record_id');
            $table->string('reference', 40);
            $table->string('file_name', 160)->unique();
            $table->foreignUuid('attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->timestampTz('record_updated_at')->nullable();
            $table->unsignedInteger('size_bytes');
            $table->index(['type', 'record_id', 'created_at']);
        });
        Constraints::enum('generated_documents', 'type', DocumentType::class);

        Schema::create('clinic_profiles', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 120);
            $table->string('tagline', 160)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('website', 120)->nullable();
            $table->foreignUuid('logo_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->foreignUuid('stamp_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
        });

        Constraints::statement('ALTER TABLE attachments DROP CONSTRAINT IF EXISTS chk_attachments_category');
        Constraints::enum('attachments', 'category', AttachmentCategory::class);

        $this->grantRights();
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_profiles');
        Schema::dropIfExists('generated_documents');
    }

    /** Existing databases: `clinic_profile.*` to the roles with every right (admins). */
    private function grantRights(): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);
        $names = ['clinic_profile.read_own', 'clinic_profile.read_all', 'clinic_profile.create', 'clinic_profile.write', 'clinic_profile.delete'];
        foreach ($names as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard], ['module' => 'reference_data', 'description' => ucfirst(str_replace('_', ' ', explode('.', $name)[1])).' — clinic profile']);
        }
        /** @var array<string, array{access: string|array<string, list<string>>}> $roles */
        $roles = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($roles))->get() as $role) {
            if ($roles[$role->name]['access'] === '*') {
                $role->givePermissionTo($names);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
