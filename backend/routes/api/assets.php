<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| Included inside the protected /api/v1/me group of routes/api.php (names are prefixed "api.v1.me.").
| Phase 8a — assets: always the token owner's own employee record.
*/

Route::get('assets', [V1\AssetController::class, 'index'])->name('assets.index');
Route::get('assets/{assignment}', [V1\AssetController::class, 'show'])->whereNumber('assignment')->name('assets.show');
Route::post('assets/{assignment}/acknowledge', [V1\AssetController::class, 'acknowledge'])->whereNumber('assignment')->name('assets.acknowledge');
