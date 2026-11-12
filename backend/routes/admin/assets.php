<?php

use App\Http\Controllers\Admin\Assets;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /admin group of routes/web.php (names are prefixed "admin.").
| Phase 8a — assets. Authorised by AssetPolicy (register, data scope = asset branch),
| AssetAssignmentPolicy (custody, scoped through the employee) and AssetCategoryPolicy (assets.manage).
*/

Route::resource('asset-categories', Assets\AssetCategoryController::class)->except('show');

Route::get('assets/export', [Assets\AssetController::class, 'export'])->name('assets.export');
Route::resource('assets', Assets\AssetController::class)->except('destroy');
Route::post('assets/{asset}/status/{action}', [Assets\AssetController::class, 'status'])
    ->whereIn('action', ['maintenance', 'maintenance_done', 'damaged', 'lost', 'retire'])->name('assets.status');

Route::prefix('asset-assignments')->name('asset-assignments.')->controller(Assets\AssetAssignmentController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('export', 'export')->name('export');
    Route::post('{assignment}/return', 'recordReturn')->whereNumber('assignment')->name('return');
    Route::post('{assignment}/acknowledge', 'acknowledge')->whereNumber('assignment')->name('acknowledge');
    Route::post('{assignment}/lost', 'lost')->whereNumber('assignment')->name('lost');
});
