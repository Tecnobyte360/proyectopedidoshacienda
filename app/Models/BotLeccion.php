<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Lecciones aprendidas del bot. Cuando un operador detecta un error
 * del bot, lo reporta como lección. Las lecciones activas se inyectan
 * al prompt del LLM como "reglas que NO debes romper".
 */
class BotLeccion extends Model
{
    use BelongsToTenant;

    protected $table = 'bot_lecciones';

    public const CATEGORIAS = [
        'general'   => '🎯 General',
        'cantidad'  => '🔢 Cantidad / Unidad',
        'producto'  => '🥩 Producto / Catálogo',
        'precio'    => '💰 Precio',
        'direccion' => '📍 Dirección / Pickup',
        'cobertura' => '🗺️ Cobertura / Zona',
        'cliente'   => '👤 Datos del cliente',
        'flujo'     => '🔁 Flujo del pedido',
        'tono'      => '💬 Tono / Lenguaje',
    ];

    protected $fillable = [
        'tenant_id',
        'categoria',
        'titulo',
        'contexto_error',
        'regla',
        'frase_disparadora',
        'conversacion_id',
        'mensaje_id',
        'reportado_por_user_id',
        'activa',
        'veces_aplicada',
    ];

    protected $casts = [
        'activa'          => 'boolean',
        'veces_aplicada'  => 'integer',
    ];

    public function conversacion()
    {
        return $this->belongsTo(ConversacionWhatsapp::class, 'conversacion_id');
    }

    public function mensaje()
    {
        return $this->belongsTo(MensajeWhatsapp::class, 'mensaje_id');
    }

    public function reportadoPor()
    {
        return $this->belongsTo(User::class, 'reportado_por_user_id');
    }

    /**
     * Renderiza las lecciones activas como bloque de texto para inyectar
     * al system prompt del LLM.
     */
    public static function bloquePrompt(int $tenantId, int $limite = 25): string
    {
        $lecciones = static::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->orderByDesc('veces_aplicada')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        if ($lecciones->isEmpty()) return '';

        $lineas = [];
        $lineas[] = "═══════════════════════════════════════════════════════════════════════════════";
        $lineas[] = "# 📚 LECCIONES APRENDIDAS — ERRORES PASADOS QUE NO DEBES REPETIR";
        $lineas[] = "";
        $lineas[] = "Estas son cosas que el equipo te ha corregido. SON OBLIGATORIAS de cumplir:";
        $lineas[] = "";

        $porCat = $lecciones->groupBy('categoria');
        foreach ($porCat as $cat => $items) {
            $label = self::CATEGORIAS[$cat] ?? ucfirst($cat);
            $lineas[] = "## {$label}";
            foreach ($items as $l) {
                $lineas[] = "- **{$l->titulo}**";
                if ($l->frase_disparadora) {
                    $lineas[] = "  Cuando el cliente diga: \"{$l->frase_disparadora}\"";
                }
                if ($l->contexto_error) {
                    $lineas[] = "  ❌ NO hagas: {$l->contexto_error}";
                }
                if ($l->regla) {
                    $lineas[] = "  ✅ SÍ haz: {$l->regla}";
                }
                $lineas[] = "";
            }
        }

        $lineas[] = "Estas lecciones son CRÍTICAS. Internalízalas en CADA respuesta.";
        $lineas[] = "═══════════════════════════════════════════════════════════════════════════════";

        return implode("\n", $lineas);
    }

    /**
     * Marca esta lección como aplicada (contador) — útil para métricas
     * y priorización (las más aplicadas suben de orden en el prompt).
     */
    public function aplicar(): void
    {
        $this->increment('veces_aplicada');
    }
}
