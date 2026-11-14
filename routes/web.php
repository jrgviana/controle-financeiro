<?php

use App\Http\Controllers\Auth\PinLoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [PinLoginController::class, 'create'])->name('login');
    Route::post('/login', [PinLoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/sair', [PinLoginController::class, 'destroy'])->name('logout');
});
