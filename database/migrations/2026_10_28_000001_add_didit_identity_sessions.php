<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_people', function (Blueprint $table) {
            $table->string('identity_mode', 20)->default('manual');
        });
        Schema::create('identity_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('order_person_id')->unique();
            $table->foreign(['order_id', 'order_person_id'])->references(['order_id', 'id'])->on('order_people')->restrictOnDelete();
            $table->uuid('application_id');
            $table->uuid('workflow_id');
            $table->string('environment', 10);
            $table->uuid('provider_session_id')->nullable()->unique();
            $table->string('state', 30)->default('pending')->index();
            $table->text('verification_url')->nullable();
            $table->text('provider_status')->nullable();
            $table->longText('decision')->nullable();
            $table->boolean('sync_requested')->default(false);
            $table->timestamp('consented_at');
            $table->string('notice_version', 50);
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('identity_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('identity_session_id')->constrained()->restrictOnDelete();
            $table->char('fingerprint', 64);
            $table->timestamp('created_at');
        });
        DB::table('settings')->insertOrIgnore(['key' => 'identity_verification_mode', 'label' => 'Verificación de identidad', 'value' => 'manual', 'group' => 'Integraciones', 'type' => 'identity_mode', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('identity_sessions')->exists() || DB::table('order_people')->where('identity_mode', '!=', 'manual')->exists()) {
            throw new RuntimeException('Hay verificaciones automatizadas. Preserve sus registros; no se permite un rollback destructivo.');
        }
        Schema::dropIfExists('identity_webhook_receipts');
        Schema::dropIfExists('identity_sessions');
        Schema::table('order_people', fn (Blueprint $table) => $table->dropColumn('identity_mode'));
        DB::table('settings')->where('key', 'identity_verification_mode')->delete();
    }
};
