<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_subjects', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_identity_session_id')->unique()->constrained('identity_sessions')->restrictOnDelete();
            $table->uuid('application_id');
            $table->string('environment', 10);
            $table->string('provider_reference', 100);
            $table->text('profile');
            $table->foreignId('enrolled_by')->constrained('users')->restrictOnDelete();
            $table->string('notice_version', 50);
            $table->timestamp('consented_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['application_id', 'environment', 'provider_reference'], 'identity_subject_provider_unique');
            $table->index(['customer_account_id', 'id']);
        });
        Schema::table('order_people', function (Blueprint $table) {
            $table->foreignId('identity_subject_id')->nullable()->constrained('identity_subjects')->restrictOnDelete();
        });
        Schema::table('identity_sessions', function (Blueprint $table) {
            $table->foreignId('identity_subject_id')->nullable()->constrained('identity_subjects')->restrictOnDelete();
            $table->string('verification_kind', 20)->default('initial');
            $table->string('provider_reference', 100)->nullable()->index();
            $table->timestamp('provider_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('identity_subjects')->exists()) {
            throw new RuntimeException('Preserve los consentimientos y vínculos de identidad antes de revertir.');
        }
        Schema::table('identity_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('identity_subject_id');
            $table->dropIndex(['provider_reference']);
            $table->dropColumn(['verification_kind', 'provider_reference', 'provider_completed_at']);
        });
        Schema::table('order_people', fn (Blueprint $table) => $table->dropConstrainedForeignId('identity_subject_id'));
        Schema::dropIfExists('identity_subjects');
    }
};
