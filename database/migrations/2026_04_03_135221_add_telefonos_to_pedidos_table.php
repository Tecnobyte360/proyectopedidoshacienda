<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('telefono_whatsapp')->nullable()->after('cliente_nombre');
            $table->string('telefono_contacto')->nullable()->after('telefono_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['telefono_whatsapp', 'telefono_contacto']);
        });
    }
};