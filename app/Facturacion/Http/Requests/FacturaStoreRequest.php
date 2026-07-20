<?php

namespace App\Facturacion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 📄 CONTRATO PÚBLICO de una factura de venta.
 *
 * Este es el JSON que cualquier software externo (tu ERP, ERP de clientes,
 * otras apps) envía a `POST /api/facturacion/v1/facturas`. Es el artefacto más
 * importante de la integración: se versiona (/v1/) y no debe cambiar de forma
 * incompatible una vez publicado.
 *
 * El emisor NO viaja en el body: se resuelve del API key (middleware). El
 * software externo NO manda el número ni el CUFE: los asigna el servidor.
 */
class FacturaStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; } // ya autenticado por el middleware de API key

    public function rules(): array
    {
        return [
            'tipo_documento'          => ['sometimes', 'in:factura'],
            'fecha_emision'           => ['sometimes', 'date'],
            'moneda'                  => ['sometimes', 'string', 'size:3'],
            'observaciones'           => ['sometimes', 'nullable', 'string', 'max:2000'],

            // Referencia del documento en el software de origen (para conciliar).
            'origen_ref'              => ['sometimes', 'nullable', 'string', 'max:100'],

            // ── Cliente (adquirente) ───────────────────────────────
            'cliente'                        => ['required', 'array'],
            'cliente.tipo_documento'         => ['required', 'string', 'max:5'],   // 13=CC, 31=NIT...
            'cliente.numero_documento'       => ['required', 'string', 'max:30'],
            'cliente.dv'                     => ['sometimes', 'nullable', 'string', 'max:2'],
            'cliente.nombre'                 => ['required', 'string', 'max:200'],
            'cliente.email'                  => ['sometimes', 'nullable', 'email', 'max:150'],
            'cliente.telefono'               => ['sometimes', 'nullable', 'string', 'max:40'],
            'cliente.direccion'              => ['sometimes', 'nullable', 'string', 'max:250'],
            'cliente.municipio_codigo'       => ['sometimes', 'nullable', 'string', 'max:10'],
            'cliente.responsabilidades'      => ['sometimes', 'array'],

            // ── Ítems ──────────────────────────────────────────────
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.codigo'                 => ['required', 'string', 'max:60'],
            'items.*.descripcion'            => ['required', 'string', 'max:250'],
            'items.*.cantidad'               => ['required', 'numeric', 'gt:0'],
            'items.*.unidad'                 => ['sometimes', 'nullable', 'string', 'max:10'], // ej. 94=unidad
            'items.*.precio_unitario'        => ['required', 'numeric', 'gte:0'],
            'items.*.descuento'              => ['sometimes', 'numeric', 'gte:0'],
            'items.*.porcentaje_impuesto'    => ['sometimes', 'numeric', 'gte:0'], // ej. 19, 5, 0
            'items.*.tipo_impuesto'          => ['sometimes', 'string', 'max:5'],  // 01=IVA...

            // ── Pago (opcional) ────────────────────────────────────
            'pago'                           => ['sometimes', 'array'],
            'pago.forma'                     => ['sometimes', 'in:contado,credito'],
            'pago.medio_codigo'              => ['sometimes', 'nullable', 'string', 'max:5'], // catálogo DIAN
            'pago.vencimiento'               => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'  => 'La factura debe tener al menos un ítem.',
            'cliente.required'=> 'Falta el bloque de cliente (adquirente).',
        ];
    }
}
