<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Events\MensajeWhatsappRecibido;

class WhatsappWebhookController extends Controller
{
    public function receive(Request $request)
    {
        // 🔥 LOG 1: TODO lo que llega
        Log::info('📩 WEBHOOK WHATSAPP RECIBIDO', [
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        // 🔐 Seguridad
        if ($request->header('X-API-KEY') !== env('WHATSAPP_BOT_TOKEN')) {

            Log::warning('🚫 Token inválido', [
                'token_recibido' => $request->header('X-API-KEY')
            ]);

            return response()->json(['error' => 'No autorizado'], 401);
        }

        $from = $request->input('from');
        $name = $request->input('name');
        $message = $request->input('message');

        Log::info('📨 Mensaje procesado', [
            'from' => $from,
            'name' => $name,
            'message' => $message
        ]);

        // 🤖 RESPUESTA IA (por ahora mock)
        $respuesta = "Hola $name 😊 recibí: $message";

        Log::info('🤖 Respuesta generada', [
            'respuesta' => $respuesta
        ]);

        // 📡 Evento en tiempo real
        broadcast(new MensajeWhatsappRecibido([
            'from' => $from,
            'name' => $name,
            'message' => $message,
            'respuesta' => $respuesta
        ]));

        Log::info('📡 Evento enviado a Reverb');

        // 📤 Enviar respuesta a WhatsApp
        try {

            $token = $this->loginWhatsapp();

            Log::info('🔐 Token obtenido', ['token' => $token]);

            $response = Http::withoutVerifying()
                ->withToken($token)
                ->post('https://wa-api.tecnobyteapp.com:1422/api/messages/send', [
                    'to' => $from,
                    'message' => $respuesta
                ]);

            Log::info('📤 Respuesta enviada a WhatsApp', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

        } catch (\Exception $e) {

            Log::error('❌ Error enviando mensaje', [
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true
        ]);
    }

    private function loginWhatsapp()
    {
        $response = Http::withoutVerifying()->post(
            'https://wa-api.tecnobyteapp.com:1422/auth/login',
            [
                'email' => 'stiven.madrid@doblamos.com',
                'password' => '12345678'
            ]
        );

        Log::info('🔑 Login WhatsApp', [
            'status' => $response->status(),
            'body' => $response->json()
        ]);

        return $response->json()['token'] ?? null;
    }
}