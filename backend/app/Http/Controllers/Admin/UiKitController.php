<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Living reference of the Blade components and JS modules (Super Admin only).
 */
class UiKitController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.ui-kit');
    }
}
