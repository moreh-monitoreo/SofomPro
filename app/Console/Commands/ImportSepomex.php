<?php

namespace App\Console\Commands;

use App\Models\PostalCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportSepomex extends Command
{
    protected $signature = 'sepomex:import {file : Archivo CPdescarga.txt de Correos de México (formato TXT)}';

    protected $description = 'Carga el catálogo nacional de códigos postales de SEPOMEX; reemplaza el que haya';

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
        $rows = [];
        $total = 0;

        while (($line = fgets($handle)) !== false) {
            // El archivo de Correos viene en ISO-8859-1
            $line = rtrim(mb_check_encoding($line, 'UTF-8') ? $line : mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'), "\r\n");
            $fields = explode('|', $line);

            // La primera línea es una leyenda; el encabezado es la que trae d_codigo
            if (! $header) {
                if (in_array('d_codigo', $fields, true)) {
                    $header = array_flip($fields);
                }

                continue;
            }

            $value = fn (string $column) => trim($fields[$header[$column] ?? -1] ?? '');
            $code = $value('d_codigo');
            if (! preg_match('/^\d{5}$/', $code)) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'settlement' => $value('d_asenta'),
                'settlement_type' => $value('d_tipo_asenta') ?: null,
                'municipality' => $value('D_mnpio'),
                'state' => $value('d_estado'),
                'city' => $value('d_ciudad') ?: null,
            ];

            if (count($rows) === 1000) {
                PostalCode::insert($rows);
                $total += count($rows);
                $rows = [];
            }
        }

        if (! $header) {
            throw new RuntimeException('El archivo no tiene el encabezado de SEPOMEX (d_codigo|d_asenta|d_tipo_asenta|D_mnpio|d_estado|d_ciudad...).');
        }

        if ($rows) {
            PostalCode::insert($rows);
            $total += count($rows);
        }

        return $total;
    }
}
