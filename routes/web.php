<?php

use App\Http\Controllers\AgentController;
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
    Route::post('/setup/api', [ConfigController::class, 'saveApiConfig'])->name('tenants.setup.api');
    Route::post('/setup/api-test', [ConfigController::class, 'testApiConnection'])->name('tenants.setup.api-test');

    Route::get('/runs', [ImportController::class, 'index'])->name('tenants.runs.index');
    Route::post('/runs', [ImportController::class, 'store'])->name('tenants.runs.store');
    Route::post('/runs/pull', [ImportController::class, 'pull'])->name('tenants.runs.pull');
    Route::get('/runs/{run}', [ImportController::class, 'show'])->name('tenants.runs.show');
    Route::get('/runs/{run}/export', [ImportController::class, 'export'])->name('tenants.runs.export');

    Route::get('/agents', [AgentController::class, 'index'])->name('tenants.agents.index');
    Route::post('/agents/sync', [AgentController::class, 'sync'])->name('tenants.agents.sync');
});
