<?php

use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\OperationsController;
use App\Http\Controllers\Web\OrderController;
use App\Http\Controllers\Web\SecurityController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/contact',[MessageController::class,'contact'])->middleware('throttle:contact')->name('contact.store');
Route::middleware(['auth','active','verified'])->prefix('portal')->name('portal.')->group(function () {
    Route::view('/api-guide','portal.api-guide')->name('api-guide');
    Route::get('/api-contract',fn () => response()->download(base_path('packages/contracts/openapi.json'),'atd-api-v1.json',['Content-Type'=>'application/json']))->name('api-contract');
    Route::get('/security',[SecurityController::class,'index'])->middleware('password.confirm')->name('security');
    Route::middleware(['password.confirm','throttle:10,1'])->group(function () {
        Route::post('/tokens',[SecurityController::class,'token'])->name('tokens.store');
        Route::delete('/tokens/{token}',[SecurityController::class,'revoke'])->name('tokens.revoke');
        Route::post('/webhooks',[SecurityController::class,'webhook'])->name('webhooks.store');
        Route::delete('/webhooks/{endpoint}',[SecurityController::class,'disableWebhook'])->name('webhooks.disable');
    });
    Route::middleware('staff-2fa')->group(function () {
        Route::get('/',DashboardController::class)->name('dashboard');
        Route::get('/orders',[OrderController::class,'index'])->name('orders.index');
        Route::get('/orders/new',[OrderController::class,'create'])->name('orders.create');
        Route::post('/orders',[OrderController::class,'store'])->middleware('throttle:20,1')->name('orders.store');
        Route::get('/orders/{order}',[OrderController::class,'show'])->name('orders.show');
        Route::patch('/orders/{order}',[OrderController::class,'update'])->name('orders.update');
        Route::post('/orders/{order}/attachments',[DocumentController::class,'attach'])->middleware('throttle:10,1')->name('attachments.store');
        Route::get('/attachments/{attachment}',[DocumentController::class,'downloadAttachment'])->name('attachments.download');
        Route::post('/orders/{order}/results',[DocumentController::class,'result'])->middleware('throttle:10,1')->name('results.store');
        Route::get('/results/{result}',[DocumentController::class,'downloadResult'])->name('results.download');
        Route::post('/results/{result}/publish',[DocumentController::class,'publish'])->middleware('password.confirm')->name('results.publish');
        Route::get('/invoices/{invoice}',[BillingController::class,'show'])->name('invoices.show');
        Route::get('/messages',[MessageController::class,'index'])->name('messages.index');
        Route::post('/messages',[MessageController::class,'create'])->middleware('throttle:10,1')->name('messages.store');
        Route::get('/messages/{conversation}',[MessageController::class,'show'])->name('messages.show');
        Route::post('/messages/{conversation}',[MessageController::class,'reply'])->middleware('throttle:20,1')->name('messages.reply');
        Route::middleware(['can:manage-business','password.confirm'])->group(function () {
            Route::post('/orders/{order}/invoice',[BillingController::class,'issue'])->name('invoices.issue');
            Route::post('/invoices/{invoice}/payments',[BillingController::class,'pay'])->name('payments.store');
            Route::patch('/messages/{conversation}',[MessageController::class,'close'])->name('messages.status');
            Route::get('/users',[UserController::class,'index'])->name('users.index');
            Route::post('/users',[UserController::class,'store'])->middleware('throttle:10,1')->name('users.store');
            Route::patch('/users/{user}',[UserController::class,'update'])->name('users.update');
            Route::post('/users/{user}/resend',[UserController::class,'resend'])->middleware('throttle:5,1')->name('users.resend');
            Route::get('/settings',[SettingsController::class,'index'])->name('settings');
            Route::put('/settings',[SettingsController::class,'update'])->name('settings.update');
            Route::get('/operations',OperationsController::class)->name('operations');
        });
    });
});
Route::middleware(['auth','active','verified','staff-2fa','can:manage-business'])->get('/admin',fn () => redirect()->route('portal.settings'))->name('admin.dashboard');
