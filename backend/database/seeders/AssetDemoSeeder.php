<?php

namespace Database\Seeders;

use App\Actions\Assets\ChangeAssetStatus;
use App\Actions\Assets\ManageAssetCustody;
use App\Actions\Assets\SaveAsset;
use App\Actions\Offboarding\OpenOffboardingCase;
use App\Enums\EmployeeStatus;
use App\Enums\OffboardingStatus;
use App\Enums\TerminationType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Development demo data of the asset register, created through the real
 * actions (events, audit, offboarding clearance) by hr.manager@:
 *
 * - 80 assets across Cairo, Giza and Alexandria (laptops, desktops, phones, SIM cards,
 *   office equipment, uniforms, vehicles, access cards) with values in EGP;
 * - ~45 active handovers, most acknowledged; returned history (one damaged return,
 *   repaired), two assets in maintenance, one lost while assigned, one retired;
 * - employee1@ (EMP-00024): an unacknowledged laptop and an acknowledged SIM card;
 *   employee2@ (EMP-00015): an acknowledged laptop; employee3@ (EMP-00036): an
 *   unacknowledged uniform and an acknowledged phone;
 * - offboarding clearance: a resignation case opened for an employee holding a laptop
 *   and a phone — two asset items; the phone is returned (item done), the laptop pending.
 *   Assets handed to employees whose "contract end" case is already open are added to
 *   that case as well.
 */
class AssetDemoSeeder extends Seeder
{
    private User $hr;

    private SaveAsset $save;

    private ManageAssetCustody $custody;

    /** @var array<string, AssetCategory> */
    private array $categories;

    /** @var array<string, int> */
    private array $branches;

    private int $sequence = 0;

    public function run(): void
    {
        if (Asset::query()->withoutGlobalScopes()->exists() || ! Employee::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        Notification::fake();
        $this->hr = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        auth('admin')->setUser($this->hr);
        $this->save = app(SaveAsset::class);
        $this->custody = app(ManageAssetCustody::class);
        $this->categories = AssetCategory::query()->get()->keyBy('code')->all();
        $this->branches = Branch::query()->pluck('id', 'code')->all();
        mt_srand(20261119);

        $assets = $this->register();
        $this->handovers($assets);

        Carbon::setTestNow();
        auth('admin')->forgetUser();
        $this->command?->info('Asset demo: '.Asset::query()->count().' assets, '.AssetAssignment::query()->where('status', 'active')->count().' active handovers.');
    }

    /**
     * @return Collection<string, Asset> keyed by tag
     */
    private function register(): Collection
    {
        // [category, tag prefix, count, name, brand, model, cost, branches cycle, years old]
        $plan = [
            ['LAPTOP', 'LT', 24, 'Laptop', 'Dell', 'Latitude 5440', '42500.00', ['CAI', 'CAI', 'GIZ', 'CAI', 'ALX'], 2],
            ['DESKTOP', 'DT', 6, 'Desktop computer', 'HP', 'ProDesk 400 G9', '31000.00', ['CAI', 'ALX', 'GIZ'], 4],
            ['PHONE', 'MB', 14, 'Mobile phone', 'Samsung', 'Galaxy A55', '17999.00', ['CAI', 'ALX', 'GIZ'], 1],
            ['SIM', 'SIM', 14, 'Business SIM card', 'Vodafone', 'Red Business', '150.00', ['CAI', 'ALX', 'GIZ'], 1],
            ['OFFICE', 'OF', 7, 'Monitor 27"', 'LG', '27UP600', '9800.00', ['CAI', 'GIZ'], 2],
            ['UNIFORM', 'UN', 6, 'Warehouse uniform set', 'Local', 'Safety kit', '1250.00', ['ALX'], 0],
            ['VEHICLE', 'VH', 3, 'Delivery van', 'Toyota', 'Hiace 2023', '1450000.00', ['ALX'], 2],
            ['ACCESS', 'AC', 6, 'Access card', 'HID', 'iCLASS SE', '350.00', ['CAI', 'GIZ'], 1],
        ];

        $assets = collect();

        foreach ($plan as [$category, $prefix, $count, $name, $brand, $model, $cost, $branches, $age]) {
            for ($i = 1; $i <= $count; $i++) {
                $tag = $prefix.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
                $purchased = CarbonImmutable::today()->subYears($age)->subDays(mt_rand(10, 300));
                $assets[$tag] = $this->save->handle(null, [
                    'asset_tag' => $tag,
                    'asset_category_id' => $this->categories[$category]->id,
                    'name' => $name,
                    'brand' => $brand,
                    'model' => $model,
                    'serial_number' => $this->categories[$category]->requires_serial ? strtoupper($prefix).'SN'.(100000 + (++$this->sequence) * 37) : null,
                    'purchase_date' => $purchased->toDateString(),
                    'purchase_cost' => $cost,
                    'supplier' => match ($category) {
                        'LAPTOP', 'DESKTOP', 'OFFICE' => 'Raya IT',
                        'PHONE', 'SIM' => 'Vodafone Business',
                        'VEHICLE' => 'Toyota Egypt',
                        default => 'Local supplier',
                    },
                    'warranty_expires_on' => in_array($category, ['LAPTOP', 'DESKTOP', 'PHONE', 'OFFICE', 'VEHICLE'], true)
                        ? $purchased->addYears($category === 'VEHICLE' ? 3 : 2)->toDateString() : null,
                    'branch_id' => $this->branches[$branches[($i - 1) % count($branches)]] ?? null,
                    'condition' => $age === 0 ? 'new' : 'good',
                ], $this->hr);
            }
        }

        return $assets;
    }

    /**
     * @param  Collection<string, Asset>  $assets
     */
    private function handovers(Collection $assets): void
    {
        $byNumber = Employee::query()->withoutGlobalScopes()->where('status', '!=', EmployeeStatus::Terminated)->with('branch')
            ->orderBy('employee_number')->get()->keyBy('employee_number');
        $openCases = OffboardingCase::query()->withoutGlobalScopes()->where('status', OffboardingStatus::Open)->pluck('employee_id')->all();

        // Login accounts of the demo (portal / API flows).
        $this->give($assets['LT-0001'], $byNumber['EMP-00024'] ?? null, 60, acknowledged: false);
        $this->give($assets['SIM-0001'], $byNumber['EMP-00024'] ?? null, 200, acknowledged: true);
        $this->give($assets['LT-0003'], $byNumber['EMP-00015'] ?? null, 300, acknowledged: true);
        $this->give($assets['UN-0001'], $byNumber['EMP-00036'] ?? null, 5, acknowledged: false);
        $this->give($assets['MB-0002'], $byNumber['EMP-00036'] ?? null, 120, acknowledged: true);

        // History: returned assets, one damaged (repaired afterwards), then re-issued.
        $pool = $byNumber->reject(fn (Employee $employee): bool => in_array($employee->employee_number, ['EMP-00002', 'EMP-00024', 'EMP-00015', 'EMP-00036'], true))->values();
        $this->returned($assets['LT-0005'], $pool[0], 500, 200, 'good');
        $this->returned($assets['LT-0006'], $pool[1], 450, 90, 'fair');
        $this->returned($assets['MB-0004'], $pool[2], 400, 150, 'damaged', 'Cracked screen; sent for repair.');
        app(ChangeAssetStatus::class)->handle($assets['MB-0004']->refresh(), 'maintenance', $this->hr, 'Screen replacement at the service centre.');
        $this->returned($assets['DT-0002'], $pool[3], 700, 300, 'poor');
        app(ChangeAssetStatus::class)->handle($assets['DT-0002']->refresh(), 'retire', $this->hr, 'End of life; disposed through the IT recycler.');
        app(ChangeAssetStatus::class)->handle($assets['LT-0024'], 'maintenance', $this->hr, 'Battery replacement under warranty.');

        // Active handovers to the rest of the staff, by branch.
        $skip = ['LT-0001', 'SIM-0001', 'LT-0003', 'UN-0001', 'MB-0002', 'MB-0004', 'DT-0002', 'LT-0024'];
        // Round-robin over the categories (LT-0002, MB-0001, SIM-0002, ... then the next numbers).
        $free = $assets->reject(fn (Asset $asset, string $tag): bool => in_array($tag, $skip, true))
            ->sortBy(fn (Asset $asset, string $tag): int => (int) substr($tag, strrpos($tag, '-') + 1) * 100 + $asset->asset_category_id)->values();
        $given = 5;
        $index = 0;

        foreach ($free as $asset) {
            if ($given >= 46) {
                break;
            }

            $employee = $pool[($index++ * 7) % $pool->count()];

            if ($asset->branch_id !== null && $employee->branch_id !== $asset->branch_id) {
                $employee = $pool->first(fn (Employee $row): bool => $row->branch_id === $asset->branch_id
                    && ! AssetAssignment::query()->where('employee_id', $row->id)->whereHas('asset', fn ($query) => $query->where('asset_category_id', $asset->asset_category_id))->active()->exists()) ?? $employee;
            }

            if (in_array($employee->id, $openCases, true) && $given % 3 !== 0) {
                continue;
            }

            $this->give($asset, $employee, mt_rand(20, 420), acknowledged: mt_rand(1, 10) > 2, expectedReturn: $asset->asset_category_id === $this->categories['VEHICLE']->id ? 30 : null);
            $given++;
        }

        // One asset lost while assigned.
        $lost = AssetAssignment::query()->active()->whereHas('asset', fn ($query) => $query->where('asset_category_id', $this->categories['ACCESS']->id))->first();

        if ($lost !== null) {
            $this->custody->markLost($lost, today()->subDays(4)->toDateString(), 'Employee reported the card lost; a replacement was requested.', $this->hr);
        }

        $this->offboardingClearance($assets, $pool, $openCases);
    }

    /**
     * Resignation case of an employee holding a laptop and a phone: the phone comes back, the laptop item stays pending.
     *
     * @param  Collection<string, Asset>  $assets
     * @param  Collection<int, Employee>  $pool
     * @param  list<int>  $openCases
     */
    private function offboardingClearance(Collection $assets, Collection $pool, array $openCases): void
    {
        $employee = $pool->first(fn (Employee $row): bool => ! in_array($row->id, $openCases, true)
            && $row->branch?->code === 'CAI' && ! AssetAssignment::query()->where('employee_id', $row->id)->active()->exists() && $row->user()->doesntExist());
        $laptop = Asset::query()->where('status', 'available')->where('asset_category_id', $this->categories['LAPTOP']->id)->first();
        $phone = Asset::query()->where('status', 'available')->where('asset_category_id', $this->categories['PHONE']->id)->first();

        if ($employee === null || $laptop === null || $phone === null) {
            return;
        }

        $this->give($laptop, $employee, 380, acknowledged: true);
        $this->give($phone, $employee, 380, acknowledged: true);

        $case = app(OpenOffboardingCase::class)->handle($employee, [
            'type' => TerminationType::Resignation->value,
            'notice_date' => today()->subDays(5)->toDateString(),
            'last_working_date' => today()->addDays(25)->toDateString(),
            'reason' => 'Relocating abroad.',
            'eligible_for_rehire' => true,
        ], $this->hr);

        $this->custody->returnAsset(AssetAssignment::query()->where('asset_id', $phone->id)->active()->firstOrFail(), [
            'returned_on' => today()->toDateString(), 'condition_on_return' => 'good', 'return_notes' => 'Returned early with charger.',
        ], $this->hr);

        $this->command?->info('Asset demo: offboarding case #'.$case->id.' ('.$employee->employee_number.') has asset clearance items.');
    }

    private function give(Asset $asset, ?Employee $employee, int $daysAgo, bool $acknowledged, ?int $expectedReturn = null): ?AssetAssignment
    {
        if ($employee === null) {
            return null;
        }

        $date = CarbonImmutable::today()->subDays($daysAgo);
        $date = $employee->hire_date !== null && $date->lt($employee->hire_date) ? CarbonImmutable::parse($employee->hire_date) : $date;

        $assignment = $this->custody->assign($asset->refresh(), $employee, [
            'assigned_on' => $date->toDateString(),
            'expected_return_on' => $expectedReturn ? $date->addDays($expectedReturn)->toDateString() : null,
            'condition_on_issue' => $asset->condition->value,
            'handover_notes' => null,
        ], $this->hr);

        if ($acknowledged) {
            Carbon::setTestNow($date->addDay()->setTime(9, 30));
            $account = User::query()->where('employee_id', $employee->id)->first();
            $this->custody->acknowledge($assignment, $account ? (mt_rand(0, 1) ? 'portal' : 'api') : 'admin', $account ?? $this->hr);
            Carbon::setTestNow();
        }

        return $assignment;
    }

    private function returned(Asset $asset, Employee $employee, int $issuedDaysAgo, int $returnedDaysAgo, string $condition, ?string $note = null): void
    {
        $assignment = $this->give($asset, $employee, $issuedDaysAgo, acknowledged: true);

        if ($assignment === null) {
            return;
        }

        $returnedOn = CarbonImmutable::today()->subDays($returnedDaysAgo);
        $this->custody->returnAsset($assignment, [
            'returned_on' => ($returnedOn->lt($assignment->assigned_on) ? $assignment->assigned_on : $returnedOn)->toDateString(),
            'condition_on_return' => $condition,
            'return_notes' => $note,
        ], $this->hr);
    }
}
