<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta a la API MOREH el contrato (plan funerario) por folio.
 * La API identifica la tabla (ContratosA-F / ContratosPF-PF5) según el rango del folio.
 */
class MorehContractService
{
    /**
     * @return array{status:int, body:array}
     */
    public function findByFolio(string $folio): array
    {
        $url = trim((string) config('services.moreh.url'));
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $key = config('services.moreh.key');

        if (! $url || ! $key) {
            return ['status' => 503, 'body' => ['message' => 'La conexión con MOREH no está configurada (MOREH_API_URL / MOREH_SERVICE_KEY).']];
        }

        try {
            $ca = config('services.moreh.ca_bundle');
            $response = Http::timeout((int) config('services.moreh.timeout'))
                ->withOptions(['verify' => ($ca && is_file($ca)) ? $ca : true])
                ->acceptJson()
                ->withHeaders(['X-Service-Key' => $key])
                ->get(rtrim($url, '/') . '/api/plan-funerario/' . rawurlencode(trim($folio)), [
                    'sucursal' => strtoupper(preg_replace('/_db$/i', '', (string) config('services.moreh.sucursal'))),
                ]);
        } catch (\Throwable $e) {
            Log::warning('MOREH no disponible: ' . $e->getMessage());

            return ['status' => 502, 'body' => ['message' => 'No se pudo conectar con MOREH. Intenta de nuevo.']];
        }

        // 404 JSON de la API = folio inexistente; 404 HTML = la API aún no tiene el endpoint desplegado
        if ($response->status() === 404 && $response->json('success') === false) {
            return ['status' => 404, 'body' => ['message' => "No se encontró un plan funerario con el folio {$folio}."]];
        }

        if ($response->failed()) {
            Log::warning('MOREH respondió ' . $response->status() . ' en ' . $url . ' (¿es la URL pública de la API y no el proxy de la base de datos?)');

            return ['status' => 502, 'body' => ['message' => 'MOREH no pudo consultar el contrato.']];
        }

        return ['status' => 200, 'body' => ['contract' => $response->json('data')]];
    }
}
