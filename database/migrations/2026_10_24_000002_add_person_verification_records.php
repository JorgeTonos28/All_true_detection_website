<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A case is a snapshot for ONE person, not a cross-customer identity registry.
        Schema::create('order_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->text('profile'); // encrypted JSON, never searchable plaintext
            $table->timestamps();
        });
        Schema::table('order_attachments', function (Blueprint $table) {
            $table->string('purpose', 32)->default('supporting');
            $table->index(['order_id', 'purpose']);
        });
        Schema::table('order_results', function (Blueprint $table) {
            $table->text('verification_findings')->nullable(); // encrypted JSON
        });
    }

    public function down(): void
    {
        Schema::table('order_results', fn (Blueprint $table) => $table->dropColumn('verification_findings'));
        Schema::table('order_attachments', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'purpose']);
            $table->dropColumn('purpose');
        });
        Schema::dropIfExists('order_people');
    }
};
