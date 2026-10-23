<?php
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ResultController;
use App\Http\Controllers\Api\V1\ServiceController;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->middleware(['auth:sanctum','active','verified','integration','throttle:api'])->group(function () {
    Route::get('/services',[ServiceController::class,'index'])->middleware('integration:orders:read');
    Route::get('/orders',[OrderController::class,'index'])->middleware('integration:orders:read');
    Route::post('/orders',[OrderController::class,'store'])->middleware('integration:orders:write');
    Route::get('/orders/{order}',[OrderController::class,'show'])->middleware('integration:orders:read');
    Route::get('/orders/{order}/results',[ResultController::class,'index'])->middleware('integration:results:read');
    Route::get('/orders/{order}/results/{result}/download',[ResultController::class,'download'])->middleware('integration:results:read');
    Route::get('/orders/{order}/messages',[MessageController::class,'index'])->middleware('integration:messages:read');
    Route::post('/orders/{order}/messages',[MessageController::class,'store'])->middleware('integration:messages:write');
});
