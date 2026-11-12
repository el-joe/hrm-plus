{{--
    An announcement body. $html MUST be the stored value, sanitised on save by
    App\Services\Announcements\HtmlSanitizer (allow-list): that is why it is output raw.
    Variables: $html, $direction (rtl|ltr), $locale.
--}}
<div dir="{{ $direction }}" lang="{{ $locale }}" data-announcement-body
    class="text-body-md text-text-primary [&_a]:text-text-link [&_a]:underline [&_blockquote]:border-s-4 [&_blockquote]:border-border-default [&_blockquote]:ps-4 [&_blockquote]:text-text-secondary [&_h3]:mb-2 [&_h3]:text-h5 [&_h4]:mb-2 [&_h4]:text-label-lg [&_li]:mb-1 [&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:mb-3 [&_ul]:mb-3 [&_ul]:list-disc [&_ul]:ps-6 [&>*:last-child]:mb-0">{!! $html !!}</div>
