<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationSoundController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/booking-searches', [DashboardController::class, 'store'])->name('booking-searches.store');
    Route::patch('/booking-searches/{search}/stop', [DashboardController::class, 'stop'])->name('booking-searches.stop');
    Route::delete('/booking-searches/{search}', [DashboardController::class, 'destroy'])->name('booking-searches.destroy');
    Route::get('/booking-searches/{search}/availability/{item}', [DashboardController::class, 'openAvailability'])->name('booking-searches.availability');
    Route::get('/sounds', [NotificationSoundController::class, 'index'])->name('sounds.index');
    Route::post('/sounds', [NotificationSoundController::class, 'store'])->name('sounds.store');
    Route::post('/sounds/default', [NotificationSoundController::class, 'useDefault'])->name('sounds.default');
    Route::post('/sounds/test', [NotificationSoundController::class, 'test'])->name('sounds.test');
    Route::get('/sounds/{sound}/file', [NotificationSoundController::class, 'file'])->name('sounds.file');
    Route::patch('/sounds/{sound}/activate', [NotificationSoundController::class, 'activate'])->name('sounds.activate');
    Route::delete('/sounds/{sound}', [NotificationSoundController::class, 'destroy'])->name('sounds.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
