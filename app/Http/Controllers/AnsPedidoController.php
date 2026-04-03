<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AnsPedido;
use Illuminate\Support\Facades\Log;

class AnsPedidoController extends Controller
{
    /**
     * Listar todos los ANS
     */
    public function index()
    {
        try {
            $ans = AnsPedido::orderBy('accion')->get();

            return response()->json([
                'status' => 'success',
                'data' => $ans
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR LISTANDO ANS', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener los ANS'
            ], 500);
        }
    }

    /**
     * Crear un nuevo ANS
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'accion' => 'required|string|max:50|unique:ans_pedidos,accion',
                'tiempo_minutos' => 'required|integer|min:1',
                'tiempo_alerta' => 'nullable|integer|min:0',
                'descripcion' => 'nullable|string|max:255',
            ]);

            $ans = AnsPedido::create([
                'accion' => strtolower($request->accion),
                'tiempo_minutos' => $request->tiempo_minutos,
                'tiempo_alerta' => $request->tiempo_alerta,
                'descripcion' => $request->descripcion,
                'activo' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'ANS creado correctamente',
                'data' => $ans
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR CREANDO ANS', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el ANS'
            ], 500);
        }
    }

    /**
     * Mostrar un ANS específico
     */
    public function show($id)
    {
        try {
            $ans = AnsPedido::find($id);

            if (!$ans) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ANS no encontrado'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $ans
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR CONSULTANDO ANS', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar el ANS'
            ], 500);
        }
    }

    /**
     * Actualizar ANS
     */
    public function update(Request $request, $id)
    {
        try {
            $ans = AnsPedido::find($id);

            if (!$ans) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ANS no encontrado'
                ], 404);
            }

            $request->validate([
                'accion' => "required|string|max:50|unique:ans_pedidos,accion,{$id}",
                'tiempo_minutos' => 'required|integer|min:1',
                'tiempo_alerta' => 'nullable|integer|min:0',
                'descripcion' => 'nullable|string|max:255',
                'activo' => 'boolean',
            ]);

            $ans->update([
                'accion' => strtolower($request->accion),
                'tiempo_minutos' => $request->tiempo_minutos,
                'tiempo_alerta' => $request->tiempo_alerta,
                'descripcion' => $request->descripcion,
                'activo' => $request->activo ?? $ans->activo,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'ANS actualizado correctamente',
                'data' => $ans
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR ACTUALIZANDO ANS', [
                'error' => $e->getMessage(),
                'id' => $id,
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el ANS'
            ], 500);
        }
    }

    /**
     * Eliminar ANS
     */
    public function destroy($id)
    {
        try {
            $ans = AnsPedido::find($id);

            if (!$ans) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ANS no encontrado'
                ], 404);
            }

            $ans->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'ANS eliminado correctamente'
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR ELIMINANDO ANS', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el ANS'
            ], 500);
        }
    }

    /**
     * Activar / Desactivar ANS
     */
    public function toggle($id)
    {
        try {
            $ans = AnsPedido::find($id);

            if (!$ans) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ANS no encontrado'
                ], 404);
            }

            $ans->activo = !$ans->activo;
            $ans->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Estado actualizado correctamente',
                'data' => $ans
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ERROR CAMBIANDO ESTADO ANS', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al cambiar el estado'
            ], 500);
        }
    }
}