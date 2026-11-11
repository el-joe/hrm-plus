{{--
    The signed-in interviewer's scorecard (admin and portal).
    Variables: $interview (with evaluationForm.criteria), $myScorecard (nullable, with scores), $action (URL)
--}}
@php
    $scores = $myScorecard?->scores->keyBy('evaluation_criterion_id') ?? collect();
    $scale = (int) config('recruitment.score_scale');
@endphp
<form method="POST" action="{{ $action }}" class="flex flex-col gap-4" data-validate>
    @csrf
    @if ($interview->evaluationForm)
        <p class="text-body-sm text-text-secondary">{{ __('recruitment.scorecards.scale_help', ['scale' => $scale]) }}</p>
        @foreach ($interview->evaluationForm->criteria as $criterion)
            <fieldset class="rounded-lg border border-border-subtle p-3">
                <legend class="px-1 text-label-md">{{ $criterion->name }} <span class="text-caption-md text-text-tertiary">× {{ $criterion->weight }}</span></legend>
                @if ($criterion->description)
                    <p class="mb-2 text-caption-md text-text-tertiary">{{ $criterion->description }}</p>
                @endif
                <div class="flex flex-wrap gap-3" role="radiogroup" aria-label="{{ $criterion->name }}">
                    @for ($score = 1; $score <= $scale; $score++)
                        <x-form.radio name="scores[{{ $criterion->id }}]" :value="$score" :id="'score-'.$criterion->id.'-'.$score" :label="(string) $score"
                            :checked="(string) old('scores.'.$criterion->id, $scores->get($criterion->id)?->score) === (string) $score" required />
                    @endfor
                </div>
                @error('scores.'.$criterion->id)<p class="mt-1 text-caption-md text-feedback-danger-text">{{ $message }}</p>@enderror
                <x-form.input name="comments_by_criterion[{{ $criterion->id }}]" :label="__('recruitment.scorecards.criterion_comment')" class="mt-2" maxlength="500"
                    :value="old('comments_by_criterion.'.$criterion->id, $scores->get($criterion->id)?->comment)" />
            </fieldset>
        @endforeach
    @else
        <x-alert tone="info">{{ __('recruitment.scorecards.no_form') }}</x-alert>
    @endif
    <x-form.select name="recommendation" :label="__('recruitment.fields.recommendation')" required>
        <option value="">—</option>
        @foreach (App\Enums\ScorecardRecommendation::cases() as $recommendation)
            <option value="{{ $recommendation->value }}" @selected(old('recommendation', $myScorecard?->recommendation?->value) === $recommendation->value)>{{ $recommendation->label() }}</option>
        @endforeach
    </x-form.select>
    <x-form.textarea name="comments" :label="__('recruitment.fields.comments')" rows="4" maxlength="5000">{{ old('comments', $myScorecard?->comments) }}</x-form.textarea>
    <div><x-button icon="check">{{ $myScorecard ? __('recruitment.scorecards.update') : __('recruitment.scorecards.submit') }}</x-button></div>
</form>
