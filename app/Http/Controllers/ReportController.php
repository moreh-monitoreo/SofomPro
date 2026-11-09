<?php

namespace App\Http\Controllers;

use App\Services\PortfolioReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes para Gerencia y Administración; el periodo por defecto son los últimos 12 meses. */
class ReportController extends Controller
{
    public function index(Request $request, PortfolioReport $report)
    {
        $filters = $request->validate([
            'from' => 'nullable|date_format:Y-m',
            'to' => 'nullable|date_format:Y-m|after_or_equal:from',
        ], [
            'to.after_or_equal' => 'El mes final no puede ser anterior al inicial.',
        ]);

        $to = isset($filters['to']) ? Carbon::createFromFormat('!Y-m', $filters['to'])->endOfMonth() : today()->endOfMonth();
        $from = isset($filters['from']) ? Carbon::createFromFormat('!Y-m', $filters['from']) : $to->copy()->subMonths(11)->startOfMonth();

        // Más de 36 meses vuelve ilegible la gráfica; se recorta desde el inicio
        if ($from->diffInMonths($to) > 36) {
            $from = $to->copy()->subMonths(35)->startOfMonth();
        }

        return Inertia::render('Reports/Index', [
            'report' => $report->build($from, $to),
            'filters' => ['from' => $from->format('Y-m'), 'to' => $to->format('Y-m')],
            'buckets' => PortfolioReport::BUCKETS,
        ]);
    }

    /** Detalle por préstamo de la cartera por antigüedad, en CSV para Excel. */
    public function exportAging(PortfolioReport $report): StreamedResponse
    {
        $rows = $report->aging()['rows'];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Préstamo', 'Cliente', 'Producto', 'Monto otorgado', 'Saldo de capital', 'Monto vencido', 'Moratorios con IVA', 'Días de atraso', 'Tramo']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['loan_id'], $row['customer'], $row['type'],
                    number_format($row['amount'], 2, '.', ''), number_format($row['principal_balance'], 2, '.', ''),
                    number_format($row['overdue_amount'], 2, '.', ''), number_format($row['moratory_amount'], 2, '.', ''),
                    $row['days_overdue'], PortfolioReport::BUCKETS[$row['bucket']],
                ]);
            }
            fclose($out);
        }, 'cartera-por-antiguedad-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
