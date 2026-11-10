<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Actualiza el medicamento con código LINAME A0304 para incluir su denominación completa
     * y concentración en el nombre ("Butilbromuro de Hioscina (Butilescopolamina) 20 mg/ml"),
     * así como la descripción base ("Butilbromuro de Hioscina (Butilescopolamina)") y
     * concentración ("20 mg/ml"), garantizando coherencia con el estándar del catálogo.
     */
    public function up(): void
    {
        $codigo = 'A0304';
        $nombreCompleto = 'Butilbromuro de Hioscina (Butilescopolamina) 20 mg/ml';
        $descripcionBase = 'Butilbromuro de Hioscina (Butilescopolamina)';
        $concentracion = '20 mg/ml';

        // 1. Actualizar tabla medicamentos
        $medUpdate = [
            'nombre'        => $nombreCompleto,
            'concentracion' => $concentracion,
            'updated_at'    => now(),
        ];

        if (Schema::hasColumn('medicamentos', 'descripcion')) {
            $medUpdate['descripcion'] = $descripcionBase;
        }

        DB::table('medicamentos')
            ->where('codigo', $codigo)
            ->update($medUpdate);

        // 2. Actualizar cierre_mensual_detalles si existen registros vinculados
        if (Schema::hasTable('cierre_mensual_detalles')) {
            $medId = DB::table('medicamentos')->where('codigo', $codigo)->value('id');

            DB::table('cierre_mensual_detalles')
                ->where(function ($q) use ($codigo, $medId) {
                    $q->where('codigo', $codigo);
                    if ($medId) {
                        $q->orWhere('medicamento_id', $medId);
                    }
                })
                ->update([
                    'descripcion' => $nombreCompleto,
                    'updated_at'  => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('medicamentos')
            ->where('codigo', 'A0304')
            ->update([
                'nombre'        => 'Butilbromuro de Hioscina',
                'concentracion' => '20 mg/ml',
                'descripcion'   => 'Butilbromuro de Hioscina 20 mg/ml Inyectable (Código LINAME A0304)',
                'updated_at'    => now(),
            ]);
    }
};

