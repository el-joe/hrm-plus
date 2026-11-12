<?php

namespace App\Http\Controllers\Admin\Notifications;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\NotificationTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Notification templates: every event of config/notification_events.php, one text per channel
 * (in-app, e-mail, push) and language. notifications.view reads, notifications.manage edits / resets.
 * The preview renders unsaved text with sample values (no JS needed: "Update preview" posts the form).
 */
class NotificationTemplateController extends Controller
{
    public function __construct(private readonly NotificationEventRegistry $events, private readonly NotificationTemplates $templates) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', Rule::in($this->events->modules())],
            'customised' => ['nullable', 'boolean'],
        ]);
        $customised = NotificationTemplate::query()->where('is_customised', true)->distinct()->pluck('event')->flip();

        $rows = collect($this->events->all())
            ->map(fn (array $event, string $key): array => [
                'key' => $key,
                'label' => $this->events->label($key),
                'module' => $event['module'],
                'audience' => $event['audience'],
                'mandatory' => $this->events->isMandatory($key),
                'customised' => $customised->has($key),
            ])
            ->filter(fn (array $row): bool => (! filled($filters['module'] ?? null) || $row['module'] === $filters['module'])
                && (empty($filters['customised']) || $row['customised'])
                && (! filled($filters['search'] ?? null) || Str::contains(Str::lower($row['label'].' '.$row['key']), Str::lower($filters['search']))))
            ->groupBy('module');

        $ordered = collect($this->events->modules())->filter(fn (string $module): bool => $rows->has($module))
            ->mapWithKeys(fn (string $module): array => [$module => $rows[$module]]);

        return view('admin.notifications.templates.index', [
            'groups' => $ordered,
            'filters' => $filters,
            'modules' => $this->events->modules(),
            'customisedCount' => $customised->count(),
        ]);
    }

    public function edit(Request $request, string $event): View
    {
        abort_unless($this->events->has($event), 404);

        [$channel, $locale] = $this->selection($request);
        $stored = NotificationTemplate::query()->with('editor')->where(['event' => $event, 'channel' => $channel, 'locale' => $locale])->first();
        $current = $this->templates->resolve($event, $channel, $locale);
        $subject = (string) old('subject', session('preview.subject', $current['subject']));
        $body = (string) old('body', session('preview.body', $current['body']));

        return view('admin.notifications.templates.edit', [
            'event' => $event,
            'definition' => $this->events->get($event),
            'label' => $this->events->label($event),
            'channel' => $channel,
            'locale' => $locale,
            'channels' => $this->events->channels(),
            'locales' => $this->templates->locales(),
            'stored' => $stored,
            'subject' => $subject,
            'body' => $body,
            'placeholders' => $this->events->placeholders($event),
            'samples' => $this->templates->sampleValues($event, $locale),
            'preview' => $this->templates->preview($event, $subject, $body, $locale),
            'default' => $this->events->defaultText($event, $locale),
            'canManage' => $request->user()->can('notifications.manage'),
        ]);
    }

    public function update(Request $request, string $event): RedirectResponse
    {
        abort_unless($this->events->has($event), 404);

        $data = $request->validate([
            'channel' => ['required', Rule::in($this->events->channels())],
            'locale' => ['required', Rule::in($this->templates->locales())],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'intent' => ['nullable', 'in:save,preview'],
        ]);
        $back = route('admin.notification-templates.edit', ['event' => $event, 'channel' => $data['channel'], 'locale' => $data['locale']]);

        if (($data['intent'] ?? 'save') === 'preview') {
            return redirect()->to($back)->with('preview', ['subject' => $data['subject'], 'body' => $data['body']]);
        }

        $this->templates->save($event, $data['channel'], $data['locale'], $data['subject'], $data['body'], $request->user());

        return redirect()->to($back)->with('status', __('notifications.templates.saved'));
    }

    public function reset(Request $request, string $event): RedirectResponse
    {
        abort_unless($this->events->has($event), 404);

        $data = $request->validate([
            'channel' => ['nullable', Rule::in($this->events->channels())],
            'locale' => ['nullable', Rule::in($this->templates->locales())],
        ]);
        $this->templates->reset($event, $request->user(), $data['channel'] ?? null, $data['locale'] ?? null);

        return redirect()->route('admin.notification-templates.edit', array_filter(['event' => $event, 'channel' => $data['channel'] ?? null, 'locale' => $data['locale'] ?? null]))
            ->with('status', __('notifications.templates.reset_done'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function selection(Request $request): array
    {
        $channel = in_array($request->query('channel'), $this->events->channels(), true) ? (string) $request->query('channel') : 'database';
        $locale = in_array($request->query('locale'), $this->templates->locales(), true) ? (string) $request->query('locale') : app()->getLocale();

        return [$channel, $locale];
    }
}
