{{--
    Stage control of one application: a plain PATCH form (works without JavaScript and is the
    keyboard alternative to dragging). On the board, resources/js/pages/pipeline-board.js sends it with fetch.
    Variables: $application, $stages
--}}
<form method="POST" action="{{ route('admin.applications.move', $application) }}" class="flex items-end gap-2" data-move-form>
    @csrf
    @method('PATCH')
    <label class="sr-only" for="move-{{ $application->id }}">{{ __('recruitment.pipeline.move_to', ['name' => $application->candidate->name]) }}</label>
    <select id="move-{{ $application->id }}" name="stage_id" data-move-select
        class="h-8 min-w-0 flex-1 rounded-md border border-border-default bg-surface-default px-2 text-body-sm text-text-primary focus-visible:shadow-focus focus-visible:outline-none">
        @foreach ($stages as $stage)
            <option value="{{ $stage->id }}" @selected($stage->id === $application->stage_id) @disabled($stage->category === App\Enums\StageCategory::Hired)>{{ $stage->name }}</option>
        @endforeach
    </select>
    <x-button size="sm" variant="secondary" data-move-submit>{{ __('recruitment.pipeline.move') }}</x-button>
</form>
