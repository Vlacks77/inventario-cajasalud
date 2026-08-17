<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Corrige registros antiguos que quedaron guardados con mojibake.
        DB::table('users')
            ->whereIn('name', [
                'Dra. Encargada de Almac├®n',
                'Dra. Encargada de Almacen',
            ])
            ->update([
                'name' => 'Dra. Encargada de Almacén',
                'updated_at' => now(),
            ]);

        DB::table('ingresos')
            ->whereIn('recibido_por', [
                'Dra. Encargada de Almac├®n',
                'Dra. Encargada de Almacen',
            ])
            ->update([
                'recibido_por' => 'Dra. Encargada de Almacén',
                'updated_at' => now(),
            ]);

        DB::table('establecimientos')
            ->where('nombre', 'Policl├¡nico Sopocachi')
            ->update([
                'nombre' => 'Policlínico Sopocachi',
                'updated_at' => now(),
            ]);

        DB::table('establecimientos')
            ->where('tipo', 'Policl├¡nico')
            ->update([
                'tipo' => 'Policlínico',
                'updated_at' => now(),
            ]);

        // En el catálogo inicial existen tres concentraciones porcentuales
        // almacenadas como decimales sin unidad. Se normalizan una sola vez.
        $porcentajes = [
            '0.05' => '5%',
            '0.03' => '3%',
            '0.01' => '1%',
        ];

        foreach ($porcentajes as $origen => $destino) {
            DB::table('medicamentos')
                ->where('concentracion', $origen)
                ->update([
                    'concentracion' => $destino,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Restaurar únicamente las tres concentraciones normalizadas.
        $porcentajes = [
            '5%' => '0.05',
            '3%' => '0.03',
            '1%' => '0.01',
        ];

        foreach ($porcentajes as $origen => $destino) {
            DB::table('medicamentos')
                ->where('concentracion', $origen)
                ->update([
                    'concentracion' => $destino,
                    'updated_at' => now(),
                ]);
        }
    }
};
