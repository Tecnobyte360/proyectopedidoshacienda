<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MetaWhatsappPlantilla extends Model
{
    use BelongsToTenant;

    protected $table = 'meta_whatsapp_plantillas';

    protected $fillable = [
        'tenant_id', 'nombre', 'idioma', 'categoria', 'estado',
        'descripcion', 'body_preview', 'footer', 'num_variables', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'num_variables' => 'int',
    ];

    public function disparadores()
    {
        return $this->hasMany(MetaWhatsappDisparador::class, 'plantilla_id');
    }

    /**
     * Cuenta los placeholders {{N}} del body. Útil cuando importas desde Meta.
     */
    public static function contarVariables(?string $body): int
    {
        if (!$body) return 0;
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);
        return count(array_unique($m[1] ?? []));
    }
}
