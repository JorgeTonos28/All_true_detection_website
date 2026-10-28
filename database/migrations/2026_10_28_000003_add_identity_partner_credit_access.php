<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->boolean('identity_api_enabled')->default(false);
            $table->unsignedInteger('identity_api_daily_limit')->default(20);
            $table->foreignId('identity_api_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('identity_api_approved_at')->nullable();
        });
        Schema::table('identity_sessions', function (Blueprint $table) {
            // Historical sessions retain the prepaid web rules. No retroactive credit grants.
            $table->string('access_channel', 20)->default('web')->index();
            $table->foreignId('credit_authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('credit_authorized_at')->nullable();
            $table->timestamp('credit_accepted_at')->nullable()->index();
            $table->unsignedInteger('decision_revision')->default(0);
        });
    }

    public function down(): void
    {
        if (DB::table('identity_sessions')->where('access_channel', 'api_credit')->exists() || DB::table('customer_accounts')->whereNotNull('identity_api_approved_at')->exists()) {
            throw new RuntimeException('Preserve las autorizaciones y consumos de partners antes de revertir.');
        }
        Schema::table('identity_sessions', function (Blueprint $table) {
            $table->dropForeign(['credit_authorized_by']);
            $table->dropIndex(['access_channel']);
            $table->dropIndex(['credit_accepted_at']);
            $table->dropColumn(['access_channel', 'credit_authorized_by', 'credit_authorized_at', 'credit_accepted_at', 'decision_revision']);
        });
        Schema::table('customer_accounts', function (Blueprint $table) {
            $table->dropForeign(['identity_api_approved_by']);
            $table->dropColumn(['identity_api_enabled', 'identity_api_daily_limit', 'identity_api_approved_by', 'identity_api_approved_at']);
        });
    }
};
