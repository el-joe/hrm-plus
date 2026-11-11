<?php

namespace App\Http\Controllers\Admin\Structure;

use App\Http\Controllers\Controller;
use App\Services\Structure\OrgChartBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organisational chart: the department tree, the position reporting
 * hierarchy (needs positions.view) or the manager tree of the employees
 * (needs employees.view). Route requires departments.view.
 */
class OrgChartController extends Controller
{
    public function __invoke(Request $request, OrgChartBuilder $builder): View
    {
        $user = $request->user();
        $canViewPositions = $user->can('positions.view');
        $canViewPeople = $user->can('employees.view');
        $view = match (true) {
            $request->query('view') === 'positions' && $canViewPositions => 'positions',
            $request->query('view') === 'people' && $canViewPeople => 'people',
            default => 'departments',
        };

        $nodes = match ($view) {
            'positions' => $builder->positions($user),
            'people' => $builder->managers($user),
            default => $builder->departments($user),
        };

        return view('admin.structure.org-chart.index', [
            'view' => $view,
            'nodes' => $nodes,
            'summary' => $builder->summary($nodes),
            'canViewPositions' => $canViewPositions,
            'canViewPeople' => $canViewPeople,
        ]);
    }
}
