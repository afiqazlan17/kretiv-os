<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Read-only trail of changes: Finance (BOD / Finance) and HR (BOD / HR).
class AuditLogController extends Controller
{
    public function finance(Request $request): View
    {
        abort_unless($request->user()->seesCompanyFinance(), 403);

        return $this->list($request, 'finance');
    }

    public function hr(Request $request): View
    {
        abort_unless($request->user()->canManageHr(), 403);

        return $this->list($request, 'hr');
    }

    private function list(Request $request, string $module): View
    {
        $filters = $request->only(['q', 'who', 'type', 'from', 'to']);
        $logs = AuditLog::where('module', $module)
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('summary', 'like', "%{$v}%"))
            ->when($filters['who'] ?? null, fn ($q, $v) => $q->where('user_name', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('subject_type', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')->paginate(40)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'module' => $module,
            'filters' => $filters,
            'people' => AuditLog::where('module', $module)->distinct()->orderBy('user_name')->pluck('user_name'),
            'types' => AuditLog::where('module', $module)->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
