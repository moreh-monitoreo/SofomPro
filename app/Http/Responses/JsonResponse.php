<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse as IlluminateJsonResponse;

class JsonResponse
{
    public static function success($data = null, $message = 'success', $statusCode = 200)
    {
        return self::make($data, $message, $statusCode);
    }

    public static function error($message = 'error', $statusCode = 400)
    {
        return self::make(null, $message, $statusCode);
    }

    private static function make($data, $message, $statusCode)
    {
        $response = [
            'message' => $message,
            'data' => $data,
        ];

        return new IlluminateJsonResponse($response, $statusCode);
    }
}
