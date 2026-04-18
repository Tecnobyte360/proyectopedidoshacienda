<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpia info_empresa: quita la lista detallada de productos.
 * La IA estaba usando esa lista como catálogo y ofrecía productos que no existen
 * en la BD de productos. Solo dejamos info corporativa (sedes, contacto, valores).
 *
 * Sobrescribe SOLO si la info actual contiene la lista vieja de productos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $textoCorporativo = "Alimentos La Hacienda — Carnicería en Bello, Antioquia\n\n"
            . "Producción y comercialización de cortes cárnicos de res, cerdo, pollo y pescado,\n"
            . "manteniendo los más altos estándares de calidad e inocuidad.\n\n"
            . "📍 Sede principal: Calle 50 #47-80, Prado, Bello (Antioquia)\n"
            . "🏪 4 sedes en el municipio de Bello (una con planta propia de desposte y embutidos)\n"
            . "📞 Teléfono fijo: (604) 322 7020\n"
            . "🚚 WhatsApp domicilios: +57 314 615 3567\n"
            . "🛵 Cobertura: Área Metropolitana del Valle de Aburrá\n\n"
            . "CERTIFICACIONES:\n"
            . "✅ Autorización Sanitaria vigente\n"
            . "✅ Certificado BPG (Buenas Prácticas Ganaderas)\n\n"
            . "MÉTODOS DE PAGO:\n"
            . "• Efectivo contra entrega\n"
            . "• Tarjetas crédito y débito\n"
            . "• Transferencias y pagos electrónicos\n\n"
            . "DIFERENCIADORES:\n"
            . "- Procesamiento propio (de la finca a tu mesa)\n"
            . "- Embutidos artesanales sin conservantes innecesarios\n"
            . "- Cortes a la medida\n"
            . "- Frescura diaria garantizada\n"
            . "- Atención personalizada por WhatsApp\n\n"
            . "REDES SOCIALES: Facebook · Instagram · TikTok · YouTube — @LaHaciendaAlimentos\n\n"
            . "⚠️ NOTA AL ASESOR (IA): este bloque es información CORPORATIVA. Los productos que\n"
            . "puedes vender vienen ÚNICAMENTE del bloque CATÁLOGO. NO ofrezcas productos\n"
            . "que no estén en el catálogo, aunque aquí se mencionen categorías generales.";

        // Solo sobrescribir si la info contiene la palabra "morrillo" o "muchacho"
        // (que aparecían en la versión vieja con la lista detallada de cortes)
        DB::table('configuraciones_bot')
            ->where('id', 1)
            ->where(function ($q) {
                $q->where('info_empresa', 'like', '%morrillo%')
                  ->orWhere('info_empresa', 'like', '%muchacho%')
                  ->orWhere('info_empresa', 'like', '%lagarto%');
            })
            ->update(['info_empresa' => $textoCorporativo]);
    }

    public function down(): void
    {
        // No-op
    }
};
