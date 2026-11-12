{{-- Skill level as 5 dots + the number. Variables: $level (0–5). --}}
<span class="inline-flex items-center gap-1" title="{{ __('training.skills_matrix.level', ['level' => $level]) }}">
    <span class="sr-only">{{ __('training.skills_matrix.level', ['level' => $level]) }}</span>
    @for ($dot = 1; $dot <= App\Models\Skill::MAX_LEVEL; $dot++)
        <span @class(['inline-block size-2 rounded-full', 'bg-action-primary-default' => $dot <= $level, 'bg-border-default' => $dot > $level]) aria-hidden="true"></span>
    @endfor
</span>
