<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Asentamiento del catálogo de SEPOMEX (se importa con `php artisan sepomex:import`). */
class PostalCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'settlement', 'settlement_type', 'municipality', 'state', 'city'];

    /** Municipio, estado y colonias de un código postal, o null si no está en el catálogo. */
    public static function lookup(string $code): ?array
    {
        $rows = static::where('code', $code)->orderBy('settlement')->get();
        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'code' => $code,
            'municipality' => $rows->first()->municipality,
            'state' => $rows->first()->state,
            'city' => $rows->first()->city,
            'settlements' => $rows->pluck('settlement')->unique()->values(),
        ];
    }
}
