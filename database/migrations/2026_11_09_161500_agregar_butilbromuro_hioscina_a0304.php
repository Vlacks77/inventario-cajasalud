<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Incorpora al catálogo maestro el medicamento crítico Butilbromuro de Hioscina
     * con código LINAME A0304, vinculado a la partida presupuestaria 34200.
     */
    public function up(): void
    {
        // 1. Obtener la partida presupuestaria 34200 (Medicamentos)
        $partidaId = DB::table('partidas_presupuestarias')
            ->where('codigo', '34200')
            ->value('id');

        if (!$partidaId) {
            $partidaId = DB::table('partidas_presupuestarias')->insertGetId([
                'codigo'     => '34200',
                'nombre'     => 'Medicamentos',
                'estado'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Validar que no exista previamente por código LINAME
        $codigoLiname = 'A0304';
        $existe = DB::table('medicamentos')
            ->where('codigo', $codigoLiname)
            ->exists();

        if (!$existe) {
            DB::table('medicamentos')->insert([
                'partida_presupuestaria_id' => $partidaId,
                'codigo'                    => $codigoLiname,
                'nombre'                    => 'Butilbromuro de Hioscina (Butilescopolamina) 20 mg/ml',
                'tipo_producto'             => 'MEDICAMENTOS E INSUMOS MEDICOS',
                'grupo_producto'            => 'MEDICAMENTOS',
                'concentracion'             => '20 mg/ml',
                'forma_farmaceutica'        => 'Inyectable',
                'unidad_presentacion'       => 'Unidad',
                'stock_minimo'              => 0,
                'descripcion'               => 'Butilbromuro de Hioscina (Butilescopolamina)',
                'estado'                    => 1,
                'created_at'                => now(),
                'updated_at'                => now(),
            ]);
        } else {
            DB::table('medicamentos')
                ->where('codigo', $codigoLiname)
                ->update([
                    'partida_presupuestaria_id' => $partidaId,
                    'nombre'                    => 'Butilbromuro de Hioscina (Butilescopolamina) 20 mg/ml',
                    'tipo_producto'             => 'MEDICAMENTOS E INSUMOS MEDICOS',
                    'grupo_producto'            => 'MEDICAMENTOS',
                    'concentracion'             => '20 mg/ml',
                    'forma_farmaceutica'        => 'Inyectable',
                    'unidad_presentacion'       => 'Unidad',
                    'descripcion'               => 'Butilbromuro de Hioscina (Butilescopolamina)',
                    'updated_at'                => now(),
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
            ->delete();
    }
};

