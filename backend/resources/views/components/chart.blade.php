{{--
    Chart (Chart.js through resources/js/ui/chart.js). Pass the config built by
    App\Support\Charts\ChartData. Without JavaScript (or for screen readers) the same data is
    available in the table under the chart. Props: chart, label (accessible name), height (CSS size).
--}}
@props(['chart', 'label', 'height' => '16rem'])

@php
    $options = $chart['options'] ?? [];
    $format = fn ($value) => ($options['percent'] ?? false)
        ? \App\Support\Formatter::percent($value, $options['decimals'] ?? 1)
        : \App\Support\Formatter::number(is_float($value) ? (string) $value : $value, $options['decimals'] ?? 0);
@endphp

<figure data-chart {{ $attributes->class('flex flex-col gap-3') }}>
    <div class="relative w-full" style="block-size: {{ $height }}">
        <canvas role="img" aria-label="{{ $label }}"></canvas>
    </div>
    <script type="application/json" data-chart-config>{!! json_encode($chart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
    <details class="group text-caption-md text-text-secondary">
        <summary class="cursor-pointer select-none text-label-sm text-text-link">{{ __('dashboard.chart.show_data') }}</summary>
        <div class="relative mt-2 overflow-x-auto">
            <table class="w-full text-start text-body-sm">
                <caption class="sr-only">{{ $label }}</caption>
                <thead>
                    <tr class="border-b border-border-subtle">
                        <th scope="col" class="py-1.5 pe-3 text-start text-label-sm text-text-secondary">{{ __('dashboard.chart.category') }}</th>
                        @foreach ($chart['datasets'] as $dataset)
                            <th scope="col" class="py-1.5 pe-3 text-end text-label-sm text-text-secondary">{{ $dataset['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chart['labels'] as $index => $categoryLabel)
                        <tr class="border-b border-border-subtle last:border-0">
                            <th scope="row" class="py-1.5 pe-3 text-start font-normal text-text-primary">{{ $categoryLabel }}</th>
                            @foreach ($chart['datasets'] as $dataset)
                                <td class="py-1.5 pe-3 text-end tabular-nums text-text-primary" dir="ltr">{{ $format($dataset['data'][$index] ?? null) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
