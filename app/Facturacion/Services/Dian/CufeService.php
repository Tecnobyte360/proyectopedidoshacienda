<?php

namespace App\Facturacion\Services\Dian;

/**
 * Cálculo del CUFE (Código Único de Factura Electrónica) — algoritmo v2 de la DIAN.
 *
 * El CUFE es un SHA-384 sobre la concatenación EXACTA de campos, en este orden:
 *   NumFac + FecFac + HorFac + ValFac + CodImp1 + ValImp1 + CodImp2 + ValImp2
 *   + CodImp3 + ValImp3 + ValTot + NitOFE + NumAdq + ClTec + TipoAmbiente
 *
 * Reglas de formato (críticas — si algo cambia, la DIAN rechaza la factura):
 *   - Montos: 2 decimales, punto como separador, SIN separador de miles (number_format(x,2,'.','')).
 *   - Fecha:  yyyy-mm-dd   ·   Hora: HH:mm:ss-05:00 (con zona horaria de Colombia).
 *   - NitOFE: NIT del emisor SIN dígito de verificación.
 *   - TipoAmbiente: '1' = producción, '2' = habilitación/pruebas.
 *   - Impuestos fijos por posición: 01=IVA, 04=INC, 03=ICA. Si un impuesto no aplica, va 0.00.
 *   - ClTec (clave técnica): la entrega la DIAN con la resolución de numeración.
 *
 * El CUNC (notas crédito/débito) usa la misma mecánica pero SIN ClTec; se hará aparte.
 */
class CufeService
{
    /** Ambientes DIAN. */
    public const AMB_PRODUCCION   = '1';
    public const AMB_HABILITACION = '2';

    /**
     * @param array $d claves esperadas:
     *   num_factura, fecha (Y-m-d), hora (H:i:sP), val_factura, iva, inc, ica,
     *   val_total, nit_emisor, doc_adquiriente, clave_tecnica, ambiente
     * @return string CUFE en hexadecimal minúscula (96 chars)
     */
    public function generar(array $d): string
    {
        $cadena = $this->cadena($d);
        return hash('sha384', $cadena);
    }

    /** Devuelve la cadena pre-hash (útil para depurar contra un ejemplo de la DIAN). */
    public function cadena(array $d): string
    {
        return $d['num_factura']
            . $d['fecha']
            . $d['hora']
            . $this->monto($d['val_factura'])
            . '01' . $this->monto($d['iva'] ?? 0)
            . '04' . $this->monto($d['inc'] ?? 0)
            . '03' . $this->monto($d['ica'] ?? 0)
            . $this->monto($d['val_total'])
            . $d['nit_emisor']
            . $d['doc_adquiriente']
            . $d['clave_tecnica']
            . $d['ambiente'];
    }

    /** 2 decimales, punto decimal, sin separador de miles. */
    private function monto($valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
