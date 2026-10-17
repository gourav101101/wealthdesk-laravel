<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.submit');

Route::middleware(['auth', 'auth.session', 'workspace'])->group(function () {
    Route::get('/', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/positions', [WorkspaceController::class, 'positions'])->name('positions.index');
    Route::get('/transactions', [WorkspaceController::class, 'transactions'])->name('transactions.index');
    Route::get('/withdrawals/request', [WorkspaceController::class, 'withdrawalForm'])->name('withdrawals.create');
    Route::post('/withdrawals', [WorkspaceController::class, 'requestWithdrawal'])->middleware('throttle:20,1')->name('withdrawals.store');
    Route::get('/profile', [WorkspaceController::class, 'profile'])->name('profile');
    Route::post('/profile/password', [WorkspaceController::class, 'changePassword'])->middleware('throttle:5,1')->name('password.change');
    Route::post('/profile/photo', [WorkspaceController::class, 'photo'])->name('profile.photo');
    Route::get('/funds', [WorkspaceController::class, 'funds'])->name('funds');
    Route::get('/documents/{document}', [WorkspaceController::class, 'document'])->name('documents.show');
    Route::get('/activity', [WorkspaceController::class, 'activity'])->name('activity');
    Route::get('/coming-soon/{feature}', [WorkspaceController::class, 'coming'])->name('coming');
    Route::get('/export/{type}', [WorkspaceController::class, 'export'])->name('export');

    Route::middleware('workspace:admin')->prefix('admin')->group(function () {
        Route::get('/clients', [WorkspaceController::class, 'clients'])->name('clients.index');
        Route::get('/clients/create', [WorkspaceController::class, 'clientForm'])->name('clients.create');
        Route::post('/clients', [WorkspaceController::class, 'saveClient'])->name('clients.store');
        Route::get('/clients/{client}', [WorkspaceController::class, 'clientShow'])->name('clients.show');
        Route::get('/clients/{client}/edit', [WorkspaceController::class, 'clientForm'])->name('clients.edit');
        Route::put('/clients/{client}', [WorkspaceController::class, 'saveClient'])->name('clients.update');
        Route::post('/clients/{client}/password/reveal', [WorkspaceController::class, 'revealClientPassword'])->middleware('throttle:5,1')->name('clients.password.reveal');
        Route::post('/clients/{client}/archive', [WorkspaceController::class, 'archiveClient'])->name('clients.archive');
        Route::post('/clients/{client}/preview', [WorkspaceController::class, 'preview'])->name('clients.preview');
        Route::post('/preview/exit', [WorkspaceController::class, 'exitPreview'])->name('preview.exit');
        Route::get('/positions/create', [WorkspaceController::class, 'positionForm'])->name('positions.create');
        Route::post('/positions', [WorkspaceController::class, 'savePosition'])->name('positions.store');
        Route::get('/positions/{position}/edit', [WorkspaceController::class, 'positionForm'])->name('positions.edit');
        Route::put('/positions/{position}', [WorkspaceController::class, 'savePosition'])->name('positions.update');
        Route::post('/positions/{position}/action', [WorkspaceController::class, 'positionAction'])->name('positions.action');
        Route::get('/deposits/create', [WorkspaceController::class, 'depositForm'])->name('deposits.create');
        Route::post('/deposits', [WorkspaceController::class, 'saveDeposit'])->name('deposits.store');
        Route::get('/deposits/{deposit}/edit', [WorkspaceController::class, 'depositForm'])->name('deposits.edit');
        Route::put('/deposits/{deposit}', [WorkspaceController::class, 'saveDeposit'])->name('deposits.update');
        Route::delete('/deposits/{deposit}', [WorkspaceController::class, 'removeDeposit'])->name('deposits.destroy');
        Route::post('/withdrawals/{withdrawal}/decision', [WorkspaceController::class, 'decideWithdrawal'])->name('withdrawals.decide');
        Route::get('/settings', [WorkspaceController::class, 'settings'])->name('settings');
        Route::post('/settings', [WorkspaceController::class, 'saveSettings'])->name('settings.save');
    });
});
