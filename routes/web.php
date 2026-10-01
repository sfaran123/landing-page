<?php

use AuraTech\SmartDashboard\Http\Controllers\AssetController;
use AuraTech\SmartDashboard\Http\Controllers\DashboardController;
use AuraTech\SmartDashboard\Http\Controllers\EditorController;
use Illuminate\Support\Facades\Route;

$prefix = trim(config('smart-dashboard.route_prefix', 'smart-dashboard'), '/');

Route::get("$prefix/assets/{file}", AssetController::class)->where('file', '[A-Za-z0-9._-]+')->name('smart-dashboard.asset');

Route::middleware(config('smart-dashboard.middleware', ['web', 'auth']))->group(function () use ($prefix) {
    Route::get($prefix, [DashboardController::class, 'index'])->name('smart-dashboard');
    Route::get("$prefix/editor", [EditorController::class, 'index'])->name('smart-dashboard.editor');

    Route::prefix("$prefix/api")->name('smart-dashboard.api.')->group(function () {
        Route::post('data', [DashboardController::class, 'data'])->name('data');
        Route::get('layout', [EditorController::class, 'show'])->name('layout');
        Route::post('layout', [EditorController::class, 'save'])->name('layout.save');
        Route::post('layout/reset', [EditorController::class, 'reset'])->name('layout.reset');
        Route::post('layout/reset-personal', [EditorController::class, 'resetPersonal'])->name('layout.reset-personal');
        Route::post('build', [EditorController::class, 'build'])->name('build');
        Route::get('detect', [EditorController::class, 'detect'])->name('detect');
        Route::post('settings', [EditorController::class, 'settings'])->name('settings');
    });

    if (config('smart-dashboard.replace_default_dashboard')) {
        Route::get('dashboard', fn () => redirect()->route('smart-dashboard'));
    }
});
