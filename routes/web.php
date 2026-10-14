<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Demo page for previewing the public layout; only registered locally so it is never public or indexed.
if (app()->environment('local')) {
    Route::view('/demo/layout', 'demo.layout')->name('demo.layout');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::prefix('admin')->middleware('can:access-admin')->group(function () {
        Route::get('/', DashboardController::class)->name('admin.dashboard');
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
