<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usuarios_internos_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('telefono_normalizado', 20)->index();
            $table->string('nombre', 120);
            $table->string('cargo', 120)->nullable();
            $table->string('departamento', 120)->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(['tenant_id', 'telefono_normalizado'], 'uniq_tenant_tel');
        });

        // Marcar conversaciones como internas
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            if (!Schema::hasColumn('conversaciones_whatsapp', 'es_interna')) {
                $table->boolean('es_interna')->default(false)->after('atendida_por_humano');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios_internos_whatsapp');
        Schema::table('conversaciones_whatsapp', function (Blueprint $table) {
            if (Schema::hasColumn('conversaciones_whatsapp', 'es_interna')) {
                $table->dropColumn('es_interna');
            }
        });
    }
};
