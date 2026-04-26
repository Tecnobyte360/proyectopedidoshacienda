<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domiciliarios', function (Blueprint $table) {
            if (!Schema::hasColumn('domiciliarios', 'token_acceso')) {
                $table->string('token_acceso', 64)->nullable()->unique()->after('telefono');
            }
            if (!Schema::hasColumn('domiciliarios', 'lat_actual')) {
                $table->float('lat_actual')->nullable()->after('token_acceso');
            }
            if (!Schema::hasColumn('domiciliarios', 'lng_actual')) {
                $table->float('lng_actual')->nullable()->after('lat_actual');
            }
            if (!Schema::hasColumn('domiciliarios', 'ubicacion_actualizada_at')) {
                $table->timestamp('ubicacion_actualizada_at')->nullable()->after('lng_actual');
            }
        });

        // Generar token para los domiciliarios existentes
        DB::table('domiciliarios')
            ->whereNull('token_acceso')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($d) {
                DB::table('domiciliarios')->where('id', $d->id)->update([
                    'token_acceso' => (string) Str::uuid(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('domiciliarios', function (Blueprint $table) {
            foreach (['token_acceso', 'lat_actual', 'lng_actual', 'ubicacion_actualizada_at'] as $c) {
                if (Schema::hasColumn('domiciliarios', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
