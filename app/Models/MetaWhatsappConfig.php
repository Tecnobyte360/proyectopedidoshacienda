<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Credenciales Meta WhatsApp Cloud API por tenant.
 * Si activo=true para un tenant → todos sus envíos pasan por Meta.
 * El webhook entrante enruta al tenant correcto por phone_number_id.
 */
class MetaWhatsappConfig extends Model
{
    use BelongsToTenant;

    protected $table = 'meta_whatsapp_configs';

    protected $fillable = [
        'tenant_id',
        'phone_number_id',
        'waba_id',
        'access_token',
        'api_version',
        'verify_token',
        'app_secret',
        'activo',
        'default_lang',
        'display_name',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'app_secret',
        'verify_token',
    ];

    /**
     * Devuelve la config activa del tenant actual (o null si no hay).
     * Multi-tenant: BelongsToTenant filtra por tenant_id en scope global.
     */
    public static function activaActual(): ?self
    {
        return static::query()->where('activo', true)->first();
    }

    /**
     * Busca config por phone_number_id (sin scope de tenant — para webhook entrante).
     */
    public static function porPhoneNumberId(string $phoneNumberId): ?self
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->where('activo', true)
            ->first();
    }

    public function endpointMessages(): string
    {
        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->api_version ?: 'v20.0',
            $this->phone_number_id
        );
    }
}
