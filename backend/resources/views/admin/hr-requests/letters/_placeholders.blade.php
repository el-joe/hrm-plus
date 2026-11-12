{{-- Placeholder reference of letter templates. Variables: $placeholders (list<string>). --}}
<dl class="grid gap-x-4 gap-y-1 text-body-sm sm:grid-cols-2">
    @foreach ($placeholders as $name)
        <div class="flex flex-wrap gap-1">
            <dt><code dir="ltr" class="rounded-xs bg-surface-muted px-1 text-caption-md">{{ str_repeat('{', 2).$name.str_repeat('}', 2) }}</code></dt>
            <dd class="text-text-secondary">{{ __('hr_requests.letters.placeholder_labels.'.$name) }}</dd>
        </div>
    @endforeach
</dl>
