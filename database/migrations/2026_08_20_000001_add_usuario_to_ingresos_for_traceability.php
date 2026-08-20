<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asocia cada nuevo ingreso con el usuario autenticado que lo registró.
     * Se permite NULL para conservar los ingresos históricos sin inventar
     * información de auditoría que no existía cuando fueron creados.
     */
    public function up(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->foreignId('usuario_id')
                ->nullable()
                ->after('proveedor_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_id');
        });
    }
};
