<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carga la información oficial de Alimentos La Hacienda en la configuración del bot.
 * Solo sobrescribe si la info actual está vacía o es la genérica de fábrica.
 * Si el usuario ya editó manualmente, NO toca su contenido.
 */
return new class extends Migration
{
    public function up(): void
    {
        $textoOficial = "Alimentos La Hacienda — Carnicería en Bello, Antioquia\n\n"
            . "Producción y comercialización de cortes cárnicos de res, cerdo, pollo y pescado,\n"
            . "manteniendo los más altos estándares de calidad e inocuidad en la alimentación.\n\n"
            . "📍 Sede principal: Calle 50 #47-80, Prado, Bello (Antioquia)\n"
            . "🏪 4 sedes ubicadas en el municipio de Bello (una con planta de desposte y producción propia de embutidos)\n"
            . "📞 Teléfono fijo: (604) 322 7020\n"
            . "🚚 WhatsApp domicilios: +57 314 615 3567\n"
            . "🛵 Domicilios en toda el Área Metropolitana del Valle de Aburrá\n\n"
            . "CATEGORÍAS DE PRODUCTOS:\n"
            . "• Carne de res: costilla, lagarto, solomo, morrillo, muchacho, pulpa, posta, pecho\n"
            . "• Carne de cerdo: cortes selectos\n"
            . "• Pollo: presas, deshuesado, entero\n"
            . "• Pescado fresco\n"
            . "• Embutidos artesanales (marca propia)\n"
            . "• Cortes especiales y servicio de parrilla\n\n"
            . "CERTIFICACIONES:\n"
            . "✅ Autorización Sanitaria vigente\n"
            . "✅ Certificado BPG (Buenas Prácticas Ganaderas)\n\n"
            . "MÉTODOS DE PAGO:\n"
            . "• Efectivo contra entrega\n"
            . "• Tarjetas crédito y débito\n"
            . "• Transferencias y pagos electrónicos\n\n"
            . "DIFERENCIADORES:\n"
            . "- Procesamiento propio (de la finca a tu mesa)\n"
            . "- Embutidos hechos por nosotros, sin conservantes innecesarios\n"
            . "- Cortes a la medida que pidas\n"
            . "- Frescura garantizada todos los días\n"
            . "- Atención personalizada por WhatsApp\n\n"
            . "REDES SOCIALES: Facebook · Instagram · TikTok · YouTube — @LaHaciendaAlimentos";

        $textoGenericoViejo = "Alimentos La Hacienda\n"
            . "- Más de 25 años de experiencia.\n"
            . "- Ubicada en Bello, Antioquia.\n"
            . "- Calidad, frescura y servicio al cliente.\n"
            . "- Opera con domicilios, sedes físicas y atención directa.\n"
            . "- Sistema de pedidos integrado.";

        DB::table('configuraciones_bot')
            ->where('id', 1)
            ->where(function ($q) use ($textoGenericoViejo) {
                $q->whereNull('info_empresa')
                  ->orWhere('info_empresa', '')
                  ->orWhere('info_empresa', $textoGenericoViejo);
            })
            ->update(['info_empresa' => $textoOficial]);
    }

    public function down(): void
    {
        // No-op — no revertimos el texto.
    }
};
