<?php

namespace App\Http\Controllers;

use App\Http\Responses\JsonResponse;
use App\Models\CollectionAction;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gestiones de cobranza (llamadas, visitas, promesas de pago) y asignación de gestores. */
class CollectionController extends Controller
{
    public function store(Request $request, int $id)
    {
        $loan = Loan::findOrFail($id);
        $user = $request->user();

        if (! self::canRecord($user, $loan)) {
            return JsonResponse::error('Solo puedes registrar gestiones en los préstamos que tienes asignados.', 403);
        }
        if (! $loan->approved_at || $loan->reject_at) {
            return JsonResponse::error('Solo se registran gestiones de cobranza en préstamos aprobados.', 422);
        }

        $isPromise = $request->input('outcome') === 'promesa';
        $data = $request->validate([
            'channel' => ['required', Rule::in(array_keys(CollectionAction::CHANNELS))],
            'outcome' => ['required', Rule::in(array_keys(CollectionAction::OUTCOMES))],
            'notes' => 'nullable|string|max:1000',
            'promise_amount' => [Rule::requiredIf($isPromise), 'nullable', 'numeric', 'min:1'],
            'promise_date' => [Rule::requiredIf($isPromise), 'nullable', 'date', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(60)->toDateString()],
        ], [
            'channel.required' => 'Indica cómo se hizo la gestión.',
            'outcome.required' => 'Indica el resultado de la gestión.',
            'promise_amount.required' => 'Captura el importe que prometió pagar el cliente.',
            'promise_date.required' => 'Captura la fecha en que el cliente prometió pagar.',
            'promise_date.after_or_equal' => 'La fecha de la promesa no puede ser pasada.',
            'promise_date.before_or_equal' => 'La promesa debe ser a no más de 60 días.',
        ]);

        // Una promesa nueva reemplaza a la que seguía pendiente
        if ($isPromise) {
            $loan->collectionActions()->where('promise_status', 'pendiente')
                ->update(['promise_status' => 'incumplida', 'promise_resolved_at' => now()]);
        }

        $action = $loan->collectionActions()->create([
            'user_id' => $user->id,
            'channel' => $data['channel'],
            'outcome' => $data['outcome'],
            'notes' => $data['notes'] ?? null,
            'promise_amount' => $isPromise ? round((float) $data['promise_amount'], 2) : null,
            'promise_date' => $isPromise ? $data['promise_date'] : null,
            'promise_status' => $isPromise ? 'pendiente' : null,
        ]);

        AuditLogger::record('collection.recorded', $action, $loan->id, after: array_filter([
            'channel' => $action->channel,
            'outcome' => $action->outcome,
            'promise_amount' => $action->promise_amount,
            'promise_date' => $action->promise_date?->toDateString(),
        ], fn ($v) => $v !== null), note: $action->notes);

        return JsonResponse::success(self::present($action->load('user:id,name')), $isPromise
            ? 'Promesa de pago registrada; se dará por cumplida cuando entre el pago.'
            : 'Gestión registrada.');
    }

    /** Asigna (o quita) el gestor de cobranza de uno o varios préstamos. */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'loan_ids' => 'required|array|min:1',
            'loan_ids.*' => 'integer|exists:loans,id',
            'collector_id' => 'nullable|integer',
        ], ['loan_ids.required' => 'Selecciona al menos un préstamo.']);

        $collector = null;
        if ($data['collector_id'] ?? null) {
            $collector = User::find($data['collector_id']);
            if (! $collector || ! $collector->isActive() || ! $collector->hasAnyRole(User::COLLECTOR_ROLES)) {
                return JsonResponse::error('Esa persona no puede tener cartera asignada.', 422);
            }
        }

        $loans = Loan::whereIn('id', $data['loan_ids'])->get();
        foreach ($loans as $loan) {
            if ((int) $loan->collector_id === (int) $collector?->id) {
                continue;
            }
            $before = $loan->collector_id;
            $loan->forceFill(['collector_id' => $collector?->id])->save();
            AuditLogger::record('collection.assigned', $loan, $loan->id, before: ['collector_id' => $before], after: ['collector_id' => $collector?->id]);
        }

        return JsonResponse::success(
            ['loan_ids' => $loans->pluck('id'), 'collector' => $collector?->only('id', 'name')],
            $collector ? "Se asignaron {$loans->count()} préstamo(s) a {$collector->name}." : "Se quitó el gestor de {$loans->count()} préstamo(s).",
        );
    }

    public static function canRecord(User $user, Loan $loan): bool
    {
        return $user->hasAnyRole(User::COLLECTION_ROLES)
            || ($user->hasRole('Gestor de cobranza') && (int) $loan->collector_id === (int) $user->id);
    }

    /** Personas a las que se les puede asignar cartera. */
    public static function collectors(): array
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', User::COLLECTOR_ROLES))->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => $u->only('id', 'name'))->all();
    }

    public static function present(CollectionAction $action): array
    {
        return [
            'id' => $action->id,
            'created_at' => $action->created_at->toIso8601String(),
            'user' => $action->user?->name,
            'channel' => $action->channel,
            'outcome' => $action->outcome,
            'notes' => $action->notes,
            'promise_amount' => $action->promise_amount,
            'promise_date' => $action->promise_date?->toDateString(),
            'promise_status' => $action->promise_status,
        ];
    }
}
