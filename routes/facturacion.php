<?php

use App\Facturacion\Http\Controllers\FacturaController;
use App\Facturacion\Http\Middleware\FacturadorApiKey;
use Illuminate\Support\Facades\Route;

/**
 * API pública de Facturación Electrónica (versionada).
 * La consume cualquier software externo autenticándose con la API key del
 * emisor (Authorization: Bearer <key>). El middleware resuelve el tenant emisor
 * y valida que su plan incluya el paquete de facturación electrónica.
 *
 * REGISTRO: cargar este archivo desde el bootstrap de rutas del proyecto.
 *   - Laravel 11/12 (bootstrap/app.php):
 *       ->withRouting(then: fn () => require base_path('routes/facturacion.php'))
 *   - Laravel 10 (RouteServiceProvider) o simple:
 *       require base_path('routes/facturacion.php');  // al final de routes/api.php
 */
Route::prefix('api/facturacion/v1')
    ->middleware([FacturadorApiKey::class])
    ->group(function () {
        Route::post('/facturas',       [FacturaController::class, 'store']); // emitir factura
        Route::get('/facturas/{id}',   [FacturaController::class, 'show']);  // consultar estado/CUFE

        // ── Siguiente fase ────────────────────────────────────────
        // Route::post('/notas-credito', [NotaController::class, 'credito']);
        // Route::post('/notas-debito',  [NotaController::class, 'debito']);
        // Route::get('/facturas/{id}/pdf', [FacturaController::class, 'pdf']);
    });
