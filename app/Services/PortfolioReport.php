<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\PaymentDetail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Reportes de dirección: colocación, cartera por antigüedad, cobranza esperada contra real e índice de morosidad.
 * Saldos y atrasos salen de LoanPortfolio para que coincidan con lo que se ve en cada préstamo.
 */
class PortfolioReport
{
    /** Tramos de atraso en días desde la fecha límite; un préstamo dentro de sus días de gracia sigue al corriente. */
    public const BUCKETS = [
        'current' => 'Al corriente',
        '1_30' => '1 a 30 días',
        '31_60' => '31 a 60 días',
        '61_90' => '61 a 90 días',
        '90_plus' => 'Más de 90 días',
    ];

    public function __construct(private LoanPortfolio $portfolio)
    {
    }

    public function build(CarbonInterface $from, CarbonInterface $to, ?CarbonInterface $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $aging = $this->aging($today);

        return [
            'placement' => $this->placement($from, $to),
            'aging' => $aging['buckets'],
            'delinquency' => $aging['delinquency'],
            'collections' => $this->collections($from, $to),
        ];
    }

    /** Créditos colocados (desembolsados) en el periodo, por mes y por producto. */
    public function placement(CarbonInterface $from, CarbonInterface $to): array
    {
        // Los préstamos aprobados antes de registrar desembolsos cuentan desde su aprobación
        $loans = Loan::whereNotNull('approved_at')->whereNull('reject_at')->get()
            ->map(fn (Loan $loan) => ['loan' => $loan, 'date' => Carbon::parse($loan->disbursed_at ?? $loan->approved_at)])
            ->filter(fn ($row) => $row['date']->between($from->copy()->startOfDay(), $to->copy()->endOfDay()));

        $months = collect($this->months($from, $to))->mapWithKeys(fn ($label, $key) => [$key => [
            'month' => $key, 'label' => $label, 'count' => 0, 'amount' => 0.0,
        ]])->all();

        foreach ($loans as $row) {
            $key = $row['date']->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['count']++;
                $months[$key]['amount'] = round($months[$key]['amount'] + (float) $row['loan']->amount, 2);
            }
        }

        $byProduct = $loans->groupBy(fn ($row) => $row['loan']->type ?: 'NORMAL')
            ->map(fn (Collection $rows, $type) => [
                'type' => $type,
                'count' => $rows->count(),
                'amount' => round($rows->sum(fn ($row) => (float) $row['loan']->amount), 2),
            ])->values();

        return [
            'count' => $loans->count(),
            'amount' => round($loans->sum(fn ($row) => (float) $row['loan']->amount), 2),
            'average' => $loans->count() ? round($loans->avg(fn ($row) => (float) $row['loan']->amount), 2) : 0.0,
            'months' => array_values($months),
            'by_product' => $byProduct,
        ];
    }

    /** Cartera vigente por tramo de atraso e índice de morosidad (saldo vencido entre saldo total). */
    public function aging(?CarbonInterface $today = null): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $rows = $this->rows($today);

        $buckets = collect(self::BUCKETS)->map(function ($label, $key) use ($rows) {
            $inBucket = $rows->where('bucket', $key);

            return [
                'key' => $key,
                'label' => $label,
                'count' => $inBucket->count(),
                'balance' => round($inBucket->sum('principal_balance'), 2),
                'overdue_amount' => round($inBucket->sum('overdue_amount'), 2),
            ];
        })->values();

        $total = round($rows->sum('principal_balance'), 2);
        $overdue = round($rows->where('bucket', '!=', 'current')->sum('principal_balance'), 2);
        $over90 = round($rows->where('bucket', '90_plus')->sum('principal_balance'), 2);

        return [
            'rows' => $rows,
            'buckets' => $buckets,
            'delinquency' => [
                'portfolio_balance' => $total,
                'overdue_balance' => $overdue,
                'overdue_90_balance' => $over90,
                'index' => $total > 0 ? round($overdue / $total * 100, 2) : 0.0,
                'index_90' => $total > 0 ? round($over90 / $total * 100, 2) : 0.0,
                'loans' => $rows->count(),
                'overdue_loans' => $rows->where('bucket', '!=', 'current')->count(),
            ],
        ];
    }

    /** Lo que se esperaba cobrar (cuotas que vencían en el mes) contra lo que de verdad entró. */
    public function collections(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        $months = collect($this->months($from, $to))->mapWithKeys(fn ($label, $key) => [$key => [
            'month' => $key, 'label' => $label, 'expected' => 0.0, 'collected' => 0.0, 'efficiency' => null,
        ]])->all();

        PaymentDetail::with('payment.loan')
            ->whereHas('payment.loan', fn ($q) => $q->whereNotNull('approved_at')->whereNull('reject_at'))
            ->get()
            ->each(function (PaymentDetail $detail) use (&$months, $start, $end) {
                $due = $detail->dueDate();
                $key = $due->format('Y-m');
                if ($due->between($start, $end) && isset($months[$key])) {
                    $months[$key]['expected'] = round($months[$key]['expected'] + (float) $detail->monthly_payment, 2);
                }
            });

        LoanPayment::whereNull('reverted_at')
            ->whereBetween('paid_at', [$start, $end])
            ->get()
            ->each(function (LoanPayment $payment) use (&$months) {
                $key = $payment->paid_at->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['collected'] = round($months[$key]['collected'] + $payment->amount, 2);
                }
            });

        $months = array_map(function ($month) {
            $month['efficiency'] = $month['expected'] > 0 ? round($month['collected'] / $month['expected'] * 100, 2) : null;

            return $month;
        }, array_values($months));

        $expected = round(array_sum(array_column($months, 'expected')), 2);
        $collected = round(array_sum(array_column($months, 'collected')), 2);

        return [
            'expected' => $expected,
            'collected' => $collected,
            'efficiency' => $expected > 0 ? round($collected / $expected * 100, 2) : null,
            'months' => $months,
        ];
    }

    /** Un renglón por préstamo vigente con su saldo y tramo de atraso (también sirve para exportar). */
    private function rows(CarbonInterface $today): Collection
    {
        return Loan::with(['customer', 'payment.details'])
            ->whereNotNull('approved_at')
            ->whereNull('reject_at')
            ->whereNull('paid_at')
            ->whereHas('payment.details')
            ->get()
            ->map(function (Loan $loan) use ($today) {
                $summary = $this->portfolio->summary($loan, $today);
                $days = $summary['is_overdue'] ? $summary['days_overdue'] : 0;

                return [
                    'loan_id' => $loan->id,
                    'customer' => $loan->customer?->full_name,
                    'type' => $loan->type ?: 'NORMAL',
                    'amount' => (float) $loan->amount,
                    'principal_balance' => max($summary['principal_balance'], 0.0),
                    'overdue_amount' => $summary['overdue_amount'],
                    'moratory_amount' => $summary['moratory_amount'],
                    'days_overdue' => $days,
                    'bucket' => $this->bucket($days),
                ];
            })
            ->sortByDesc('days_overdue')
            ->values();
    }

    private function bucket(int $days): string
    {
        return match (true) {
            $days <= 0 => 'current',
            $days <= 30 => '1_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /** @return array<string, string> 'Y-m' => 'nov 2026' */
    private function months(CarbonInterface $from, CarbonInterface $to): array
    {
        $months = [];
        foreach (CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()) as $month) {
            $months[$month->format('Y-m')] = $month->locale('es')->translatedFormat('M Y');
        }

        return $months;
    }
}
