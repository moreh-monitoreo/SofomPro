<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Punto único para dejar rastro de las acciones sensibles (aprobar, mover dinero, tocar documentos). */
class AuditLogger
{
    public static function record(string $action, ?Model $subject = null, ?int $loanId = null, ?array $before = null, ?array $after = null, ?string $note = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'loan_id' => $loanId,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'note' => $note ? mb_substr($note, 0, 255) : null,
            'ip' => request()?->ip(),
        ]);
    }
}
