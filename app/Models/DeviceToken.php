<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'domiciliario_id', 'token', 'plataforma'];
}
