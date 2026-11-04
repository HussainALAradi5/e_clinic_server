<?php

declare(strict_types=1);

use App\Enums\Documents\DocumentType;
use App\Enums\Documents\WrittenDocumentKind;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Utilities\Database\Constraints;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Documents written at a visit:
 * - document_templates (TPL-000001): reusable rich-text wording with {{placeholders}} — ready-made ones
 *   (`is_system`) and the ones staff write. Rights `templates.*`.
 * - written_documents (WRD-2026-000001): a medical report, attendance / fitness certificate, referral note or letter
 *   written from the visit by its provider; body encrypted. Rights `written_documents.*`.
 * Also widens the generated document types with `written_document`.
 */
return new class extends Migration
{
    private const RESOURCES = ['templates', 'written_documents'];

    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('kind', 30);
            $table->text('body');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->index(['kind', 'is_active']);
        });
        Constraints::enum('document_templates', 'kind', WrittenDocumentKind::class);

        Schema::create('written_documents', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('author_id')->constrained('employees')->restrictOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->string('kind', 30);
            $table->string('title', 160);
            // Encrypted rich text (PHI).
            $table->text('body');
            $table->timestampTz('issued_at');
            $table->index(['patient_id', 'issued_at']);
            $table->index(['author_id', 'issued_at']);
        });
        Constraints::enum('written_documents', 'kind', WrittenDocumentKind::class);

        Constraints::statement('ALTER TABLE generated_documents DROP CONSTRAINT IF EXISTS chk_generated_documents_type');
        Constraints::enum('generated_documents', 'type', DocumentType::class);

        $this->grantRights();
        (new DocumentTemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('written_documents');
        Schema::dropIfExists('document_templates');
    }

    /** Existing databases: the new rights to the roles that have them in config/access.php. */
    private function grantRights(): void
    {
        $guard = Config::string('access.guard');
        if (Role::query()->where('guard_name', $guard)->doesntExist()) {
            return;
        }
        Model::preventLazyLoading(false);
        /** @var array<string, array{access: string|array<string, list<string>>}> $roles */
        $roles = Config::array('access.roles');
        $flags = ['read_own', 'read_all', 'create', 'write', 'delete'];
        foreach (self::RESOURCES as $resource) {
            foreach ($flags as $flag) {
                Permission::query()->firstOrCreate(['name' => "{$resource}.{$flag}", 'guard_name' => $guard], ['module' => 'appointments', 'description' => ucfirst(str_replace('_', ' ', $flag)).' — '.str_replace('_', ' ', $resource)]);
            }
            foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($roles))->get() as $role) {
                $access = $roles[$role->name]['access'];
                $grant = array_map(fn (string $flag): string => "{$resource}.{$flag}", $access === '*' ? $flags : ($access[$resource] ?? []));
                if ($grant !== []) {
                    $role->givePermissionTo($grant);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
