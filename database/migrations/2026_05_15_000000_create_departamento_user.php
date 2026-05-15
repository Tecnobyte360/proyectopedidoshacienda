<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot many-to-many entre usuarios (User) y departamentos.
 *
 * Cuando el bot deriva una conversación a un departamento (ej: "Cobranza"),
 * solo los usuarios asignados a ESE departamento podrán verla en el chat.
 *
 * Si un usuario no tiene ningún departamento asignado, ve TODAS las
 * conversaciones (típicamente admins/supervisores).
 *
 * Para acceso explícito a todo, usar el permiso `chat.ver-todos`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('departamento_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['user_id', 'departamento_id'], 'departamento_user_unique');
            $t->index('departamento_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_user');
    }
};
