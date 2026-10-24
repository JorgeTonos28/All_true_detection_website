<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Web\Auth\GoogleAuthController;
use App\Http\Controllers\Web\ServiceEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/solicitar', ServiceEntryController::class)->name('services.request');
Route::get('/desarrolladores/api', [ApiDocumentationController::class, 'index'])->name('api.docs');
Route::get('/desarrolladores/openapi.json', [ApiDocumentationController::class, 'contract'])->name('api.contract');
Route::view('/aviso-de-privacidad', 'legal.privacy')->name('privacy');
Route::view('/terminos-y-condiciones', 'legal.terms')->name('terms');
Route::redirect('/admin/login', '/login')->name('admin.login');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware(['guest', 'throttle:10,1'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:10,1')->name('google.callback');
Route::get('/auth/google/link', [GoogleAuthController::class, 'redirect'])->middleware(['auth', 'active', 'verified', 'password.confirm', 'throttle:5,1'])->name('google.link');
Route::get('/auth/google/reauthenticate', [GoogleAuthController::class, 'reauthenticate'])->middleware(['auth', 'active', 'verified', 'throttle:5,1'])->name('google.reauthenticate');
require __DIR__.'/portal.php';
