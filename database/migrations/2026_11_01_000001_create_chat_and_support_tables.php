<?php

use App\Enums\Appointments\TreatmentPriority;
use App\Enums\Communication\ConversationType;
use App\Enums\Communication\ParticipantRole;
use App\Enums\Communication\TicketCategory;
use App\Enums\Shared\GenericStatus;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Utilities\Database\Constraints;
use App\Utilities\Shared\CacheKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Communication: staff chat (one-to-one and groups) and support tickets that admins resolve. Chat rows are not
     * generic entities — messages are many and short-lived in meaning, so they skip codes, audit and archiving.
     * Existing databases also get the `chat` / `tickets` rights, granted as config/access.php does on fresh installs.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 10);
            $table->string('title', 100)->nullable();
            // Sorted pair of user ids for a direct chat, so two people never get two chats.
            $table->string('direct_key', 80)->nullable()->unique();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('last_message_at')->nullable()->index();
            $table->timestampsTz();
        });
        Constraints::enum('conversations', 'type', ConversationType::class);
        Constraints::check('conversations', 'chk_conversations_shape', "(type = 'direct' AND direct_key IS NOT NULL) OR (type = 'group' AND title IS NOT NULL)");

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('role', 10)->default(ParticipantRole::Member->value);
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->timestampsTz();
            $table->unique(['conversation_id', 'user_id']);
        });
        Constraints::enum('conversation_participants', 'role', ParticipantRole::class);

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampsTz();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->version();
            $table->foreignUuid('requester_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('subject', 150);
            $table->text('description');
            $table->string('category', 20);
            $table->string('priority', 10)->default(TreatmentPriority::Medium->value);
            $table->string('status', 15)->default(GenericStatus::Pending->value);
            $table->foreignUuid('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolution', 1000)->nullable();
            $table->index(['status', 'priority']);
        });
        Constraints::enum('support_tickets', 'category', TicketCategory::class);
        Constraints::enum('support_tickets', 'priority', TreatmentPriority::class);
        Constraints::enum('support_tickets', 'status', GenericStatus::class);

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestampsTz();
            $table->index(['ticket_id', 'created_at']);
        });

        $this->grantRights();
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }

    private function grantRights(): void
    {
        // Spatie reloads a role's permissions after each change; fine in a one-off data step.
        Model::preventLazyLoading(false);
        $guard = Config::string('access.guard');
        $names = ['chat.read_own', 'chat.read_all', 'chat.create', 'chat.write', 'chat.delete',
            'tickets.read_own', 'tickets.read_all', 'tickets.create', 'tickets.write', 'tickets.delete', 'tickets.resolve'];
        // A fresh install seeds these from config/access.php after migrating; only existing databases need them here.
        if (! Schema::hasTable('roles') || Role::query()->doesntExist()) {
            return;
        }
        foreach ($names as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
                ['module' => 'identity', 'description' => ucfirst(str_replace('_', ' ', explode('.', $name)[1])).' — '.explode('.', $name)[0]],
            );
        }

        /** @var array<string, array{access: string|array<string, list<string>>, actions: string|list<string>, except_actions?: list<string>}> $defaults */
        $defaults = Config::array('access.roles');
        foreach (Role::query()->where('guard_name', $guard)->whereIn('name', array_keys($defaults))->get() as $role) {
            $default = $defaults[$role->name];
            $grant = $default['access'] === '*'
                ? $names
                : array_merge(
                    array_map(fn (string $flag): string => 'chat.'.$flag, $default['access']['chat'] ?? []),
                    array_map(fn (string $flag): string => 'tickets.'.$flag, $default['access']['tickets'] ?? []),
                );
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        User::query()->pluck('id')->each(fn (string $id) => Cache::forget(CacheKeys::permissions($id)));
    }
};
