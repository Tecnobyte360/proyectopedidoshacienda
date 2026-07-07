<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 🖨️ API para el AGENTE de impresión que corre en el PC de la impresora.
 *
 * Flujo (Modo B - cola en la nube):
 *   1. La tablet/Kivox encola un trabajo (TrabajoImpresion, estado=pendiente).
 *   2. El agente (servicio de Windows) hace POST /poll cada pocos segundos con
 *      su token → recibe los trabajos pendientes (y quedan marcados 'enviado').
 *   3. El agente imprime en la EPSON y hace POST /confirmar por cada uno.
 *
 * Autenticación simple por TOKEN de la impresora (no requiere login web).
 */
class ImpresionAgenteController extends Controller
{
    /** El agente pregunta si hay trabajos pendientes. */
    public function poll(Request $request)
    {
        $impresora = $this->impresoraDesdeToken($request);
        if (!$impresora) {
            return response()->json(['ok' => false, 'error' => 'Token inválido'], 401);
        }

        // Marca de "en línea". NO sobrescribimos printer_name con lo que manda el
        // agente: Kivox es la fuente de verdad del nombre de impresora (evita que
        // un appsettings viejo pise el nombre correcto). Solo lo tomamos del agente
        // si en Kivox está vacío (primer registro).
        $datos = [
            'ultima_conexion_at' => now(),
            'pc_nombre'          => $request->input('pc') ?: $impresora->pc_nombre,
        ];
        if (empty($impresora->printer_name) && $request->filled('printer_name')) {
            $datos['printer_name'] = $request->input('printer_name');
        }
        $impresora->forceFill($datos)->save();

        // Tomar hasta 5 trabajos pendientes y marcarlos 'enviado' para no duplicar.
        $trabajos = TrabajoImpresion::where('impresora_id', $impresora->id)
            ->where('estado', TrabajoImpresion::ESTADO_PENDIENTE)
            ->orderBy('id')
            ->limit(5)
            ->get();

        $salida = [];
        foreach ($trabajos as $t) {
            $t->forceFill([
                'estado'    => TrabajoImpresion::ESTADO_ENVIADO,
                'enviado_at'=> now(),
                'intentos'  => $t->intentos + 1,
            ])->save();

            $salida[] = [
                'id'        => $t->id,
                'tipo'      => $t->tipo,
                'contenido' => $t->contenido,
            ];
        }

        return response()->json([
            'ok'           => true,
            'printer_name' => $impresora->printer_name,
            'trabajos'     => $salida,
        ]);
    }

    /** El agente confirma que imprimió (o reporta error). */
    public function confirmar(Request $request)
    {
        $impresora = $this->impresoraDesdeToken($request);
        if (!$impresora) {
            return response()->json(['ok' => false, 'error' => 'Token inválido'], 401);
        }

        $id  = (int) $request->input('id');
        $ok  = filter_var($request->input('ok', true), FILTER_VALIDATE_BOOLEAN);
        $err = (string) $request->input('error', '');

        $t = TrabajoImpresion::where('impresora_id', $impresora->id)->find($id);
        if (!$t) {
            return response()->json(['ok' => false, 'error' => 'Trabajo no encontrado'], 404);
        }

        if ($ok) {
            $t->forceFill(['estado' => TrabajoImpresion::ESTADO_IMPRESO, 'impreso_at' => now(), 'error' => null])->save();
        } else {
            // Si falló, reintentar hasta 3 veces; luego marcar error.
            $estado = $t->intentos >= 3 ? TrabajoImpresion::ESTADO_ERROR : TrabajoImpresion::ESTADO_PENDIENTE;
            $t->forceFill(['estado' => $estado, 'error' => mb_substr($err, 0, 500)])->save();
            Log::warning('🖨️ Trabajo impresión falló', ['id' => $id, 'error' => $err, 'estado' => $estado]);
        }

        return response()->json(['ok' => true]);
    }

    private function impresoraDesdeToken(Request $request): ?Impresora
    {
        $token = (string) ($request->input('token') ?: $request->bearerToken());
        if ($token === '') return null;

        return Impresora::where('token', $token)->where('activa', true)->first();
    }
}
