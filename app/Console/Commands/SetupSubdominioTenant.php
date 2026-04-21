<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\HostingerDnsService;
use App\Services\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Configura el subdominio de un tenant en el servidor:
 *   1. Genera archivo Nginx en /etc/nginx/sites-enabled/{slug}.{base}.conf
 *   2. Recarga Nginx
 *   3. Genera certificado SSL con certbot --nginx
 *
 * Uso:
 *   php artisan tenants:setup-subdominio la-hacienda
 *   php artisan tenants:setup-subdominio --all     (todos los activos sin configurar)
 *   php artisan tenants:setup-subdominio la-hacienda --no-ssl   (solo nginx, sin cert)
 */
class SetupSubdominioTenant extends Command
{
    protected $signature = 'tenants:setup-subdominio
                            {slug? : Slug del tenant (omitir si --all)}
                            {--all : Procesar todos los tenants activos}
                            {--no-dns : No tocar DNS en Hostinger (asume que ya existe)}
                            {--no-ssl : No generar certificado SSL (solo Nginx)}
                            {--email= : Email para certbot (si no, usa env)}
                            {--wait=60 : Segundos a esperar propagación DNS}';

    protected $description = 'Genera la config de Nginx + certificado SSL para el subdominio de un tenant.';

    /** Ruta del template base — usa la config de pedidosonline como referencia */
    private string $templateConfPath = '/etc/nginx/sites-enabled/pedidosonline.tecnobyte360.com.conf';

    public function handle(): int
    {
        $base = config('app.tenant_base_domain', 'tecnobyte360.com');
        $email = $this->option('email') ?: env('CERTBOT_EMAIL', 'admin@' . $base);

        $tenants = collect();

        if ($this->option('all')) {
            $tenants = app(TenantManager::class)->withoutTenant(
                fn () => Tenant::where('activo', true)->get()
            );
        } elseif ($this->argument('slug')) {
            $t = app(TenantManager::class)->withoutTenant(
                fn () => Tenant::where('slug', $this->argument('slug'))->first()
            );
            if (!$t) {
                $this->error("No existe tenant con slug '{$this->argument('slug')}'.");
                return Command::FAILURE;
            }
            $tenants->push($t);
        } else {
            $this->error('Indica un slug o usa --all');
            return Command::FAILURE;
        }

        $exitos = 0;
        $errores = 0;

        foreach ($tenants as $tenant) {
            $this->info("");
            $this->info("━━━ Procesando: {$tenant->nombre} ({$tenant->slug}) ━━━");

            $dominio = "{$tenant->slug}.{$base}";
            $confPath = "/etc/nginx/sites-enabled/{$dominio}.conf";

            // 0. DNS en Hostinger (si no se desactivó)
            if (!$this->option('no-dns')) {
                try {
                    $dns = app(HostingerDnsService::class);
                    if ($dns->existeSubdominio($tenant->slug)) {
                        $this->line("  ✓ DNS ya existe en Hostinger: {$dominio}");
                    } else {
                        $this->line("  🌐 Creando registro DNS en Hostinger: {$dominio}...");
                        $info = $dns->crearSubdominio($tenant->slug);
                        $this->line("  ✓ DNS creado: {$info['fqdn']} -> {$info['ip']} (TTL {$info['ttl']}s)");
                    }

                    $espera = (int) $this->option('wait');
                    if ($espera > 0) {
                        $this->line("  ⏳ Esperando propagación DNS (hasta {$espera}s)...");
                        if ($dns->esperarPropagacion($tenant->slug, $espera)) {
                            $this->line('  ✓ DNS propagado.');
                        } else {
                            $this->warn('  ⚠️ DNS aún no resuelve localmente. Continuando — certbot podría fallar.');
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("  ❌ Error con Hostinger DNS: " . $e->getMessage());
                    $this->line('     Puedes reintentar con --no-dns si ya creaste el registro manualmente.');
                    $errores++;
                    continue;
                }
            } else {
                $this->warn('  ⏭️  --no-dns activado, asumiendo DNS ya configurado.');
            }

            // 1. Verificar que NO existe ya
            if (File::exists($confPath)) {
                $this->warn("  ⚠️  Ya existe {$confPath} — saltando generación de Nginx.");
            } else {
                // 2. Verificar template
                if (!File::exists($this->templateConfPath)) {
                    $this->error("  ❌ Template no encontrado: {$this->templateConfPath}");
                    $errores++;
                    continue;
                }

                // 3. Copiar template y reemplazar dominio
                $contenido = File::get($this->templateConfPath);
                $contenido = $this->limpiarTemplate($contenido, $dominio, $tenant->slug);

                try {
                    File::put($confPath, $contenido);
                    $this->line("  ✓ Nginx config creado: {$confPath}");
                } catch (\Throwable $e) {
                    $this->error("  ❌ No pude escribir {$confPath}: " . $e->getMessage());
                    $this->line("     (El usuario que corre PHP necesita permisos de escritura)");
                    $errores++;
                    continue;
                }

                // 4. Validar y recargar Nginx
                $resultado = $this->ejecutar('sudo nginx -t 2>&1');
                if ($resultado['exit'] !== 0) {
                    $this->error("  ❌ nginx -t falló:");
                    $this->line($resultado['output']);
                    File::delete($confPath);   // rollback
                    $errores++;
                    continue;
                }

                $this->ejecutar('sudo systemctl reload nginx');
                $this->line('  ✓ Nginx recargado.');
            }

            // 5. SSL con certbot
            if ($this->option('no-ssl')) {
                $this->warn('  ⏭️  --no-ssl activado, saltando SSL.');
            } else {
                $this->line("  🔒 Generando certificado SSL para {$dominio}...");
                $cmd = sprintf(
                    'sudo certbot --nginx -d %s --non-interactive --agree-tos --email %s --redirect 2>&1',
                    escapeshellarg($dominio),
                    escapeshellarg($email)
                );
                $resultado = $this->ejecutar($cmd);

                if ($resultado['exit'] === 0) {
                    $this->info("  ✓ SSL generado y aplicado.");
                } else {
                    $this->warn('  ⚠️ Certbot falló:');
                    $this->line($resultado['output']);
                    $this->line('     Genera el cert manualmente:');
                    $this->line("     sudo certbot --nginx -d {$dominio}");
                }
            }

            $this->info("  🎉 {$dominio} listo: https://{$dominio}");
            $exitos++;
        }

        $this->info('');
        $this->info("✅ Éxitos: {$exitos}");
        if ($errores > 0) {
            $this->error("❌ Errores: {$errores}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Reemplaza el dominio "pedidosonline.tecnobyte360.com" del template
     * por el dominio del nuevo tenant. Quita SSL del template porque certbot
     * lo agrega solo al final.
     */
    private function limpiarTemplate(string $contenido, string $dominioNuevo, string $slug): string
    {
        $base = config('app.tenant_base_domain', 'tecnobyte360.com');
        $dominioBase = "pedidosonline.{$base}";

        // Reemplazar el dominio base por el nuevo
        $contenido = str_replace($dominioBase, $dominioNuevo, $contenido);
        $contenido = str_replace('pedidosonline-tecnobyte360', "{$slug}-{$base}", $contenido);
        $contenido = str_replace('tecnobyte360-pedidosonline', "{$base}-{$slug}", $contenido);

        // Quitar SSL del template (certbot lo agregará luego)
        // Preserva los bloques server pero elimina las directivas SSL
        $contenido = preg_replace('/\s*ssl_certificate(_key)?\s+[^;]+;/', '', $contenido);
        $contenido = preg_replace('/listen\s+443\s+ssl\s+http2;/', '# listen 443 ssl http2; (lo agrega certbot)', $contenido);
        $contenido = preg_replace('/listen\s+\[::\]:443\s+ssl\s+http2;/', '# listen [::]:443 ssl http2; (lo agrega certbot)', $contenido);

        // Si el template no tiene listen 80, agrégalo al inicio del bloque server
        if (!preg_match('/listen\s+80;/', $contenido)) {
            $contenido = preg_replace(
                '/(server\s*\{)/',
                "$1\n    listen 80;\n    listen [::]:80;",
                $contenido,
                1
            );
        }

        return $contenido;
    }

    private function ejecutar(string $cmd): array
    {
        $output = [];
        $exit = 0;
        exec($cmd . ' 2>&1', $output, $exit);
        return [
            'exit'   => $exit,
            'output' => implode("\n", $output),
        ];
    }
}
