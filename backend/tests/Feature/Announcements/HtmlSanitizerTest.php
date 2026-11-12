<?php

use App\Services\Announcements\HtmlSanitizer;

/*
| Announcement bodies (Phase 8b): allow-list HTML sanitising, stored sanitised and rendered raw.
*/

beforeEach(function () {
    $this->sanitizer = new HtmlSanitizer;
});

it('keeps the allowed formatting and Arabic text', function () {
    $html = '<h3>عنوان</h3><p>مرحبًا <strong>بكم</strong> <em>جميعًا</em> <u>هنا</u></p><ul><li>أولًا</li></ul><ol><li>1</li></ol><blockquote>اقتباس</blockquote>';

    expect($this->sanitizer->sanitize($html))->toBe($html);
});

it('removes scripts, styles and embedded objects with their content', function (string $input) {
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('alert')
        ->not->toContain('<script')
        ->not->toContain('<style')
        ->not->toContain('<iframe')
        ->not->toContain('<svg')
        ->not->toContain('<object');
})->with([
    'script' => '<p>Hi</p><script>alert(1)</script>',
    'script in caps' => '<SCRIPT>alert(1)</SCRIPT><p>Hi</p>',
    'style' => '<style>body{background:url("javascript:alert(1)")}</style><p>Hi</p>',
    'iframe' => '<iframe src="https://evil.test/alert"></iframe>',
    'svg onload' => '<svg onload="alert(1)"><circle /></svg>',
    'object' => '<object data="alert.swf"></object>',
    'math' => '<math><mi xlink:href="javascript:alert(1)">x</mi></math>',
]);

it('drops every event handler and style attribute', function () {
    $output = $this->sanitizer->sanitize('<p onclick="steal()" style="color:red" class="x" id="y">Hi</p><img src=x onerror=alert(1)>');

    expect($output)->toBe('<p>Hi</p>');
});

it('only keeps http, https and mailto links and hardens them', function () {
    $output = $this->sanitizer->sanitize(
        '<a href="javascript:alert(1)">a</a><a href=" jav&#x09;ascript:alert(2)">b</a><a href="data:text/html;base64,PHNjcmlwdD4=">c</a>'
        .'<a href="vbscript:msgbox(1)">d</a><a href="https://example.com/policy">e</a><a href="mailto:hr@example.com">f</a>'
    );

    expect($output)->not->toContain('javascript')
        ->not->toContain('data:')
        ->not->toContain('vbscript')
        ->toContain('abcd')
        ->toContain('<a href="https://example.com/policy" rel="noopener noreferrer" target="_blank">e</a>')
        ->toContain('<a href="mailto:hr@example.com" rel="noopener noreferrer" target="_blank">f</a>');
});

it('re-serialises malformed and nested markup', function () {
    expect($this->sanitizer->sanitize('<b><i>nested</b></i>'))->toBe('<b><i>nested</i></b>')
        ->and($this->sanitizer->sanitize('<div><span>keep</span></div><!-- comment -->'))->toBe('keep')
        ->and($this->sanitizer->sanitize('<p>open <strong>unclosed'))->toBe('<p>open <strong>unclosed</strong></p>');
});

it('never turns encoded entities into markup', function () {
    expect($this->sanitizer->sanitize('&lt;script&gt;alert(1)&lt;/script&gt;'))->toBe('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>')
        ->and($this->sanitizer->sanitize('<p>&lt;img src=x onerror=alert(1)&gt;</p>'))->not->toContain('<img');
});

it('turns plain text into paragraphs and keeps line breaks', function () {
    expect($this->sanitizer->sanitize("line one\nline two\n\nparagraph two"))
        ->toBe("<p>line one<br>\nline two</p><p>paragraph two</p>");
});

it('builds a plain-text version for the mobile app', function () {
    expect($this->sanitizer->toText('<p>First &amp; foremost</p><ul><li>one</li><li>two</li></ul>'))->toBe("First & foremost\none\ntwo");
});
