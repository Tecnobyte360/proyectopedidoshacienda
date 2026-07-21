<?php

namespace App\Facturacion\Services\Dian;

/**
 * SoftwareSecurityCode y contenido del código QR de la factura (DIAN).
 *
 * - SoftwareSecurityCode = SHA-384( idSoftware + PIN + NumeroFactura )
 *   Va dentro de UBLExtensions/DianExtensions. Prueba que la factura salió
 *   del software registrado (por eso el PIN nunca se expone).
 *
 * - QR: en el ambiente de PRODUCCIÓN el contenido del QR es la URL de consulta
 *   del documento en la DIAN. En habilitación se usa la URL de habilitación.
 */
class SoftwareSecurityCode
{
    public const URL_QR_PRODUCCION   = 'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=';
    public const URL_QR_HABILITACION = 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=';

    /** SHA-384(idSoftware + pin + numeroFactura). */
    public function codigo(string $idSoftware, string $pin, string $numeroFactura): string
    {
        return hash('sha384', $idSoftware . $pin . $numeroFactura);
    }

    /** Contenido del QR: URL de consulta del documento por CUFE. */
    public function qr(string $cufe, bool $produccion): string
    {
        $base = $produccion ? self::URL_QR_PRODUCCION : self::URL_QR_HABILITACION;
        return $base . $cufe;
    }
}
