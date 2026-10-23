<?php

namespace App\Http\Controllers;

use App\Mail\ContactConfirmation;
use App\Mail\ContactReceived;
use App\Models\ContactRequest;
use App\Services\Recaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactRequestController extends Controller
{
    public function store(Request $request, Recaptcha $recaptcha): JsonResponse
    {
        // Campo trampa: las personas no lo ven; si viene lleno es un bot y se descarta sin avisarle
        if (filled($request->input('website'))) {
            return response()->json(['message' => 'Recibimos tu solicitud.']);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:255',
            'phone' => ['nullable', 'regex:/^[0-9]{10}$/'],
            'message' => 'nullable|string|max:2000',
        ], [
            'name.required' => 'Escribe tu nombre.',
            'email.required' => 'Escribe tu correo.',
            'email.email' => 'El correo no es válido.',
            'phone.regex' => 'El teléfono debe tener 10 dígitos.',
        ]);

        if (! $recaptcha->passes($request->input('g-recaptcha-response'), $request->ip())) {
            return response()->json([
                'message' => 'Confirma que no eres un robot e inténtalo de nuevo.',
                'errors' => ['captcha' => ['Confirma que no eres un robot.']],
            ], 422);
        }

        $contact = ContactRequest::create($data + ['ip_address' => $request->ip()]);

        $this->send(fn () => Mail::to($contact->email)->send(new ContactConfirmation($contact)), 'confirmación al cliente');

        $team = config('promas.notification_email');
        if (filled($team)) {
            $this->send(fn () => Mail::to($team)->send(new ContactReceived($contact)), 'aviso al equipo');
        } else {
            Log::warning('CONTACT_NOTIFICATION_EMAIL no está configurado: la solicitud de cita no se avisó al equipo.', ['contact_request_id' => $contact->id]);
        }

        return response()->json(['message' => '¡Gracias! Recibimos tu solicitud y te enviamos un correo de confirmación.'], 201);
    }

    /** Un correo que falla no debe perder la solicitud ya guardada. */
    private function send(callable $send, string $what): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            report($e);
            Log::warning("No se pudo enviar el correo ({$what}): ".$e->getMessage());
        }
    }
}
