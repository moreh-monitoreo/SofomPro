<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'INE (frente)',
            'INE (reverso)',
            'CURP',
            'Comprobante de domicilio',
            'Constancia de situación fiscal (RFC)',
            'Comprobante de ingresos',
            'Estado de cuenta bancario',
            'Contrato firmado',
            'Anexos firmados',
            'Plan funerario',
            'Fotografía del cliente',
            'Otro',
        ];

        foreach ($types as $name) {
            DocumentType::firstOrCreate(['name' => $name]);
        }
    }
}
