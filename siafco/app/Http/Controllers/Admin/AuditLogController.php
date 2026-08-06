<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogQueryService;
use App\Services\AuditLogSanitizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogQueryService $logs,
        private readonly AuditLogSanitizer $sanitizer,
    ) {}

    public function index(AuditLogIndexRequest $request)
    {
        $filters = $request->filters();
        $records = $this->logs->paginate($filters);

        return view('administration.audit.index', [
            'title' => 'Auditoria',
            'records' => $records,
            'filters' => $filters,
            'actors' => User::query()
                ->where(fn ($query) => $query->where('user_type', 'internal')->orWhereNull('user_type'))
                ->whereIn('role', array_keys(config('internal_roles.labels', [])))
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'roles' => config('internal_roles.labels', []),
            'modules' => $this->logs->modules(),
            'logService' => $this->logs,
            'sanitizer' => $this->sanitizer,
            'canExport' => $request->user()->hasPermission('audit.export'),
        ]);
    }

    public function show(Request $request, AuditLog $audit)
    {
        abort_unless($request->user()?->hasPermission('audit.view'), 403);
        $audit->load('user:id,name,role,user_type');

        return view('administration.audit.show', [
            'title' => 'Detalle de auditoria',
            'audit' => $audit,
            'module' => $this->logs->moduleFor($audit->action),
            'metadata' => $this->sanitizer->sanitize($audit->metadata ?? []),
        ]);
    }

    public function export(AuditLogIndexRequest $request): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('audit.export'), 403);

        $filters = $request->filters();
        $filename = 'auditoria-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['fecha', 'actor', 'rol', 'accion', 'modulo', 'entidad', 'ip', 'resumen']);

            $this->logs->query($filters)->chunk(200, function ($records) use ($handle) {
                foreach ($records as $record) {
                    fputcsv($handle, [
                        optional($record->created_at)->format('Y-m-d H:i:s'),
                        $record->user?->name ?? 'Sistema',
                        $record->user?->role ?? '',
                        $record->action,
                        $this->logs->moduleFor($record->action),
                        class_basename($record->auditable_type ?: ''),
                        $record->ip_address,
                        $this->sanitizer->summary($record->metadata ?? []),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
