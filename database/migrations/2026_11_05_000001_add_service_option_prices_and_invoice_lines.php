<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_option_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('option_code', 60);
            $table->unsignedBigInteger('unit_price');
            $table->unique(['service_id', 'option_code']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('line_items')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('service_option_prices')->exists() || DB::table('invoices')->whereNotNull('line_items')->exists()) {
            throw new RuntimeException('Hay tarifas o facturas por modalidad; se requiere una migración revisada para conservarlas.');
        }
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('line_items'));
        Schema::drop('service_option_prices');
    }
};
