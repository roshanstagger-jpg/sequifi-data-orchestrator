<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('tenants.index'));

Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');

Route::prefix('/tenants/{tenant}')->group(function () {
    Route::get('/setup', [ConfigController::class, 'show'])->name('tenants.setup');
    Route::post('/setup/sample', [ConfigController::class, 'uploadSample'])->name('tenants.setup.sample');
    Route::post('/setup/fields', [ConfigController::class, 'saveFields'])->name('tenants.setup.fields');
    Route::post('/setup/template', [ConfigController::class, 'saveTemplate'])->name('tenants.setup.template');

    Route::get('/runs', [ImportController::class, 'index'])->name('tenants.runs.index');
    Route::post('/runs', [ImportController::class, 'store'])->name('tenants.runs.store');
    Route::get('/runs/{run}', [ImportController::class, 'show'])->name('tenants.runs.show');
    Route::get('/runs/{run}/export', [ImportController::class, 'export'])->name('tenants.runs.export');
});
