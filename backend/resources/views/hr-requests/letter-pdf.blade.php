{{-- HR letter PDF (mPDF): plain tables + inline CSS. Variables: App\Services\HrRequests\HrLetterDocument::pdf(). The body is
     rendered text (no HTML from the template): each paragraph is escaped. --}}
@php
    $align = $dir === 'rtl' ? 'right' : 'left';
    $opposite = $dir === 'rtl' ? 'left' : 'right';
    $t = fn (string $key) => __('hr_requests.letters.pdf.'.$key, [], $locale);
@endphp
<html dir="{{ $dir }}" lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: cairo; font-size: 11pt; color: #1f2937; line-height: 1.7; }
        .muted { color: #6b7280; font-size: 9pt; }
        h1 { font-size: 15pt; margin: 0; }
        h2 { font-size: 14pt; text-align: center; margin: 24px 0 18px; }
        p { margin: 0 0 12px; text-align: justify; }
        table { width: 100%; border-collapse: collapse; }
        .signature td { padding-top: 48px; vertical-align: top; }
        .line { border-top: 0.5pt solid #9ca3af; width: 55%; padding-top: 4px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 60%; text-align: {{ $align }};">
                @if ($logo_path)
                    <img src="{{ $logo_path }}" style="height: 42px;"><br>
                @endif
                <h1>{{ $company['name'] }}</h1>
                @if ($company['address'])<div class="muted">{{ $company['address'] }}</div>@endif
            </td>
            <td style="width: 40%; text-align: {{ $opposite }};" class="muted">
                {{ $t('reference') }}: <bdo dir="ltr">{{ $reference }}</bdo><br>
                {{ $t('date') }}: <bdo dir="ltr">{{ $date }}</bdo>
            </td>
        </tr>
    </table>

    <h2>{{ $title }}</h2>

    @foreach ($paragraphs as $paragraph)
        {{-- Dates, references and numbers with separators stay left-to-right inside Arabic text (mPDF bidi). --}}
        <p>{!! nl2br(preg_replace('/[A-Za-z0-9]+(?:[-\/.,][A-Za-z0-9]+)+/u', '<bdo dir="ltr">$0</bdo>', e($paragraph))) !!}</p>
    @endforeach

    <table class="signature">
        <tr>
            <td style="text-align: {{ $align }};">
                <div class="line">{{ $t('signature') }}</div>
                <div class="muted">{{ $t('hr_department') }} — {{ $company['name'] }}</div>
            </td>
            <td style="text-align: {{ $opposite }};" class="muted">{{ $t('stamp') }}</td>
        </tr>
    </table>
</body>
</html>
