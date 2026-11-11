<?php

namespace App\Console\Commands;

use App\Services\Authorization\PermissionSynchroniser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Create missing catalogue permissions and grant new default permissions to the default roles (keeps custom changes)')]
class SyncPermissions extends Command
{
    public function handle(PermissionSynchroniser $synchroniser): int
    {
        $report = $synchroniser->sync();

        $this->components->info(sprintf('Permissions created: %d, removed: %d.', count($report['created']), count($report['removed'])));

        foreach ($report['roles_created'] as $role) {
            $this->components->twoColumnDetail($role, 'role created');
        }

        foreach ($report['granted'] as $role => $permissions) {
            $this->components->twoColumnDetail($role, implode(', ', $permissions));
        }

        if ($report['granted'] === [] && $report['roles_created'] === []) {
            $this->components->info('Default roles already up to date.');
        }

        return self::SUCCESS;
    }
}
