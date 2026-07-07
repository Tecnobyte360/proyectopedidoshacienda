<?php

namespace App\Http\Controllers\Sap;

use App\Http\Controllers\Controller;
use App\Services\Sap\Asistente\AsistenteSapService;
use App\Services\Sap\SapServiceLayerClient;
use App\Services\TenantManager;
use Illuminate\Http\Request;

/**
 * Asistente IA + SAP (módulo Ventas). Interfaz de chat + endpoint de mensajes.
 */
class AsistenteSapController extends Controller
{
    public function index()
    {
        return view('sap.asistente');
    }

    /** POST: recibe un mensaje del usuario y devuelve la respuesta de la IA. */
    public function mensaje(Request $request, AsistenteSapService $asistente)
    {
        $data = $request->validate([
            'mensaje'         => 'required|string|max:2000',
            'historial'       => 'nullable|array',
            'historial.*.role'    => 'nullable|string',
            'historial.*.content' => 'nullable|string',
        ]);

        $resultado = $asistente->responder($data['mensaje'], $data['historial'] ?? []);

        return response()->json($resultado);
    }

    /** GET: diagnóstico rápido de conexión al Service Layer del tenant actual. */
    public function ping(TenantManager $tenants)
    {
        $sap = SapServiceLayerClient::paraTenant($tenants->id());
        return response()->json($sap->ping());
    }
}
