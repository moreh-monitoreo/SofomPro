<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Services\LoanPortfolio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Tablero de solicitudes por etapa, con filtros, en vista de tabla o de columnas (Kanban). */
class PipelineController extends Controller
{
    private const PER_PAGE = 25;
    private const BOARD_LIMIT = 150;

    public function index(Request $request, LoanPortfolio $portfolio)
    {
        $filters = $request->validate([
            'stage' => 'nullable|in:'.implode(',', array_keys(Loan::STAGES)),
            'q' => 'nullable|string|max:100',
            'type' => 'nullable|in:NORMAL,FUNERARIO',
            'overdue' => 'nullable|boolean',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'view' => 'nullable|in:table,board',
        ]);

        $view = $filters['view'] ?? 'table';

        $base = fn (): Builder => $this->filtered(Loan::query(), $filters);

        $counts = collect(Loan::STAGES)->map(fn ($label, $stage) => $base()->stage($stage)->count());

        $query = $base()->with(['customer', 'payment.details'])->latest('id');
        if (! empty($filters['stage']) && $view === 'table') {
            $query->stage($filters['stage']);
        }

        $loans = $view === 'board'
            ? $query->limit(self::BOARD_LIMIT)->get()
            : $query->paginate(self::PER_PAGE)->withQueryString();

        $present = fn (Loan $loan) => $this->present($loan, $portfolio);

        return Inertia::render('Pipeline', [
            'loans' => $view === 'board'
                ? $loans->map($present)->values()
                : $loans->through($present),
            'counts' => $counts,
            'stages' => Loan::STAGES,
            'filters' => [
                'stage' => $filters['stage'] ?? null,
                'q' => $filters['q'] ?? '',
                'type' => $filters['type'] ?? null,
                'overdue' => (bool) ($filters['overdue'] ?? false),
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'view' => $view,
            ],
            'boardLimit' => self::BOARD_LIMIT,
        ]);
    }

    private function filtered(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(function (Builder $q) use ($like) {
                    $q->where('folio', 'like', $like)
                        ->orWhereHas('customer', function (Builder $c) use ($like) {
                            $c->where('name', 'like', $like)->orWhere('second_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)->orWhere('maiden_name', 'like', $like)
                                ->orWhere('curp', 'like', $like)->orWhere('rfc', 'like', $like)
                                ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like);
                        });
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when(! empty($filters['overdue']), fn (Builder $q) => $q->overdue())
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('created_at', '<=', $to));
    }

    private function present(Loan $loan, LoanPortfolio $portfolio): array
    {
        $summary = $portfolio->summary($loan);

        return [
            'id' => $loan->id,
            'folio' => $loan->folio,
            'customer' => [
                'id' => $loan->customer->id,
                'full_name' => $loan->customer->full_name,
                'phone' => $loan->customer->phone,
            ],
            'amount' => $loan->amount,
            'term' => $loan->term,
            'type' => $loan->type,
            'score' => $loan->score,
            'stage' => $loan->stage,
            'stage_label' => $loan->stage_label,
            'created_at' => $loan->created_at?->toDateString(),
            'is_overdue' => $summary['is_overdue'],
            'days_overdue' => $summary['days_overdue'],
            'overdue_amount' => $summary['overdue_amount'],
            'pending_amount' => $summary['pending_amount'],
        ];
    }
}
