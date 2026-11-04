<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Consulta de solo lectura de la bitácora: quién hizo qué, sobre qué préstamo y cuándo. */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'action' => 'nullable|in:'.implode(',', array_keys(AuditLog::ACTIONS)),
            'loan' => 'nullable|integer|min:1',
            'user' => 'nullable|integer|min:1',
        ]);

        $logs = AuditLog::with('user:id,name,email')
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['loan'] ?? null, fn ($q, $v) => $q->where('loan_id', $v))
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'action' => $log->action,
                'label' => AuditLog::ACTIONS[$log->action] ?? $log->action,
                'user' => $log->user ? ['name' => $log->user->name, 'email' => $log->user->email] : null,
                'loan_id' => $log->loan_id,
                'before' => $log->before,
                'after' => $log->after,
                'note' => $log->note,
                'ip' => $log->ip,
            ]);

        return Inertia::render('Admin/AuditLog', [
            'logs' => $logs,
            'actions' => AuditLog::ACTIONS,
            'filters' => ['action' => $filters['action'] ?? '', 'loan' => $filters['loan'] ?? '', 'user' => $filters['user'] ?? ''],
        ]);
    }
}
