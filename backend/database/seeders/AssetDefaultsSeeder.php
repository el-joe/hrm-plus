<?php

namespace Database\Seeders;

use App\Enums\OnboardingTaskAction;
use App\Models\AssetCategory;
use App\Models\OnboardingTemplateTask;
use Illuminate\Database\Seeder;

/**
 * Asset reference data for every environment (editable afterwards): the
 * default asset categories, and the link of the onboarding checklist task
 * "Assign company assets (laptop, access card)" to the asset handover form.
 */
class AssetDefaultsSeeder extends Seeder
{
    /**
     * [code, name_ar, name_en, requires serial].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: bool}>
     */
    public const CATEGORIES = [
        ['LAPTOP', 'حاسب محمول', 'Laptop', true],
        ['DESKTOP', 'حاسب مكتبي', 'Desktop computer', true],
        ['PHONE', 'هاتف محمول', 'Mobile phone', true],
        ['SIM', 'شريحة اتصال', 'SIM card', true],
        ['OFFICE', 'معدات مكتبية', 'Office equipment', false],
        ['UNIFORM', 'زي العمل', 'Uniform', false],
        ['VEHICLE', 'سيارة الشركة', 'Company vehicle', true],
        ['ACCESS', 'بطاقة دخول', 'Access card', true],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as [$code, $ar, $en, $serial]) {
            AssetCategory::query()->firstOrCreate(['code' => $code], ['name_ar' => $ar, 'name_en' => $en, 'requires_serial' => $serial, 'is_active' => true]);
        }

        OnboardingTemplateTask::query()->whereNull('action')->where('title_en', 'Assign company assets (laptop, access card)')
            ->update(['action' => OnboardingTaskAction::IssueAssets->value]);
    }
}
