<?php

use App\Http\Controllers\Employee;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /portal group of routes/web.php (names are prefixed "portal.").
| Phase 8a — assets: always the signed-in user's own employee record.
*/

Route::get('assets', [Employee\AssetController::class, 'index'])->name('assets');
Route::post('assets/{assignment}/acknowledge', [Employee\AssetController::class, 'acknowledge'])->whereNumber('assignment')->name('assets.acknowledge');
