<div class="min-h-screen bg-slate-50 pb-12">
    <div class="max-w-5xl mx-auto p-6 space-y-6">

        {{-- HERO --}}
        <div class="rounded-3xl bg-gradient-to-br from-violet-600 via-fuchsia-600 to-pink-600 text-white p-8 shadow-xl">
            <div class="flex items-start gap-4">
                <div class="h-16 w-16 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center">
                    <i class="fa-solid fa-book-open text-3xl"></i>
                </div>
                <div class="flex-1">
                    <h1 class="text-3xl font-extrabold">Documentación técnica</h1>
                    <p class="mt-1 text-white/90">
                        Cómo está construida la plataforma TecnoByte360 SaaS — referencia para super-admin y desarrolladores.
                    </p>
                </div>
            </div>
        </div>

        {{-- ÍNDICE --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-6">
            <h2 class="text-sm font-bold uppercase text-slate-500 mb-3 tracking-widest">Índice</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <a href="#arquitectura" class="text-violet-600 hover:underline"><i class="fa-solid fa-sitemap w-5"></i> 1. Arquitectura general</a>
                <a href="#multitenant" class="text-violet-600 hover:underline"><i class="fa-solid fa-building w-5"></i> 2. Sistema multi-tenant</a>
                <a href="#subdominios" class="text-violet-600 hover:underline"><i class="fa-solid fa-globe w-5"></i> 3. Automatización de subdominios</a>
                <a href="#billing" class="text-violet-600 hover:underline"><i class="fa-solid fa-money-bills w-5"></i> 4. Sistema de facturación</a>
                <a href="#whatsapp" class="text-violet-600 hover:underline"><i class="fa-brands fa-whatsapp w-5"></i> 5. WhatsApp por tenant</a>
                <a href="#superadmin" class="text-violet-600 hover:underline"><i class="fa-solid fa-shield-halved w-5"></i> 6. Super-admin & impersonación</a>
                <a href="#ops" class="text-violet-600 hover:underline"><i class="fa-solid fa-server w-5"></i> 7. Operaciones (VPS / Docker)</a>
                <a href="#troubleshooting" class="text-violet-600 hover:underline"><i class="fa-solid fa-stethoscope w-5"></i> 8. Troubleshooting</a>
            </div>
        </div>

        {{-- 1. ARQUITECTURA --}}
        <section id="arquitectura" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-sitemap text-violet-600"></i> 1. Arquitectura general
            </h2>
            <p class="text-sm text-slate-600">
                La plataforma es un <strong>SaaS multi-tenant single-database</strong>: una sola base de datos
                MySQL, una sola instancia de Laravel, y todos los datos llevan una columna <code class="bg-slate-100 px-1 rounded">tenant_id</code>
                que los aísla automáticamente vía global scope.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs font-bold uppercase text-slate-500 mb-2">Stack</div>
                    <ul class="text-sm space-y-1">
                        <li>• Laravel 12 + Livewire 3</li>
                        <li>• Tailwind v4</li>
                        <li>• MySQL 8</li>
                        <li>• Reverb (WebSockets)</li>
                        <li>• Spatie Permission v6</li>
                        <li>• Hostinger DNS API</li>
                        <li>• Nginx + Certbot (Let's Encrypt)</li>
                    </ul>
                </div>
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                    <div class="text-xs font-bold uppercase text-slate-500 mb-2">Componentes clave</div>
                    <ul class="text-sm space-y-1">
                        <li>• <code class="bg-white px-1 rounded">App\Models\Tenant</code></li>
                        <li>• <code class="bg-white px-1 rounded">App\Services\TenantManager</code></li>
                        <li>• <code class="bg-white px-1 rounded">App\Models\Scopes\TenantScope</code></li>
                        <li>• <code class="bg-white px-1 rounded">App\Models\Concerns\BelongsToTenant</code></li>
                        <li>• <code class="bg-white px-1 rounded">App\Http\Middleware\SetCurrentTenant</code></li>
                        <li>• <code class="bg-white px-1 rounded">App\Services\HostingerDnsService</code></li>
                        <li>• <code class="bg-white px-1 rounded">App\Services\WhatsappResolverService</code></li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 2. MULTI-TENANT --}}
        <section id="multitenant" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-building text-violet-600"></i> 2. Sistema multi-tenant
            </h2>

            <h3 class="font-bold text-slate-700 mt-4">Cómo se identifica el tenant en cada request</h3>
            <p class="text-sm text-slate-600">
                El middleware <code class="bg-slate-100 px-1 rounded">SetCurrentTenant</code> resuelve el tenant en este orden:
            </p>
            <ol class="text-sm text-slate-600 list-decimal pl-6 space-y-1">
                <li><strong>Subdominio</strong>: si el request viene a <code>la-hacienda.tecnobyte360.com</code>, busca <code>tenants.slug = 'la-hacienda'</code>.</li>
                <li><strong>Sesión de impersonación</strong>: si <code>session('tenant_imitado_id')</code> está seteado (super-admin "viendo como"), usa ese.</li>
                <li><strong>Usuario autenticado</strong>: usa <code>auth()->user()->tenant_id</code>.</li>
                <li><strong>Subdominios reservados</strong>: <code>www, api, admin, app, mail, pedidosonline</code> → no aplican tenant.</li>
                <li><strong>Subdominio desconocido</strong>: 404 estricto (no se filtra a un tenant equivocado).</li>
            </ol>

            <h3 class="font-bold text-slate-700 mt-4">Aislamiento de datos automático</h3>
            <p class="text-sm text-slate-600">
                Los modelos con <code class="bg-slate-100 px-1 rounded">use BelongsToTenant</code> aplican el global scope
                <code class="bg-slate-100 px-1 rounded">TenantScope</code> que filtra por <code>tenant_id</code> en TODA query
                (Eloquent, relaciones, eager-loading). Si un dev se olvida de filtrar, el scope lo hace por él.
            </p>
            <pre class="bg-slate-900 text-emerald-300 text-xs p-3 rounded-lg overflow-auto"><code>// app/Models/Pedido.php
class Pedido extends Model
{
    use BelongsToTenant;   // ← inyecta tenant_id en create() + filtra en read()
}</code></pre>

            <h3 class="font-bold text-slate-700 mt-4">Bypass del scope (super-admin)</h3>
            <p class="text-sm text-slate-600">
                Para queries que deben ver TODOS los tenants (panel super-admin, comandos cron):
            </p>
            <pre class="bg-slate-900 text-emerald-300 text-xs p-3 rounded-lg overflow-auto"><code>app(\App\Services\TenantManager::class)->withoutTenant(function () {
    return Tenant::where('activo', true)->get();
});</code></pre>

            <h3 class="font-bold text-slate-700 mt-4">Migración de datos legacy</h3>
            <p class="text-sm text-slate-600">
                El seeder <code class="bg-slate-100 px-1 rounded">MultiTenantSetupSeeder</code> crea el Tenant #1
                ("Alimentos La Hacienda") y reasigna todos los registros sin <code>tenant_id</code> a ese tenant.
                Crea también el super-admin: <code>super@tecnobyte360.com</code> / <code>superadmin123</code>.
            </p>
        </section>

        {{-- 3. SUBDOMINIOS --}}
        <section id="subdominios" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-globe text-violet-600"></i> 3. Automatización de subdominios (1 click)
            </h2>

            <p class="text-sm text-slate-600">
                Al crear un tenant y hacer click en <strong>"🚀 Configurar subdominio"</strong>, el sistema ejecuta 3 pasos
                automáticos en menos de 30 segundos:
            </p>

            <div class="space-y-3 mt-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="text-sm font-bold text-emerald-700 mb-1">PASO 1 — DNS en Hostinger</div>
                    <p class="text-xs text-emerald-900">
                        <code class="bg-white px-1 rounded">App\Services\HostingerDnsService</code> usa la API DNS de
                        Hostinger (<code>https://developers.hostinger.com/api/dns/v1</code>) para crear un registro A
                        <code>{slug}.tecnobyte360.com → IP del VPS (145.79.7.71)</code>. Luego espera la propagación DNS local.
                    </p>
                </div>

                <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                    <div class="text-sm font-bold text-sky-700 mb-1">PASO 2 — Generación de Nginx config</div>
                    <p class="text-xs text-sky-900">
                        El comando <code class="bg-white px-1 rounded">tenants:setup-subdominio</code> toma el template
                        <code>resources/nginx/tenant.conf.stub</code>, reemplaza <code>{{'{{DOMINIO}}'}}</code> y <code>{{'{{SLUG}}'}}</code>,
                        y deja el archivo en <code>storage/app/nginx-tenants/{dominio}.conf</code> + un marcador
                        <code>.pending</code> con metadata JSON (email, no_ssl).
                    </p>
                </div>

                <div class="rounded-xl border border-fuchsia-200 bg-fuchsia-50 p-4">
                    <div class="text-sm font-bold text-fuchsia-700 mb-1">PASO 3 — Watcher reactivo en el host</div>
                    <p class="text-xs text-fuchsia-900">
                        El servicio systemd <code class="bg-white px-1 rounded">tenant-subdomain-watcher.service</code>
                        usa <code>inotifywait</code> para detectar el <code>.pending</code> al instante. Llama a
                        <code>aplicar-tenant-subdomain.sh</code> que:
                    </p>
                    <ol class="text-xs text-fuchsia-900 list-decimal pl-5 mt-1 space-y-0.5">
                        <li>Copia el conf a <code>/etc/nginx/sites-enabled/</code></li>
                        <li>Valida con <code>nginx -t</code> (rollback si falla)</li>
                        <li><code>systemctl reload nginx</code></li>
                        <li><code>certbot --nginx -d {dominio}</code> → genera SSL</li>
                        <li>Renombra <code>.pending → .done</code> (o <code>.error</code>)</li>
                    </ol>
                </div>
            </div>

            <h3 class="font-bold text-slate-700 mt-4">Polling reactivo en el modal</h3>
            <p class="text-sm text-slate-600">
                Mientras el watcher procesa en el host, el modal de Livewire usa <code class="bg-slate-100 px-1 rounded">wire:poll.2s</code>
                para chequear cada 2 segundos si apareció el <code>.done</code> o <code>.error</code>, y actualiza el
                stepper visual sin recargar.
            </p>

            <h3 class="font-bold text-slate-700 mt-4">⚠️ Reglas de slug</h3>
            <p class="text-sm text-slate-600">
                Let's Encrypt rechaza guion bajo, espacios y mayúsculas en subdominios. El modelo Tenant fuerza
                <strong>kebab-case</strong> (solo a-z, 0-9, guion medio) vía <code class="bg-slate-100 px-1 rounded">Tenant::normalizarSlug()</code>
                en el evento <code>saving</code>.
            </p>

            <h3 class="font-bold text-slate-700 mt-4">Variables de entorno (.env)</h3>
            <pre class="bg-slate-900 text-emerald-300 text-xs p-3 rounded-lg overflow-auto"><code>HOSTINGER_API_KEY=XcVK...
HOSTINGER_DOMAIN=tecnobyte360.com
HOSTINGER_SERVER_IP=145.79.7.71
HOSTINGER_DNS_TTL=300
CERTBOT_EMAIL=comercial@tecnobyte360.com</code></pre>
        </section>

        {{-- 4. BILLING --}}
        <section id="billing" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-money-bills text-violet-600"></i> 4. Sistema de facturación
            </h2>

            <h3 class="font-bold text-slate-700 mt-2">Modelos</h3>
            <ul class="text-sm text-slate-600 space-y-1">
                <li><code class="bg-slate-100 px-1 rounded">Plan</code> — Catálogo de planes (basico/pro/empresa) con precio mensual y anual, límites (max_pedidos_mes, max_usuarios) y features (whatsapp/ia/reportes/multi_sede/api).</li>
                <li><code class="bg-slate-100 px-1 rounded">Suscripcion</code> — Relación tenant-plan con estado (activa/en_trial/suspendida/cancelada/expirada), ciclo (mensual/anual), fecha_inicio, fecha_fin.</li>
                <li><code class="bg-slate-100 px-1 rounded">Pago</code> — Registro manual de pagos con método (efectivo/transferencia/nequi/daviplata/tarjeta), referencia y comprobante_url.</li>
            </ul>

            <h3 class="font-bold text-slate-700 mt-4">Flujo típico</h3>
            <ol class="text-sm text-slate-600 list-decimal pl-6 space-y-1">
                <li>Super-admin crea tenant en <code>/admin/tenants</code></li>
                <li>Le asigna una suscripción en <code>/admin/suscripciones</code> (con <code>fecha_fin</code>)</li>
                <li>Cuando el cliente paga, super-admin registra el pago en <code>/admin/pagos</code> con checkbox "Renovar suscripción" → extiende <code>fecha_fin</code> +30 o +365 días</li>
                <li>Cron diario (3 AM) <code>tenants:suspender-vencidos</code> revisa <code>subscription_ends_at &lt; hoy</code> y desactiva el tenant</li>
            </ol>

            <h3 class="font-bold text-slate-700 mt-4">KPIs en el panel</h3>
            <ul class="text-sm text-slate-600 space-y-1">
                <li><strong>MRR</strong> (Monthly Recurring Revenue) — suma de planes activos en ciclo mensual + anuales/12</li>
                <li><strong>ARR</strong> (Annual Recurring Revenue) — MRR × 12</li>
                <li><strong>Vencidas</strong> — tenants con suscripción expirada sin renovar</li>
            </ul>
        </section>

        {{-- 5. WHATSAPP --}}
        <section id="whatsapp" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-brands fa-whatsapp text-emerald-600"></i> 5. WhatsApp por tenant
            </h2>
            <p class="text-sm text-slate-600">
                Cada tenant tiene sus <strong>propias credenciales TecnoByteApp</strong> guardadas en la columna JSON
                <code class="bg-slate-100 px-1 rounded">tenants.whatsapp_config</code>:
            </p>
            <pre class="bg-slate-900 text-emerald-300 text-xs p-3 rounded-lg overflow-auto"><code>{
  "email": "cliente@empresa.com",
  "password": "xxx",
  "api_base_url": "https://wa-api.tecnobyteapp.com:1422",
  "connection_ids": [15, 28]
}</code></pre>
            <p class="text-sm text-slate-600">
                <code class="bg-slate-100 px-1 rounded">WhatsappResolverService</code> mantiene un mapa cacheado
                <code>connection_id → tenant_id</code> (TTL 5 min) para que cuando llega un webhook de TecnoByteApp,
                el sistema sepa a qué tenant pertenece sin ambigüedad.
            </p>
        </section>

        {{-- 6. SUPER-ADMIN --}}
        <section id="superadmin" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-violet-600"></i> 6. Super-admin & impersonación
            </h2>
            <p class="text-sm text-slate-600">
                Un usuario es <strong>super-admin</strong> si <code>tenant_id IS NULL</code> + tiene el rol
                <code>super-admin</code>. Por defecto sólo ve la sección "⭐ Super Admin" del sidebar.
            </p>

            <h3 class="font-bold text-slate-700 mt-3">Impersonación ("Ver como")</h3>
            <p class="text-sm text-slate-600">
                En <code>/admin/tenants</code> hay un botón "Ver como" en cada card. Al darle click, se setea
                <code>session('tenant_imitado_id')</code> y el middleware <code>SetCurrentTenant</code> usa ese ID
                en lugar de filtrar por usuario. Así el super-admin entra al panel del cliente como si fuera él.
            </p>

            <h3 class="font-bold text-slate-700 mt-3">Bloqueo de rutas operativas</h3>
            <p class="text-sm text-slate-600">
                El middleware <code class="bg-slate-100 px-1 rounded">no_super_sin_imp</code>
                (<code>BloquearSuperAdminSinImpersonar</code>) impide que el super-admin entre a /pedidos, /clientes,
                /productos, etc. SIN antes elegir "Ver como" un tenant. Esto evita ver datos mezclados.
            </p>
        </section>

        {{-- 7. OPERACIONES --}}
        <section id="ops" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-server text-violet-600"></i> 7. Operaciones (VPS / Docker)
            </h2>

            <h3 class="font-bold text-slate-700">VPS</h3>
            <ul class="text-sm text-slate-600 space-y-1">
                <li><strong>IP</strong>: 145.79.7.71</li>
                <li><strong>Path repo</strong>: <code>/srv/proyectopedidoshacienda</code></li>
                <li><strong>Nginx host</strong>: <code>/etc/nginx/sites-enabled/</code></li>
                <li><strong>Logs subdominios</strong>: <code>/var/log/tenant-subdomain-watcher.log</code></li>
                <li><strong>Cert SSL</strong>: <code>/etc/letsencrypt/live/{dominio}/</code></li>
            </ul>

            <h3 class="font-bold text-slate-700 mt-3">Contenedores Docker</h3>
            <ul class="text-sm text-slate-600 space-y-1">
                <li><code class="bg-slate-100 px-1 rounded">pedidos_hacienda_app</code> → Laravel (puerto host 8088)</li>
                <li><code class="bg-slate-100 px-1 rounded">pedidos_hacienda_reverb</code> → WebSockets (puerto host 8092)</li>
                <li><code class="bg-slate-100 px-1 rounded">pedidos_hacienda_scheduler</code> → cron de Laravel</li>
                <li><code class="bg-slate-100 px-1 rounded">pedidos_hacienda_db</code> → MySQL 8</li>
            </ul>

            <h3 class="font-bold text-slate-700 mt-3">Servicio systemd del watcher</h3>
            <pre class="bg-slate-900 text-emerald-300 text-xs p-3 rounded-lg overflow-auto"><code># Estado
sudo systemctl status tenant-subdomain-watcher

# Reiniciar
sudo systemctl restart tenant-subdomain-watcher

# Ver logs
sudo tail -f /var/log/tenant-subdomain-watcher.log</code></pre>

            <h3 class="font-bold text-slate-700 mt-3">Comandos artisan útiles</h3>
            <pre class="bg-slate-900 text-emerald-300 text-xs p-3 rounded-lg overflow-auto"><code># Configurar subdominio de un tenant (CLI)
php artisan tenants:setup-subdominio la-hacienda

# Configurar TODOS los tenants activos
php artisan tenants:setup-subdominio --all

# Suspender tenants vencidos (corre solo a las 3 AM)
php artisan tenants:suspender-vencidos

# Sin SSL (solo Nginx)
php artisan tenants:setup-subdominio la-hacienda --no-ssl

# Sin DNS (asume que ya existe en Hostinger)
php artisan tenants:setup-subdominio la-hacienda --no-dns</code></pre>

            <h3 class="font-bold text-slate-700 mt-3">Deploy típico</h3>
            <pre class="bg-slate-900 text-emerald-300 text-xs p-3 rounded-lg overflow-auto"><code>cd /srv/proyectopedidoshacienda
git pull
docker compose exec pedidos_hacienda_app composer install --no-dev --optimize-autoloader
docker compose exec pedidos_hacienda_app php artisan migrate --force
docker compose exec pedidos_hacienda_app php artisan config:cache
docker compose exec pedidos_hacienda_app php artisan view:cache</code></pre>
        </section>

        {{-- 8. TROUBLESHOOTING --}}
        <section id="troubleshooting" class="rounded-2xl bg-white border border-slate-200 p-6 space-y-3">
            <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-stethoscope text-violet-600"></i> 8. Troubleshooting
            </h2>

            <div class="space-y-3 mt-2">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-sm font-bold text-amber-800">"Domain name contains an invalid character" (certbot)</div>
                    <p class="text-xs text-amber-900 mt-1">
                        El slug tiene <code>_</code>, espacio o mayúscula. Renómbralo a kebab-case:
                        <code>UPDATE tenants SET slug='nuevo-slug' WHERE id=X;</code>, borra el DNS viejo en Hostinger,
                        limpia <code>storage/app/nginx-tenants/</code> y vuelve a configurar.
                    </p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-sm font-bold text-amber-800">El modal se queda en "Esperando al watcher..."</div>
                    <p class="text-xs text-amber-900 mt-1">
                        El servicio systemd no está corriendo. Ejecuta en el host:
                        <code>sudo systemctl status tenant-subdomain-watcher</code>. Si está caído:
                        <code>sudo systemctl restart tenant-subdomain-watcher</code>.
                    </p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-sm font-bold text-amber-800">Spatie\Permission\PermissionRegistrar does not exist</div>
                    <p class="text-xs text-amber-900 mt-1">
                        Faltó <code>composer install</code> en producción.
                        <code>docker compose exec pedidos_hacienda_app composer install --no-dev --optimize-autoloader</code>
                    </p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-sm font-bold text-amber-800">Super-admin ve datos mezclados de varios tenants</div>
                    <p class="text-xs text-amber-900 mt-1">
                        El middleware <code>no_super_sin_imp</code> debería impedir esto. Si no funciona, revisa que
                        está aplicado al grupo de rutas operativas en <code>routes/web.php</code>.
                    </p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div class="text-sm font-bold text-amber-800">Hostinger API: 401 Unauthorized</div>
                    <p class="text-xs text-amber-900 mt-1">
                        La API key expiró o no tiene permisos sobre el dominio. Genera una nueva en Hostinger →
                        actualiza <code>HOSTINGER_API_KEY</code> en .env → <code>php artisan config:cache</code>.
                    </p>
                </div>
            </div>
        </section>

        {{-- FOOTER --}}
        <div class="text-center text-xs text-slate-400 pt-6">
            Última actualización: {{ now()->format('d/m/Y') }} · TecnoByte360 SaaS Platform
        </div>
    </div>
</div>
