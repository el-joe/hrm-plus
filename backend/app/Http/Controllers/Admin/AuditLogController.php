<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): View
    {
        $filters = $request->validated();

        $logs = AuditLog::query()
            ->with('user')
            ->when($filters['user_id'] ?? null, fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['auditable_type'] ?? null, fn (Builder $query, string $type) => $query->where('auditable_type', $type))
            ->when($filters['auditable_id'] ?? null, fn (Builder $query, $id) => $query->where('auditable_id', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('created_at', '<=', Carbon::parse($date)->endOfDay()))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'admins' => User::query()->admins()->orderBy('name')->get(['id', 'name']),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'types' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit-logs.show', ['log' => $auditLog->load('user')]);
    }
}
