<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
        });
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('method', 10);
            $table->string('route', 160);
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['customer_account_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        Schema::table('webhook_endpoints', function (Blueprint $table) {
            $table->json('events')->nullable();
            $table->text('previous_secret')->nullable();
            $table->timestamp('previous_secret_expires_at')->nullable();
            $table->timestamp('secret_rotated_at')->nullable();
        });
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->uuid('processing_token')->nullable();
            $table->timestamp('processing_until')->nullable();
            $table->unsignedInteger('replay_count')->default(0);
        });
        Schema::create('webhook_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('error_code', 32)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['webhook_delivery_id', 'created_at']);
        });
        foreach (['order_attachments', 'order_results'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('file_size')->nullable();
                $table->char('sha256', 64)->nullable();
            });
        }
        Schema::table('order_attachments', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable();
            $table->unique(['order_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('order_attachments', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
        foreach (['order_attachments', 'order_results'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['file_size', 'sha256']));
        }
        Schema::dropIfExists('webhook_attempts');
        Schema::table('webhook_deliveries', fn (Blueprint $table) => $table->dropColumn(['processing_token', 'processing_until', 'replay_count']));
        Schema::table('webhook_endpoints', fn (Blueprint $table) => $table->dropColumn(['events', 'previous_secret', 'previous_secret_expires_at', 'secret_rotated_at']));
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('notifications');
    }
};
