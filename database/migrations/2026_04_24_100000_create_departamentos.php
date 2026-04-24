<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('nombre', 120);
            $table->string('icono_emoji', 20)->nullable();
            $table->string('color', 20)->default('#6366f1');

            // Keywords que disparan la derivación (array de strings)
            $table->json('keywords')->nullable();

            // Mensaje automático al cliente cuando se deriva
            $table->text('saludo_automatico')->nullable();

            // Notificar por WhatsApp a los usuarios internos del depto al derivar
            $table->boolean('notificar_internos')->default(true);

            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'activo']);
        });

        Schema::table('usuarios_internos_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios_internos_whatsapp', 'departamento_id')) {
                $table->foreignId('departamento_id')->nullable()->after('tenant_id')
                    ->constrained('departamentos')->nullOnDelete();
            }
        });

        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('conversaciones_whatsapp', 'departamento_id')) {
                $table->foreignId('departamento_id')->nullable()->after('es_interna')
                    ->constrained('departamentos')->nullOnDelete();
            }
            if (!Schema::hasColumn('conversaciones_whatsapp', 'derivada_at')) {
                $table->timestamp('derivada_at')->nullable()->after('departamento_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            if (Schema::hasColumn('conversaciones_whatsapp', 'departamento_id')) {
                $table->dropForeign(['departamento_id']);
                $table->dropColumn(['departamento_id', 'derivada_at']);
            }
        });
        Schema::table('usuarios_internos_whatsapp', function (Blueprint $table) {
            if (Schema::hasColumn('usuarios_internos_whatsapp', 'departamento_id')) {
                $table->dropForeign(['departamento_id']);
                $table->dropColumn('departamento_id');
            }
        });
        Schema::dropIfExists('departamentos');
    }
};
