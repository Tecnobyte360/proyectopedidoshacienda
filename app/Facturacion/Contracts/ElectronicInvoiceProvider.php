<?php

namespace App\Facturacion\Contracts;

use App\Facturacion\Models\FeDocumento;

/**
 * Contrato del "motor" que firma y transmite a la DIAN.
 *
 * Permite tener DOS implementaciones intercambiables sin tocar el resto del
 * sistema:
 *   - ProveedorTecnologicoProvider  → delega firma/transmisión a un PTA (API externa).
 *   - DianPropioProvider            → firma XAdES + CUFE + SOAP DIAN propios.
 *
 * El controlador/servicio de aplicación NO sabe cuál está detrás: siempre habla
 * contra esta interfaz. Empezar con un PTA y migrar a motor propio = cambiar el
 * binding en el service provider, nada más.
 */
interface ElectronicInvoiceProvider
{
    /**
     * Emite el documento (genera XML, firma, calcula CUFE, transmite a la DIAN)
     * y actualiza el FeDocumento con el resultado.
     *
     * @return array{estado:string, cufe:?string, mensaje:?string, errores:array}
     */
    public function emitir(FeDocumento $documento): array;

    /**
     * Consulta el estado de un documento ya transmitido (para los que quedaron
     * en validación asíncrona).
     *
     * @return array{estado:string, mensaje:?string, errores:array}
     */
    public function consultarEstado(FeDocumento $documento): array;

    /** Emite una nota crédito que referencia a un documento existente. */
    public function emitirNotaCredito(FeDocumento $notaCredito): array;

    /** Emite una nota débito que referencia a un documento existente. */
    public function emitirNotaDebito(FeDocumento $notaDebito): array;

    /** Identificador del ambiente/motor (para logs y validaciones). */
    public function nombre(): string;
}
