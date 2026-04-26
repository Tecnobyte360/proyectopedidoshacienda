<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('flujos_bot');
    }

    public function down(): void
    {
        // No-op: el feature fue removido completamente.
    }
};
