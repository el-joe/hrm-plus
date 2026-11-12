<?php

namespace App\Services\Employees\Contracts;

use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Adds module events to the employee history timeline (profile "History" tab).
 * Registered in App\Services\Employees\EmployeeProfile::TIMELINE_SOURCES. Each source
 * decides itself what the admin may see (permission, data scope) and logs restricted access.
 *
 * Entry shape (kind "event"):
 *   ['date' => Carbon, 'kind' => 'event', 'id' => int, 'module' => string (performance|training|assets|disciplinary),
 *    'title' => string, 'description' => ?string, 'url' => ?string, 'tone' => ?string (x-badge tone), 'badge' => ?string]
 */
interface TimelineSource
{
    /**
     * @return Collection<int, array{date: CarbonInterface, kind: string, id: int, module: string, title: string, description?: ?string, url?: ?string, tone?: ?string, badge?: ?string}>
     */
    public function entries(User $user, Employee $employee): Collection;
}
