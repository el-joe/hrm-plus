<?php

namespace Database\Seeders;

use App\Services\Authorization\PermissionSynchroniser;
use Illuminate\Database\Seeder;

/**
 * Brings the database in line with config/permissions.php. Safe to re-run in
 * production; same as `php artisan permissions:sync` (see
 * App\Services\Authorization\PermissionSynchroniser): missing permissions and
 * default roles are created, default roles receive newly catalogued default
 * permissions once, and permissions an administrator removed or added stay
 * as they are.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(PermissionSynchroniser $synchroniser): void
    {
        $synchroniser->sync();
    }
}
