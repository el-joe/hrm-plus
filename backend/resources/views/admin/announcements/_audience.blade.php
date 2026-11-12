{{-- Audience summary. Variables: $announcement (targets loaded), $targetLabels ("type:id" => name), $limit (optional). --}}
@if ($announcement->isTargeted())
    @php($names = $announcement->targets->map(fn ($target) => $targetLabels[$target->target_type->value.':'.$target->target_id] ?? '#'.$target->target_id))
    <span class="text-body-sm text-text-secondary">{{ $names->take($limit ?? 3)->implode(app()->getLocale() === 'ar' ? '، ' : ', ') }}@if ($names->count() > ($limit ?? 3)) +{{ $names->count() - ($limit ?? 3) }}@endif</span>
@else
    <span class="text-body-sm text-text-secondary">{{ __('announcements.everyone') }}</span>
@endif
