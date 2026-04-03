<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function receive(Request $request)
    {
        // 🔥 LOG COMPLETO (TODO lo que llega)
        Log::info('📩 WEBHOOK WHATSAPP RECIBIDO', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl()
        ]);

        // También log crudo (por si no parsea JSON)
        Log::info('📦 RAW BODY', [
            'raw' => file_get_contents('php://input')
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'recibido'
        ]);
    }
}