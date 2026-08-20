<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salidas', function (Blueprint $table) {
            $table->unsignedBigInteger('numero_salida')
                ->nullable()
                ->unique()
                ->after('id');

            $table->string('numero_pedido', 100)
                ->nullable()
                ->after('establecimiento_id');
        });

        // Las salidas de prueba existentes conservan su correlativo:
        // ID 1 -> salida N.º 1.
        DB::table('salidas')->whereNull('numero_salida')->update([
            'numero_salida' => DB::raw('id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('salidas', function (Blueprint $table) {
            $table->dropUnique(['numero_salida']);
            $table->dropColumn(['numero_salida', 'numero_pedido']);
        });
    }
};
