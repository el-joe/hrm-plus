<?php

use App\Http\Controllers\Employee;
use Illuminate\Support\Facades\Route;

/*
| Included inside the authenticated /portal group of routes/web.php (names are prefixed "portal.").
| Phase 8a — disciplinary: always the signed-in user's own employee record, issued actions only.
*/

Route::get('disciplinary', [Employee\DisciplinaryController::class, 'index'])->name('disciplinary');
Route::get('disciplinary/{action}', [Employee\DisciplinaryController::class, 'show'])->whereNumber('action')->name('disciplinary.show');
Route::post('disciplinary/{action}/acknowledge', [Employee\DisciplinaryController::class, 'acknowledge'])->whereNumber('action')->name('disciplinary.acknowledge');
Route::post('disciplinary/{action}/respond', [Employee\DisciplinaryController::class, 'respond'])->whereNumber('action')->name('disciplinary.respond');
