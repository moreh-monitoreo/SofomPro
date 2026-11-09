<?php

namespace App\Http\Controllers;

use App\Http\Responses\JsonResponse;
use App\Models\PostalCode;

/** Consulta pública del catálogo de SEPOMEX para llenar municipio, estado y colonias en la solicitud. */
class PostalCodeController extends Controller
{
    public function show(string $code)
    {
        $result = PostalCode::lookup($code);

        return $result
            ? JsonResponse::success($result)
            : JsonResponse::error('No encontramos ese código postal; escribe tu colonia y municipio.', 404);
    }
}
