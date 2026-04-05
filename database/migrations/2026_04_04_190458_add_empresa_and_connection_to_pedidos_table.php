<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            
            // 🔥 Empresa dueña del pedido (ownerId)
            $table->unsignedBigInteger('empresa_id')
                ->nullable()
                ->after('sede_id');

            // 🔥 ID de la conexión WhatsApp (id = 15 en tu ejemplo)
            $table->unsignedBigInteger('connection_id')
                ->nullable()
                ->after('canal');

            // 🔥 Opcional (pero recomendado)
            $table->unsignedBigInteger('whatsapp_id')
                ->nullable()
                ->after('connection_id');

            // Índices para rendimiento
            $table->index('empresa_id');
            $table->index('connection_id');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id']);
            $table->dropIndex(['connection_id']);

            $table->dropColumn([
                'empresa_id',
                'connection_id',
                'whatsapp_id'
            ]);
        });
    }
};