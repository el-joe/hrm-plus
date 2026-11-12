{{--
    Performance tab (Phase 8a): reviews with scores and final rating, then goals / KPIs.
    Variables: $employee, $performanceReviews, $performanceGoals.
--}}
<x-card :title="__('performance.reviews.title')" padding="none" data-region="profile-reviews">
    <x-table :caption="__('performance.reviews.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('performance.fields.cycle') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.reviewer') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.overall_score') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.final_rating') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($performanceReviews as $review)
            <x-table.row>
                <x-table.td :label="__('performance.fields.cycle')">
                    @can('view', $review)
                        <a class="text-text-link hover:underline" href="{{ route('admin.performance-reviews.show', $review) }}">{{ $review->cycle->name }}</a>
                    @else
                        {{ $review->cycle->name }}
                    @endcan
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $review->reference }}</span>
                </x-table.td>
                <x-table.td :label="__('performance.fields.reviewer')">{{ $review->reviewer?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('performance.fields.overall_score')">@include('admin.performance._score', ['score' => $review->overall_score, 'max' => null])</x-table.td>
                <x-table.td :label="__('performance.fields.final_rating')">@include('admin.performance._rating', ['level' => $review->status->isFinal() ? $review->finalRating : null, 'calibrated' => $review->calibrated])</x-table.td>
                <x-table.td :label="__('performance.fields.status')">@include('admin.performance._review-status', ['status' => $review->status])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="award" :title="__('performance.reviews.empty')" />
        @endforelse
    </x-table>
</x-card>

<x-card :title="__('performance.goals.title')" padding="none" data-region="profile-goals">
    @can('createFor', [App\Models\PerformanceGoal::class, $employee])
        <x-slot:actions>
            <x-button size="sm" variant="secondary" icon="plus" :href="route('admin.performance-goals.create', ['employee' => $employee->id])">{{ __('performance.goals.create') }}</x-button>
        </x-slot:actions>
    @endcan
    <x-table :caption="__('performance.goals.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('performance.fields.title') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.cycle') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.weight') }}</x-table.th>
                <x-table.th class="min-w-44">{{ __('performance.fields.progress') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($performanceGoals as $goal)
            <x-table.row>
                <x-table.td :label="__('performance.fields.title')">
                    <a class="text-text-link hover:underline" href="{{ route('admin.performance-goals.edit', $goal) }}">{{ $goal->title }}</a>
                    <span class="block text-caption-md text-text-tertiary">{{ $goal->type->label() }}</span>
                </x-table.td>
                <x-table.td :label="__('performance.fields.cycle')">{{ $goal->cycle?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('performance.fields.weight')" class="tabular-nums"><span dir="ltr">{{ rtrim(rtrim((string) $goal->weight, '0'), '.') }}%</span></x-table.td>
                <x-table.td :label="__('performance.fields.progress')">@include('admin.performance._progress', ['value' => $goal->progress, 'label' => $goal->title])</x-table.td>
                <x-table.td :label="__('performance.fields.status')"><x-badge :tone="$goal->status->tone()">{{ $goal->status->label() }}</x-badge></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="target" :title="__('performance.goals.empty')" />
        @endforelse
    </x-table>
</x-card>
