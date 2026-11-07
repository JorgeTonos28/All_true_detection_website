<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_results', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
            $table->string('automation_key', 128)->nullable()->unique();
        });
    }

    public function down(): void
    {
        if (DB::table('order_results')->whereNull('created_by')->orWhereNotNull('automation_key')->exists()) {
            throw new RuntimeException('Preserve la autoría de las entregas automáticas antes de revertir.');
        }
        Schema::table('order_results', function (Blueprint $table) {
            $table->dropUnique(['automation_key']);
            $table->dropColumn('automation_key');
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};
