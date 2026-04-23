<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Cast "encriptado pero tolerante":
 *   - Al ESCRIBIR: siempre cifra con Laravel\Crypt (AES-256 usando APP_KEY).
 *   - Al LEER: intenta descifrar; si falla (porque es plaintext legacy o
 *     datos corruptos), devuelve el valor raw en lugar de lanzar excepción.
 *
 * Útil cuando añades cifrado a una columna que ya tenía datos sin cifrar.
 * En el siguiente guardado queda cifrado y todo se normaliza.
 */
class EncryptedTolerante implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Valor legacy en plaintext — lo devolvemos tal cual
            return (string) $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return Crypt::encryptString((string) $value);
    }
}
