<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Actualiza el nombre del usuario y sus registros históricos asociados
     * a 'Dra. Carmen Loza Gutierrez- Encargada de Almacen'.
     */
    public function up(): void
    {
        // 1. Actualizar usuario en tabla users
        DB::table('users')
            ->where('username', 'dra.carmen-almacen')
            ->orWhere('name', 'like', '%Carmen Gutierrez%')
            ->update([
                'name' => 'Dra. Carmen Loza Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        // 2. Actualizar ingresos donde figure como recibido_por
        DB::table('ingresos')
            ->where('recibido_por', 'like', '%Carmen Gutierrez%')
            ->update([
                'recibido_por' => 'Dra. Carmen Loza Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        // 3. Actualizar salidas (solicitado_por y entregado_a)
        DB::table('salidas')
            ->where('solicitado_por', 'like', '%Carmen Gutierrez%')
            ->update([
                'solicitado_por' => 'Dra. Carmen Loza Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        DB::table('salidas')
            ->where('entregado_a', 'like', '%Carmen Gutierrez%')
            ->update([
                'entregado_a' => 'Dra. Carmen Loza Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        // 4. Actualizar observaciones en cierres_mensuales si existiesen
        DB::table('cierres_mensuales')
            ->where('observacion', 'like', '%Carmen Gutierrez%')
            ->update([
                'observacion' => DB::raw("REPLACE(observacion, 'Carmen Gutierrez', 'Carmen Loza Gutierrez')"),
                'updated_at' => now(),
            ]);
    }

    /**
     * Revierte los cambios realizados.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('username', 'dra.carmen-almacen')
            ->update([
                'name' => 'Dra. Carmen Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        DB::table('ingresos')
            ->where('recibido_por', 'like', '%Carmen Loza Gutierrez%')
            ->update([
                'recibido_por' => 'Dra. Carmen Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        DB::table('salidas')
            ->where('solicitado_por', 'like', '%Carmen Loza Gutierrez%')
            ->update([
                'solicitado_por' => 'Dra. Carmen Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        DB::table('salidas')
            ->where('entregado_a', 'like', '%Carmen Loza Gutierrez%')
            ->update([
                'entregado_a' => 'Dra. Carmen Gutierrez- Encargada de Almacen',
                'updated_at' => now(),
            ]);

        DB::table('cierres_mensuales')
            ->where('observacion', 'like', '%Carmen Loza Gutierrez%')
            ->update([
                'observacion' => DB::raw("REPLACE(observacion, 'Carmen Loza Gutierrez', 'Carmen Gutierrez')"),
                'updated_at' => now(),
            ]);
    }
};
