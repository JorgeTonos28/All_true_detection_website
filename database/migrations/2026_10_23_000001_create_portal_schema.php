<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('billing_email');
            $table->string('tax_id', 50)->nullable();
            $table->text('billing_address')->nullable();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('customer_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role', 24)->default('client')->index();
            $table->boolean('is_active')->default(true);
            $table->string('google_id')->nullable()->unique();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('subject', 200);
            $table->text('details');
            $table->string('external_reference', 100)->nullable();
            $table->string('status', 24)->default('submitted');
            $table->timestamp('consented_at');
            $table->timestamps();
            $table->index(['customer_account_id', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'created_at']);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('service_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->unique(['order_id', 'service_id']);
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('number', 40)->unique();
            $table->char('currency', 3);
            $table->json('issuer');
            $table->json('recipient');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedInteger('tax_basis_points')->default(0);
            $table->unsignedBigInteger('tax');
            $table->unsignedBigInteger('total');
            $table->string('status', 24)->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 32);
            $table->string('reference', 100);
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['invoice_id', 'reference']);
        });
        Schema::create('order_results', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('summary');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_attachments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('subject', 200);
            $table->string('contact_name');
            $table->string('contact_email')->index();
            $table->string('status', 24)->default('open');
            $table->timestamps();
            $table->index(['customer_account_id', 'updated_at']);
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type', 100);
            $table->string('subject_id', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('request_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_account_id')->constrained()->restrictOnDelete();
            $table->string('url', 2048);
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('webhook_endpoint_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 80);
            $table->json('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['webhook_deliveries', 'webhook_endpoints', 'idempotency_keys', 'audit_events', 'messages', 'conversations', 'order_attachments', 'order_results', 'payments', 'invoices', 'order_items', 'orders', 'services', 'personal_access_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_account_id');
            $table->dropColumn(['role', 'is_active', 'google_id', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
        Schema::dropIfExists('customer_accounts');
    }
};
