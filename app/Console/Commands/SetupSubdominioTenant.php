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

    /** Template del repo (siempre accesible desde el contenedor) */
    private function templatePath(): string
    {
        return resource_path('nginx/tenant.conf.stub');
    }

    /** Carpeta compartida (volumen Docker) donde se dejan los .conf pendientes */
    private function pendingDir(): string
    {
        $dir = storage_path('app/nginx-tenants');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir;
    }

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
            $confPath = $this->pendingDir() . "/{$dominio}.conf";

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

            // 1. Verificar template
            $tpl = $this->templatePath();
            if (!File::exists($tpl)) {
                $this->error("  ❌ Template no encontrado: {$tpl}");
                $errores++;
                continue;
            }

            // 2. Generar el .conf en el directorio compartido
            $contenido = File::get($tpl);
            $contenido = str_replace(
                ['{{DOMINIO}}', '{{SLUG}}', '{{EMAIL}}'],
                [$dominio, $tenant->slug, $email],
                $contenido
            );

            try {
                File::put($confPath, $contenido);
                $this->line("  ✓ Conf preparado en volumen compartido:");
                $this->line("     {$confPath}");
            } catch (\Throwable $e) {
                $this->error("  ❌ No pude escribir {$confPath}: " . $e->getMessage());
                $errores++;
                continue;
            }

            // 3. Marcador para el watcher del host (incluye email para certbot)
            $flagPath = $confPath . '.pending';
            File::put($flagPath, json_encode([
                'dominio'  => $dominio,
                'slug'     => $tenant->slug,
                'email'    => $email,
                'no_ssl'   => (bool) $this->option('no-ssl'),
                'creado_en'=> now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            $this->info("  📤 Encolado para el host:");
            $this->line("     El script `bin/aplicar-tenant-subdomain.sh` (cron en host) lo aplicará a Nginx + certbot.");
            $this->info("  🎉 {$dominio} estará listo en ≤1 min: https://{$dominio}");
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
