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
            // Existing accounts remain unclassified until their owner confirms the type.
            $table->enum('account_type', ['individual', 'business'])->nullable()->index();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->enum('category', ['assessments', 'verifications', 'additional'])->nullable()->index();
            $table->json('options')->nullable();
            $table->unsignedBigInteger('default_price')->nullable();
        });
        Schema::create('business_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 120);
            $table->enum('account_type', ['checking', 'savings']);
            $table->string('account_holder', 200);
            $table->text('account_number');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('business_bank_accounts')->exists() || DB::table('customer_accounts')->whereNotNull('account_type')->exists()
            || DB::table('services')->whereNotNull('default_price')->exists()) {
            throw new RuntimeException('Respalde y revise los datos comerciales antes de revertir esta migración.');
        }
        Schema::dropIfExists('business_bank_accounts');
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['category', 'options', 'default_price']));
        Schema::table('customer_accounts', fn (Blueprint $table) => $table->dropColumn('account_type'));
    }
};
