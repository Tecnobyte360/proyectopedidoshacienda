<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telefono')) {
                $table->string('telefono', 30)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'sede_id')) {
                $table->foreignId('sede_id')->nullable()->after('telefono')->constrained('sedes')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'activo')) {
                $table->boolean('activo')->default(true)->after('sede_id');
            }
            if (!Schema::hasColumn('users', 'ultimo_login_at')) {
                $table->timestamp('ultimo_login_at')->nullable()->after('activo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'sede_id', 'activo', 'ultimo_login_at']);
        });
    }
};
