<?php

namespace App\Console\Commands;

use App\Models\PostalCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportSepomex extends Command
{
    protected $signature = 'sepomex:import {file : CPdescarga.txt de Correos de México, o el CSV del espejo gomflo/codigos-postales-mexico}';

    protected $description = 'Carga el catálogo nacional de códigos postales de SEPOMEX; reemplaza el que haya';

    /** Columna del archivo que alimenta cada campo, según el formato. */
    private const TXT_COLUMNS = ['code' => 'd_codigo', 'settlement' => 'd_asenta', 'settlement_type' => 'd_tipo_asenta', 'municipality' => 'D_mnpio', 'state' => 'd_estado', 'city' => 'd_ciudad'];

    private const CSV_COLUMNS = ['code' => 'codigo_postal', 'settlement' => 'asentamiento', 'settlement_type' => 'tipo', 'municipality' => 'municipio', 'state' => 'estado', 'city' => 'ciudad'];

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_readable($path)) {
            $this->error("No se puede leer el archivo {$path}.");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        try {
            $total = DB::transaction(fn () => $this->load($handle));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            fclose($handle);
        }

        $this->info("Listo: {$total} asentamientos cargados.");

        return self::SUCCESS;
    }

    /** Reemplaza el catálogo con el contenido del archivo; si no es el archivo de Correos, la transacción se deshace. */
    private function load($handle): int
    {
        PostalCode::query()->delete();

        $header = null;
        $columns = null;
        $csv = false;
        $rows = [];
        $total = 0;

        while (($line = fgets($handle)) !== false) {
            // El archivo de Correos viene en ISO-8859-1; el CSV del espejo, en UTF-8 con BOM
            $line = rtrim(mb_check_encoding($line, 'UTF-8') ? $line : mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'), "\r\n");
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);

            // La primera línea del TXT es una leyenda; el encabezado es la que trae los nombres de columna
            if (! $header) {
                if (str_contains($line, 'd_codigo|')) {
                    $header = array_flip(explode('|', $line));
                    $columns = self::TXT_COLUMNS;
                } elseif (str_starts_with($line, 'codigo_postal,')) {
                    $header = array_flip(str_getcsv($line));
                    $columns = self::CSV_COLUMNS;
                    $csv = true;
                }

                continue;
            }

            $fields = $csv ? str_getcsv($line) : explode('|', $line);
            $value = fn (string $key) => trim($fields[$header[$columns[$key]] ?? -1] ?? '');
            $code = str_pad($value('code'), 5, '0', STR_PAD_LEFT);
            if (! preg_match('/^\d{5}$/', $code) || $value('settlement') === '') {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'settlement' => $value('settlement'),
                'settlement_type' => $value('settlement_type') ?: null,
                'municipality' => $value('municipality'),
                'state' => $value('state'),
                'city' => $value('city') ?: null,
            ];

            if (count($rows) === 1000) {
                PostalCode::insert($rows);
                $total += count($rows);
                $rows = [];
            }
        }

        if (! $header) {
            throw new RuntimeException('El archivo no tiene el encabezado de SEPOMEX (d_codigo|d_asenta|... en TXT, o codigo_postal,asentamiento,... en CSV).');
        }

        if ($rows) {
            PostalCode::insert($rows);
            $total += count($rows);
        }

        return $total;
    }
}
