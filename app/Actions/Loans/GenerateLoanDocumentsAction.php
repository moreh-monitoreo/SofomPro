<?php

namespace App\Actions\Loans;

use App\Actions\CreateQuotationTableAction;
use App\Models\Loan;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class GenerateLoanDocumentsAction
{
    public const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Tasa ordinaria mensual fija: con garantía 74.28% anual (6.19%), sin garantía 79.56% anual (6.63%). Cláusula Séptima. */
    public const MONTHLY_RATE_GUARANTEE = 0.0619;
    public const MONTHLY_RATE_PERSONAL = 0.0663;

    public const IVA = 0.16;

    /** Tasa anual ordinaria por defecto según el tipo de préstamo (se sustituye por la del plan guardado, si existe). */
    public const ANNUAL_RATE_GUARANTEE = 74.28;
    public const ANNUAL_RATE_PERSONAL = 79.56;
    public const DEFAULT_OPENING_FEE = 3;

    public static function annualRate(?string $type): float
    {
        return $type === 'FUNERARIO' ? self::ANNUAL_RATE_GUARANTEE : self::ANNUAL_RATE_PERSONAL;
    }

    private static function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    private const CONTRACT_SIMPLE = 'CONTRATO DE APERTURA DE CRÉDITO SIMPLE.docx';
    private const CONTRACT_GUARANTEE = 'CONTRATO DE APERTURA DE CRÉDITO SIMPLE CON GARANTÍA PRENDARIA SIN TRANSMISIÓN DE POSESIÓN.docx';

    public static function monthlyRate(?string $type): float
    {
        return $type === 'FUNERARIO' ? self::MONTHLY_RATE_GUARANTEE : self::MONTHLY_RATE_PERSONAL;
    }

    /**
     * Genera los documentos del préstamo aprobado: contrato y anexos A al D.
     * Se usan las plantillas de document/sin-garantia (NORMAL) o document/con-garantia (FUNERARIO).
     *
     * @return array<int, \App\Models\Documentation>
     */
    public function handle(Loan $loan): array
    {
        $loan->loadMissing('customer');
        $customer = $loan->customer;
        $guarantee = $loan->type === 'FUNERARIO';
        $dir = $guarantee ? 'con-garantia' : 'sin-garantia';
        $folio = $loan->folio ?: 'PRM-' . str_pad((string) $loan->id, 6, '0', STR_PAD_LEFT);
        $loan->update(['folio' => $folio]);

        $name = mb_strtoupper($customer->full_name);
        $today = Carbon::now()->locale('es')->translatedFormat('j \d\e F \d\e Y');
        $common = [
            'Nombre del cliente' => $name,
            '[Nombre del Representante]' => 'Lic. ELIA DE JESÚS OCHOA TIRADO',
        ];
        $dateLine = ['/^_{5,}\.$/u' => "Culiacán, Sinaloa, a {$today}."];

        // Los valores (monto, plazo, tasa y comisión) salen del plan guardado del préstamo; si aún no hay plan, de los valores por defecto
        $loan->loadMissing('payment');
        $plan = $loan->payment;
        $amount = (float) ($plan->request_amount ?? $loan->amount);
        $term = (int) ($plan->total_months ?? $loan->term);
        $defaultRate = self::annualRate($loan->type);
        $annual = $plan ? round($plan->month_rate * 12 * 100, 2) : $defaultRate;
        $fee = $plan ? (float) $plan->opening_fees : (float) self::DEFAULT_OPENING_FEE;
        $schedule = $this->amortization($amount, $term, Carbon::parse($loan->start_at ?? now()), $annual);

        // El texto de la plantilla trae la tasa por defecto; se sustituye por la definida para este préstamo
        $rateTexts = [];
        if (abs($annual - $defaultRate) > 0.001) {
            $rateTexts = [
                self::pct($defaultRate) . '%' => self::pct($annual) . '%',
                self::pct($defaultRate / 12) . '%' => self::pct($annual / 12) . '%',
            ];
        }
        $common += $rateTexts;

        $documents = [];

        if ($guarantee) {
            $documents[] = $this->build($loan, "{$dir}/" . self::CONTRACT_GUARANTEE, 'Contrato de crédito simple con garantía', 'CONTRATO', $common, $dateLine);
        } else {
            $documents[] = $this->build($loan, "{$dir}/" . self::CONTRACT_SIMPLE, 'Contrato de crédito simple', 'CONTRATO', $common, $dateLine);
        }

        $documents[] = $this->build($loan, "{$dir}/ANEXO A. CARÁTULA CONTRATO.docx", 'Anexo A. Carátula de contrato', 'ANEXO_A', [
            'XXX MESES' => $term . ' MESES',
            'Fecha límite de pago: ___________________' => 'Fecha límite de pago: ' . ($schedule['rows'][0]['date'] ?? ''),
            'Número: _____________________' => "Número: {$folio}",
        ], [
            '/^ORDINARIA:\s*[\d.]+%$/u' => 'ORDINARIA: ' . self::pct($annual) . '%',
            '/^Comisión por Apertura:\s*[\d.]+\s*%$/u' => 'Comisión por Apertura: ' . self::pct($fee) . ' %',
        ], function (DOMXPath $xp) use ($amount, $schedule) {
            // Monto de crédito y monto total a pagar (dos líneas en blanco consecutivas)
            $values = [number_format($amount, 2), number_format($schedule['total'], 2)];
            foreach ($xp->query('//w:p') as $p) {
                if ($values && preg_match('/^_{5,}$/', trim($this->paragraphText($p)))) {
                    $this->setParagraphText($p, '$' . array_shift($values));
                }
            }
        });

        $documents[] = $this->build($loan, "{$dir}/ANEXO B. TABLA DE AMORTIZACIÓN.docx", 'Anexo B. Tabla de amortización', 'ANEXO_B', [
            'FECHA: __________________' => 'FECHA: ' . $today,
            'ACREDITADO: __________________' => "ACREDITADO: {$name}",
            'CRÉDITO No.: ________________________' => "CRÉDITO No.: {$folio}",
        ], [], fn (DOMXPath $xp) => $this->fillAmortizationTable($xp, $schedule));

        $cName = $guarantee
            ? 'ANEXO C. AUTORIZACIÓN DE CONSULTA DE INFORMACIÓN CREDITICIA .docx'
            : 'ANEXO C. AUTORIZACIÓN DE CONSULTA DE INFORMACIÓN CREDITICIA.docx';
        $documents[] = $this->build($loan, "{$dir}/{$cName}", 'Anexo C. Autorización de consulta crediticia', 'ANEXO_C', [
            '________________________________________________________' => $name,
        ]);

        $documents[] = $this->build($loan, "{$dir}/ANEXO D. BENEFICIARIO CONTROLADOR.docx", 'Anexo D. Beneficiario controlador', 'ANEXO_D', [
            'yo: _______________________________________________________en' => "yo: {$name} en",
        ]);

        return $documents;
    }

    /**
     * Tabla de amortización con el mismo cálculo del plan de pagos del administrador
     * (CreateQuotationTableAction): interés mensual sobre saldo insoluto e IVA sobre intereses.
     */
    public function amortization(float $amount, int $term, Carbon $start, float $annualRate): array
    {
        if ($amount <= 0 || $term <= 0) {
            throw new RuntimeException('El préstamo no tiene monto o plazo definidos.');
        }

        $table = app(CreateQuotationTableAction::class)->handle($amount, $term / 12, 0, $annualRate);
        $rows = [];
        $totals = ['capital' => 0, 'interest' => 0, 'tax' => 0, 'payment' => 0];

        foreach ($table['payments'] as $i => $p) {
            $rows[] = [
                'number' => $i + 1,
                'date' => $start->copy()->addMonthsNoOverflow($i)->format('d/m/Y'),
                'capital' => $p['principal_payment'],
                'interest' => $p['interest_payment'],
                'tax' => $p['tax_payment'],
                'balance' => $p['balance'],
                'payment' => $p['monthly_payment'],
            ];
            $totals['capital'] += $p['principal_payment'];
            $totals['interest'] += $p['interest_payment'];
            $totals['tax'] += $p['tax_payment'];
            $totals['payment'] += $p['monthly_payment'];
        }

        return ['rows' => $rows, 'total' => round($totals['payment'], 2), 'totals' => $totals];
    }

    private function build(Loan $loan, string $template, string $label, string $type, array $replacements, array $regexReplacements = [], ?callable $extra = null)
    {
        $source = base_path('document/' . $template);
        if (! is_file($source)) {
            throw new RuntimeException("No se encontró la plantilla {$template}");
        }

        $relative = "loans/{$loan->id}/generated/{$type}.docx";
        $target = Storage::disk('public')->path($relative);
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        copy($source, $target);

        $zip = new ZipArchive();
        if ($zip->open($target) !== true) {
            throw new RuntimeException("No se pudo abrir la plantilla {$template}");
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($zip->getFromName('word/document.xml'));
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W_NS);

        foreach ($xp->query('//w:p') as $p) {
            $text = $this->paragraphText($p);
            $new = $text;
            foreach ($replacements as $search => $replace) {
                $new = str_replace($search, $replace, $new);
            }
            foreach ($regexReplacements as $pattern => $replace) {
                if (preg_match($pattern, trim($new))) {
                    $new = preg_replace($pattern, $replace, trim($new));
                }
            }
            if ($new !== $text) {
                $this->setParagraphText($p, $new);
            }
        }

        if ($extra) {
            $extra($xp);
        }

        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();

        return $loan->documentations()->updateOrCreate(
            ['type' => $type],
            ['name' => $label, 'path' => $relative]
        );
    }

    private function paragraphText(DOMElement $p): string
    {
        $text = '';
        foreach ($p->getElementsByTagNameNS(self::W_NS, 't') as $t) {
            $text .= $t->textContent;
        }

        return $text;
    }

    /** Escribe el texto en la primera corrida del párrafo y vacía las demás conservando el formato de la primera. */
    private function setParagraphText(DOMElement $p, string $text): void
    {
        $nodes = $p->getElementsByTagNameNS(self::W_NS, 't');
        if ($nodes->length === 0) {
            $run = $p->ownerDocument->createElementNS(self::W_NS, 'w:r');
            $run->appendChild($p->ownerDocument->createElementNS(self::W_NS, 'w:t'));
            $p->appendChild($run);
            $nodes = $p->getElementsByTagNameNS(self::W_NS, 't');
        }
        foreach ($nodes as $i => $t) {
            $t->nodeValue = '';
            if ($i === 0) {
                $t->appendChild($p->ownerDocument->createTextNode($text));
                $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            }
        }
    }

    private function fillAmortizationTable(DOMXPath $xp, array $schedule): void
    {
        $table = $xp->query('//w:tbl')->item(0);
        if (! $table) {
            return;
        }
        $rows = [];
        foreach ($table->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'tr') {
                $rows[] = $child;
            }
        }
        if (count($rows) < 3) {
            return;
        }

        $prototype = $rows[1];
        $totalRow = $rows[count($rows) - 1];
        foreach (array_slice($rows, 1, -1) as $old) {
            $table->removeChild($old);
        }

        $money = fn ($n) => '$' . number_format($n, 2);
        foreach ($schedule['rows'] as $r) {
            $tr = $prototype->cloneNode(true);
            $this->fillRow($tr, [
                (string) $r['number'], $r['date'], $money($r['capital']), $money($r['interest']),
                $money($r['tax']), $money($r['balance']), $money($r['payment']),
            ]);
            $table->insertBefore($tr, $totalRow);
        }

        $t = $schedule['totals'];
        $this->fillRow($totalRow, [
            'TOTAL', '', $money($t['capital']), $money($t['interest']), $money($t['tax']), '', $money($t['payment']),
        ], true);
    }

    private function fillRow(DOMElement $tr, array $values, bool $keepFirst = false): void
    {
        $i = 0;
        foreach ($tr->childNodes as $tc) {
            if (! ($tc instanceof DOMElement) || $tc->localName !== 'tc') {
                continue;
            }
            $value = $values[$i++] ?? '';
            if ($keepFirst && $i === 1) {
                continue; // conserva la etiqueta "TOTAL" de la plantilla
            }
            $p = $tc->getElementsByTagNameNS(self::W_NS, 'p')->item(0);
            if ($p) {
                $this->setParagraphText($p, $value);
            }
        }
    }
}
