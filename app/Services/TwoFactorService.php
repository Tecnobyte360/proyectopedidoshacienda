<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * 🔐 TOTP (Time-based One-Time Password) - RFC 6238.
 *
 * Compatible con Google Authenticator, Authy, Microsoft Authenticator,
 * 1Password, etc. (cualquier app que escanee QR otpauth://).
 *
 * Implementación propia (sin paquetes externos) usando HMAC-SHA1.
 */
class TwoFactorService
{
    private const PERIOD = 30;       // ventana de tiempo (segundos)
    private const DIGITS = 6;        // largo del código
    private const WINDOW = 1;        // permitir 1 ventana antes/después (clock skew)

    /** Genera un secreto Base32 de 16 caracteres (80 bits). */
    public function generarSecreto(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /** URL otpauth:// que escanea la app autenticadora. */
    public function urlOtpauth(string $secret, string $accountName, string $issuer): string
    {
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** URL pública del QR (usa api.qrserver.com, sin librería) */
    public function urlQrCode(string $otpauth, int $size = 200): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?'
            . http_build_query([
                'size' => "{$size}x{$size}",
                'data' => $otpauth,
                'margin' => 8,
                'ecc'  => 'M',
            ]);
    }

    /** Verifica si el código es válido para el secreto en este momento. */
    public function verificarCodigo(string $secret, string $code, int $window = self::WINDOW): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) return false;

        $timeSlice = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->generarCodigo($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /** Genera el código TOTP de 6 dígitos para un timeSlice dado. */
    public function generarCodigo(string $secret, int $timeSlice): string
    {
        $key = $this->decodeBase32($secret);
        // Empaquetar timeSlice como 8 bytes big-endian
        $time = str_pad(pack('N*', 0) . pack('N*', $timeSlice), 8, "\x00", STR_PAD_LEFT);
        if (function_exists('pack')) {
            // PHP >= 5.6: pack J* = big-endian 64-bit
            $time = pack('J', $timeSlice);
        }
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
             | ((ord($hash[$offset + 1]) & 0xFF) << 16)
             | ((ord($hash[$offset + 2]) & 0xFF) << 8)
             |  (ord($hash[$offset + 3]) & 0xFF);
        $code = $value % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Genera N códigos de respaldo (formato XXXX-YYYY). */
    public function generarCodigosRespaldo(int $cantidad = 8): array
    {
        $out = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $a = strtoupper(Str::random(4));
            $b = strtoupper(Str::random(4));
            $out[] = "{$a}-{$b}";
        }
        return $out;
    }

    /** Decodifica Base32 (RFC 4648) sin paquetes externos. */
    private function decodeBase32(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper($b32);
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);
        if ($b32 === '') return '';

        $binary = '';
        foreach (str_split($b32) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        return $bytes;
    }

    /** Habilita 2FA en el usuario: setea secret + recovery_codes + enabled_at. */
    public function habilitar(User $user, string $secret): array
    {
        $codes = $this->generarCodigosRespaldo();
        $user->update([
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => json_encode($codes),
            'two_factor_enabled_at'     => now(),
        ]);
        return $codes;
    }

    public function deshabilitar(User $user): void
    {
        $user->update([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at'     => null,
        ]);
    }

    /** Consume un código de respaldo (lo elimina). Retorna true si era válido. */
    public function consumirCodigoRespaldo(User $user, string $codigo): bool
    {
        $codigo = strtoupper(trim($codigo));
        $codes = json_decode($user->two_factor_recovery_codes ?? '[]', true) ?: [];
        $idx = array_search($codigo, $codes, true);
        if ($idx === false) return false;

        unset($codes[$idx]);
        $user->update(['two_factor_recovery_codes' => json_encode(array_values($codes))]);
        return true;
    }
}
