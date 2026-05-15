<?php

declare(strict_types=1);

namespace App\Http\Controllers\AuditLogs;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $actionFilter = $request->string('action')->toString();
        if ($actionFilter !== '') {
            $query->where('action', 'like', $actionFilter.'%');
        }

        $from = $request->string('from')->toString();
        if ($from !== '') {
            $query->where('created_at', '>=', $from);
        }

        $to = $request->string('to')->toString();
        if ($to !== '') {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $entries = $query->paginate(50)->withQueryString();

        $rows = collect($entries->items())
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'created_at' => $log->created_at->toIso8601String(),
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'payload' => $log->payload,
                'actor' => $log->actor !== null
                    ? ['id' => $log->actor->id, 'name' => $log->actor->name]
                    : null,
            ])
            ->all();

        return Inertia::render('audit-logs/Index', [
            'entries' => $rows,
            'pagination' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
            'filters' => [
                'action' => $actionFilter,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
