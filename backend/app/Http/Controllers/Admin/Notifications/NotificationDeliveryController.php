<?php

namespace App\Http\Controllers\Admin\Notifications;

use App\Enums\NotificationDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationEventRegistry;
use App\Services\Notifications\Push\PushChannelResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The e-mail / push delivery log (notifications.view): status, attempts, provider and error of each send.
 * Message texts are never stored here. Failed queue jobs can be retried with `php artisan queue:retry`.
 */
class NotificationDeliveryController extends Controller
{
    public function index(Request $request, NotificationEventRegistry $events, PushChannelResolver $push): View
    {
        $filters = $request->validate([
            'channel' => ['nullable', 'in:mail,push'],
            'status' => ['nullable', Rule::enum(NotificationDeliveryStatus::class)],
            'event' => ['nullable', Rule::in(array_keys($events->all()))],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $deliveries = NotificationDelivery::query()->with('user')
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $query->where('channel', $channel))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('recipient', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')->paginate(30)->withQueryString();

        $week = NotificationDelivery::query()->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.notifications.deliveries.index', [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'statuses' => NotificationDeliveryStatus::cases(),
            'events' => collect($events->all())->keys()->mapWithKeys(fn (string $key): array => [$key => $events->label($key)])->sort(),
            'counts' => [
                'sent' => (int) ($week['sent'] ?? 0),
                'failed' => (int) ($week['failed'] ?? 0),
                'simulated' => (int) ($week['simulated'] ?? 0),
                'devices' => DeviceToken::query()->count(),
            ],
            'pushSimulated' => ! $push->isLive(),
            'mailLogged' => config('mail.default') === 'log',
        ]);
    }
}
