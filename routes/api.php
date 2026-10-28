<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ResultController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\DiditWebhookController;
use App\Http\Controllers\IdentitySessionController;
use Illuminate\Support\Facades\Route;

Route::post('/providers/didit/webhook', DiditWebhookController::class)->middleware('throttle:300,1')->name('didit.webhook');

Route::prefix('v1')->middleware(['throttle:api-edge', 'auth:sanctum', 'active', 'verified', 'integration', 'throttle:api', 'api-json'])->group(function () {
    Route::post('/orders/{order}/people/{person}/identity-session', [IdentitySessionController::class, 'store'])->middleware(['integration:identity:verify', 'throttle:10,1']);
    Route::get('/orders/{order}/people/{person}/identity-session', [IdentitySessionController::class, 'show'])->middleware('integration:identity:read');
    Route::get('/orders/{order}/people/{person}/identity-session/result', [IdentitySessionController::class, 'result'])->middleware('integration:identity:read');
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('integration:invoices:read');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('integration:invoices:read');
    Route::get('/invoices/{invoice}/document', [InvoiceController::class, 'document'])->middleware('integration:invoices:read');
    Route::get('/orders/{order}/attachments', [AttachmentController::class, 'index'])->middleware('integration:orders:read');
    Route::post('/orders/{order}/attachments', [AttachmentController::class, 'store'])->middleware(['integration:orders:write', 'throttle:10,1']);
    Route::get('/orders/{order}/attachments/{attachment}/download', [AttachmentController::class, 'download'])->middleware('integration:orders:read');
    Route::get('/orders/{order}/attachments/{attachment}/preview', [AttachmentController::class, 'preview'])->middleware('integration:orders:read');
    Route::get('/services', [ServiceController::class, 'index'])->middleware('integration:orders:read');
    Route::get('/orders', [OrderController::class, 'index'])->middleware('integration:orders:read');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('integration:orders:write');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('integration:orders:read');
    Route::get('/orders/{order}/results', [ResultController::class, 'index'])->middleware('integration:results:read');
    Route::get('/orders/{order}/deliveries', [ResultController::class, 'index'])->middleware('integration:results:read');
    Route::get('/orders/{order}/results/{result}', [ResultController::class, 'show'])->middleware('integration:results:read');
    Route::get('/orders/{order}/results/{result}/download', [ResultController::class, 'download'])->middleware('integration:results:read');
    Route::get('/orders/{order}/results/{result}/preview', [ResultController::class, 'preview'])->middleware('integration:results:read');
    Route::get('/orders/{order}/messages', [MessageController::class, 'index'])->middleware('integration:messages:read');
    Route::post('/orders/{order}/messages', [MessageController::class, 'store'])->middleware('integration:messages:write');
});
