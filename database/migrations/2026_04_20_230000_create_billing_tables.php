<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── PLANES ──────────────────────────────────────────────────────
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();        // basico | pro | empresa
            $table->string('nombre', 80);                  // "Plan Básico"
            $table->text('descripcion')->nullable();

            $table->decimal('precio_mensual', 10, 2)->default(0);
            $table->decimal('precio_anual', 10, 2)->default(0);
            $table->string('moneda', 5)->default('COP');

            // Límites (null = ilimitado)
            $table->unsignedInteger('max_pedidos_mes')->nullable();
            $table->unsignedInteger('max_usuarios')->nullable();
            $table->unsignedInteger('max_sedes')->nullable();
            $table->unsignedInteger('max_productos')->nullable();
            $table->unsignedInteger('max_clientes')->nullable();

            // Features (bool)
            $table->boolean('feature_whatsapp')->default(true);
            $table->boolean('feature_ia')->default(true);
            $table->boolean('feature_reportes')->default(true);
            $table->boolean('feature_multi_sede')->default(false);
            $table->boolean('feature_api')->default(false);

            $table->boolean('activo')->default(true);
            $table->boolean('publico')->default(true);   // visible en página de planes
            $table->unsignedSmallInteger('orden')->default(0);

            $table->json('caracteristicas_extra')->nullable();   // bullets que se muestran en el pricing card

            $table->timestamps();
        });

        // ── SUSCRIPCIONES ───────────────────────────────────────────────
        // Una sola suscripción ACTIVA por tenant a la vez. Las viejas se
        // marcan como "cancelada" o "expirada" y queda historial.
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('planes');

            // activa | en_trial | suspendida | cancelada | expirada
            $table->string('estado', 20)->default('activa')->index();

            // mensual | anual
            $table->string('ciclo', 10)->default('mensual');

            $table->decimal('monto', 10, 2);             // Lo que paga (puede tener descuento vs precio del plan)
            $table->string('moneda', 5)->default('COP');

            $table->date('fecha_inicio');
            $table->date('fecha_fin');                   // Cuándo expira
            $table->date('proxima_factura_at')->nullable();

            $table->date('fecha_cancelacion')->nullable();
            $table->string('motivo_cancelacion')->nullable();

            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
        });

        // ── PAGOS ───────────────────────────────────────────────────────
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('suscripcion_id')->nullable()->constrained('suscripciones')->nullOnDelete();

            $table->decimal('monto', 10, 2);
            $table->string('moneda', 5)->default('COP');

            // efectivo | transferencia | nequi | daviplata | tarjeta | otro
            $table->string('metodo', 20)->default('transferencia');

            $table->string('referencia', 100)->nullable();   // # transacción, recibo, etc
            $table->string('comprobante_url')->nullable();   // link a foto/pdf del comprobante

            $table->date('fecha_pago');
            $table->date('cubre_desde')->nullable();
            $table->date('cubre_hasta')->nullable();

            // pendiente | confirmado | rechazado
            $table->string('estado', 20)->default('confirmado')->index();

            $table->text('notas')->nullable();

            // Quién registró el pago (super-admin user)
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'fecha_pago']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('suscripciones');
        Schema::dropIfExists('planes');
    }
};
