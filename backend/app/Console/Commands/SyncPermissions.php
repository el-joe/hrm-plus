<?php

namespace App\Console\Commands;

use App\Services\Authorization\PermissionSynchroniser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync {--dry-run : Show what would change without saving anything}')]
#[Description('Create missing catalogue permissions, grant new default permissions to the default roles and revoke sync-granted permissions dropped from a default set (keeps custom changes)')]
class SyncPermissions extends Command
{
    public function handle(PermissionSynchroniser $synchroniser): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $report = $synchroniser->sync($dryRun);

        if ($dryRun) {
            $this->components->warn('Dry run: nothing was saved.');
        }

        $this->components->info(sprintf('Permissions created: %d, removed: %d.', count($report['created']), count($report['removed'])));

        foreach ($report['roles_created'] as $role) {
            $this->components->twoColumnDetail($role, 'role created');
        }

        foreach ($report['granted'] as $role => $permissions) {
            $this->components->twoColumnDetail($role, implode(', ', $permissions));
        }

        foreach ($report['revoked'] as $role => $permissions) {
            $this->components->twoColumnDetail($role.' <fg=red>revoked</>', implode(', ', $permissions));
        }

        foreach ($report['kept'] as $role => $permissions) {
            $this->components->twoColumnDetail($role.' <fg=yellow>kept (granted by an administrator)</>', implode(', ', $permissions));
        }

        if ($report['granted'] === [] && $report['roles_created'] === [] && $report['revoked'] === []) {
            $this->components->info('Default roles already up to date.');
        }

        return self::SUCCESS;
    }
}
