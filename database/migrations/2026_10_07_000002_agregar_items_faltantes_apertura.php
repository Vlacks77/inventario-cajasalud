<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $partida342 = DB::table('partidas_presupuestarias')->where('codigo', '34200')->value('id') ?? 1;
        $partida394 = DB::table('partidas_presupuestarias')->where('codigo', '39400')->value('id') ?? 9;

        // 1. Crear o actualizar los 3 medicamentos en el catálogo
        $now = now();

        $medP7777Id = DB::table('medicamentos')->updateOrInsert(
            ['codigo' => 'P7777'],
            [
                'partida_presupuestaria_id' => $partida342,
                'nombre'                    => 'Loratadina',
                'tipo_producto'             => 'MEDICAMENTOS E INSUMOS MEDICOS',
                'grupo_producto'            => 'MEDICAMENTOS',
                'concentracion'             => '5mg/5ml',
                'forma_farmaceutica'        => 'Jarabe',
                'unidad_presentacion'       => 'Frasco',
                'stock_minimo'              => 0,
                'descripcion'               => 'Loratadina 5mg/5ml Jarabe (Código provisional P7777)',
                'estado'                    => 1,
                'updated_at'                => $now,
            ]
        );
        $idP7777 = DB::table('medicamentos')->where('codigo', 'P7777')->value('id');

        $medDTO053Id = DB::table('medicamentos')->updateOrInsert(
            ['codigo' => 'DTO053'],
            [
                'partida_presupuestaria_id' => $partida394,
                'nombre'                    => 'Tubo endotraqueal con balon',
                'tipo_producto'             => 'MEDICAMENTOS E INSUMOS MEDICOS',
                'grupo_producto'            => 'INSUMOS MEDICOS',
                'concentracion'             => 'Nº 7.5',
                'forma_farmaceutica'        => 'Pieza',
                'unidad_presentacion'       => 'Pieza',
                'stock_minimo'              => 0,
                'descripcion'               => 'Tubo endotraqueal con balon Nº 7.5',
                'estado'                    => 1,
                'updated_at'                => $now,
            ]
        );
        $idDTO053 = DB::table('medicamentos')->where('codigo', 'DTO053')->value('id');

        $medP7778Id = DB::table('medicamentos')->updateOrInsert(
            ['codigo' => 'P7778'],
            [
                'partida_presupuestaria_id' => $partida342,
                'nombre'                    => 'Acido Ascorbico (Vitamina C)',
                'tipo_producto'             => 'MEDICAMENTOS E INSUMOS MEDICOS',
                'grupo_producto'            => 'MEDICAMENTOS',
                'concentracion'             => '1 g',
                'forma_farmaceutica'        => 'Comprimido',
                'unidad_presentacion'       => 'Comprimido',
                'stock_minimo'              => 0,
                'descripcion'               => 'Acido Ascorbico (Vitamina C) 1 g (Código provisional P7778)',
                'estado'                    => 1,
                'updated_at'                => $now,
            ]
        );
        $idP7778 = DB::table('medicamentos')->where('codigo', 'P7778')->value('id');

        // 2. Crear los lotes de apertura para los 2 ítems con stock
        $ingresoApertura = DB::table('ingresos')->where('tipo_ingreso', 'apertura')->first();
        if ($ingresoApertura) {
            $provId = $ingresoApertura->proveedor_id;
            $ingId  = $ingresoApertura->id;

            // Lote P7777 (186 unidades a Bs 12.44 = Bs 2.313,84)
            DB::table('lotes')->updateOrInsert(
                ['ingreso_id' => $ingId, 'medicamento_id' => $idP7777],
                [
                    'proveedor_id'      => $provId,
                    'codigo_lote'       => 'APT-20261001-P7777',
                    'fecha_vencimiento' => '2030-12-31',
                    'cantidad_inicial'  => 186,
                    'cantidad_actual'   => 186,
                    'precio_unitario'   => 12.44,
                    'importe_total'     => 2313.84,
                    'es_reembolso'      => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );

            // Lote DTO053 (110 unidades a Bs 17.55 = Bs 1.930,50)
            DB::table('lotes')->updateOrInsert(
                ['ingreso_id' => $ingId, 'medicamento_id' => $idDTO053],
                [
                    'proveedor_id'      => $provId,
                    'codigo_lote'       => 'APT-20261001-DTO053',
                    'fecha_vencimiento' => '2030-12-31',
                    'cantidad_inicial'  => 110,
                    'cantidad_actual'   => 110,
                    'precio_unitario'   => 17.55,
                    'importe_total'     => 1930.50,
                    'es_reembolso'      => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );
        }

        // 3. Añadir registros a cierre_mensual_detalles para el cierre de Septiembre 2026
        $cierreSept = DB::table('cierres_mensuales')->where('periodo', '2026-09-01')->first();
        if ($cierreSept) {
            $cierreId = $cierreSept->id;

            DB::table('cierre_mensual_detalles')->updateOrInsert(
                ['cierre_mensual_id' => $cierreId, 'codigo' => 'P7777'],
                [
                    'medicamento_id'          => $idP7777,
                    'partida_codigo'          => '34200',
                    'descripcion'             => 'Loratadina',
                    'forma_farmaceutica'      => 'Jarabe',
                    'grupo_producto'          => 'MEDICAMENTOS',
                    'saldo_anterior_cantidad' => 0,
                    'saldo_anterior_precio'   => 0,
                    'saldo_anterior_importe'  => 0,
                    'transferencia_cantidad'  => 0,
                    'transferencia_precio'    => 0,
                    'transferencia_importe'   => 0,
                    'compra_local_cantidad'   => 0,
                    'compra_local_precio'     => 0,
                    'compra_local_importe'    => 0,
                    'total_ingresos_cantidad' => 0,
                    'total_ingresos_precio'   => 0,
                    'total_ingresos_importe'  => 0,
                    'egreso_cantidad'         => 0,
                    'egreso_importe'          => 0,
                    'saldo_mes_cantidad'      => 186,
                    'saldo_mes_precio'        => 12.44,
                    'saldo_mes_importe'       => 2313.84,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]
            );

            DB::table('cierre_mensual_detalles')->updateOrInsert(
                ['cierre_mensual_id' => $cierreId, 'codigo' => 'DTO053'],
                [
                    'medicamento_id'          => $idDTO053,
                    'partida_codigo'          => '39400',
                    'descripcion'             => 'Tubo endotraqueal con balon',
                    'forma_farmaceutica'      => 'Pieza',
                    'grupo_producto'          => 'INSUMOS MEDICOS',
                    'saldo_anterior_cantidad' => 0,
                    'saldo_anterior_precio'   => 0,
                    'saldo_anterior_importe'  => 0,
                    'transferencia_cantidad'  => 0,
                    'transferencia_precio'    => 0,
                    'transferencia_importe'   => 0,
                    'compra_local_cantidad'   => 0,
                    'compra_local_precio'     => 0,
                    'compra_local_importe'    => 0,
                    'total_ingresos_cantidad' => 0,
                    'total_ingresos_precio'   => 0,
                    'total_ingresos_importe'  => 0,
                    'egreso_cantidad'         => 0,
                    'egreso_importe'          => 0,
                    'saldo_mes_cantidad'      => 110,
                    'saldo_mes_precio'        => 17.55,
                    'saldo_mes_importe'       => 1930.50,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]
            );

            DB::table('cierre_mensual_detalles')->updateOrInsert(
                ['cierre_mensual_id' => $cierreId, 'codigo' => 'P7778'],
                [
                    'medicamento_id'          => $idP7778,
                    'partida_codigo'          => '34200',
                    'descripcion'             => 'Acido Ascorbico (Vitamina C)',
                    'forma_farmaceutica'      => 'Comprimido',
                    'grupo_producto'          => 'MEDICAMENTOS',
                    'saldo_anterior_cantidad' => 0,
                    'saldo_anterior_precio'   => 0,
                    'saldo_anterior_importe'  => 0,
                    'transferencia_cantidad'  => 0,
                    'transferencia_precio'    => 0,
                    'transferencia_importe'   => 0,
                    'compra_local_cantidad'   => 0,
                    'compra_local_precio'     => 0,
                    'compra_local_importe'    => 0,
                    'total_ingresos_cantidad' => 0,
                    'total_ingresos_precio'   => 0,
                    'total_ingresos_importe'  => 0,
                    'egreso_cantidad'         => 0,
                    'egreso_importe'          => 0,
                    'saldo_mes_cantidad'      => 0,
                    'saldo_mes_precio'        => 0,
                    'saldo_mes_importe'       => 0,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]
            );

            // Actualizar totales en la cabecera del cierre
            $totalItemsConStock = DB::table('cierre_mensual_detalles')
                ->where('cierre_mensual_id', $cierreId)
                ->where('saldo_mes_cantidad', '>', 0)
                ->count();

            $totalImporte = DB::table('cierre_mensual_detalles')
                ->where('cierre_mensual_id', $cierreId)
                ->sum('saldo_mes_importe');

            DB::table('cierres_mensuales')
                ->where('id', $cierreId)
                ->update([
                    'total_items'       => $totalItemsConStock,
                    'importe_saldo_mes' => $totalImporte,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('cierre_mensual_detalles')->whereIn('codigo', ['P7777', 'P7778', 'DTO053'])->delete();
        $ingresoApertura = DB::table('ingresos')->where('tipo_ingreso', 'apertura')->first();
        if ($ingresoApertura) {
            DB::table('lotes')->where('ingreso_id', $ingresoApertura->id)
                ->whereIn('codigo_lote', ['APT-20261001-P7777', 'APT-20261001-DTO053'])
                ->delete();
        }
        DB::table('medicamentos')->whereIn('codigo', ['P7777', 'P7778', 'DTO053'])->delete();
    }
};
