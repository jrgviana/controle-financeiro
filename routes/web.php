<?php

use App\Http\Controllers\Auth\PinLoginController;
use App\Livewire\DailyExpenses;
use App\Livewire\FinancialAccounts;
use App\Livewire\ReceivedIncomes;
use App\Livewire\AccountTransfers;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [PinLoginController::class, 'create'])->name('login');
    Route::post('/login', [PinLoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::livewire('/contas', FinancialAccounts::class)->name('contas.index');
    Route::livewire('/gastos', DailyExpenses::class)->name('gastos.index');
    Route::livewire('/recebimentos', ReceivedIncomes::class)->name('recebimentos.index');
    Route::livewire('/transferencias', AccountTransfers::class)->name('transferencias.index');
    Route::post('/sair', [PinLoginController::class, 'destroy'])->name('logout');
});
