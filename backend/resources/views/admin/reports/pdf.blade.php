{{-- Report PDF (mPDF, App\Services\Reports\ReportExporter::pdf). Plain tables + inline CSS; Cairo font. --}}
@php
    $align = $dir === 'rtl' ? 'right' : 'left';
    $opposite = $dir === 'rtl' ? 'left' : 'right';
@endphp
<html dir="{{ $dir }}" lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: cairo; font-size: 8.5pt; color: #1f2937; }
        h1 { font-size: 14pt; margin: 0 0 2px 0; }
        .muted { color: #6b7280; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        .filters td { padding: 2px 4px; font-size: 8pt; }
        .data th { background: #eef2f7; padding: 5px 4px; font-weight: bold; text-align: {{ $align }}; border-bottom: 0.75pt solid #9ca3af; }
        .data td { padding: 4px; border-bottom: 0.5pt solid #e5e7eb; text-align: {{ $align }}; }
        .data td.num, .data th.num { text-align: {{ $opposite }}; }
        .ltr { direction: ltr; }
        .total td { font-weight: bold; border-top: 1pt solid #9ca3af; background: #f8fafc; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="text-align: {{ $align }};">
                <h1>{{ $report->title() }}</h1>
                <div class="muted">{{ $company }} · {{ __('reports.areas.'.$report->area()) }}</div>
            </td>
            <td style="text-align: {{ $opposite }};" class="muted">{{ __('reports.pdf.generated_at') }}: <bdo dir="ltr">{{ $generatedAt }}</bdo></td>
        </tr>
    </table>

    @if ($filters !== [])
        <table class="filters" style="margin-top: 6px;">
            @foreach ($filters as $label => $value)
                <tr><td style="width: 22%; text-align: {{ $align }};" class="muted">{{ $label }}</td><td style="text-align: {{ $align }};">{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif

    <table class="data" style="margin-top: 8px;">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th @class(['num' => $column->isNumeric()])>{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $key => $column)
                        @php $text = \App\Services\Reports\ReportExporter::display($column, $row[$key] ?? null); @endphp
                        <td @class(['num' => $column->isNumeric()])>@if ($column->isLtr())<bdo dir="ltr">{{ $text }}</bdo>@else{{ $text }}@endif</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted">{{ __('reports.ui.empty') }}</td></tr>
            @endforelse
            @if ($totals !== [] && $rows !== [])
                <tr class="total">
                    @foreach ($columns as $key => $column)
                        <td @class(['num' => $column->isNumeric()])>
                            @if (isset($totals[$key]))<bdo dir="ltr">{{ \App\Services\Reports\ReportExporter::display($column, $totals[$key]) }}</bdo>@elseif ($loop->first){{ __('reports.ui.total') }}@endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
    <p class="muted">{{ __('reports.pdf.rows', ['count' => \App\Support\Formatter::number(count($rows))]) }}</p>
</body>
</html>
