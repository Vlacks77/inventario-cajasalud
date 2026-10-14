<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Buscar el medicamento con código LINAME A0205
        $med = DB::table('medicamentos')->where('codigo', 'A0205')->first();
        if (!$med) {
            return;
        }

        // 2. Localizar el ingreso de tipo apertura
        $ingresoApertura = DB::table('ingresos')
            ->where('tipo_ingreso', 'apertura')
            ->first();

        $ingresoId = $ingresoApertura ? $ingresoApertura->id : 1;

        // 3. Buscar el lote correspondiente a ese medicamento y dicho ingreso de apertura
        $lote = DB::table('lotes')
            ->where('medicamento_id', $med->id)
            ->where('ingreso_id', $ingresoId)
            ->first();

        // 4. Ajustar el lote con estricto control de stock
        if ($lote) {
            $cantidadOriginal = (int) $lote->cantidad_inicial;
            $diferencia = 650 - $cantidadOriginal; // +400 unidades

            DB::table('lotes')
                ->where('id', $lote->id)
                ->update([
                    'cantidad_inicial' => 650,
                    'cantidad_actual'  => $lote->cantidad_actual + $diferencia,
                    'precio_unitario'  => 9.618570,
                    'importe_total'    => 6252.07,
                    'updated_at'       => now(),
                ]);
        }

        // 5. Actualizar en tablas consolidadas de saldo anterior/cierre de apertura (Septiembre 2026)
        $detallesCierre = DB::table('cierre_mensual_detalles')
            ->where(function ($q) use ($med) {
                $q->where('medicamento_id', $med->id)
                  ->orWhere('codigo', 'A0205');
            })
            ->get();

        foreach ($detallesCierre as $det) {
            DB::table('cierre_mensual_detalles')
                ->where('id', $det->id)
                ->update([
                    'saldo_mes_cantidad' => 650.000,
                    'saldo_mes_precio'   => 9.618570,
                    'saldo_mes_importe'  => 6252.07,
                    'updated_at'         => now(),
                ]);

            // Recalcular importe total del saldo mes en la cabecera del cierre
            $totalSaldoMes = DB::table('cierre_mensual_detalles')
                ->where('cierre_mensual_id', $det->cierre_mensual_id)
                ->sum('saldo_mes_importe');

            DB::table('cierres_mensuales')
                ->where('id', $det->cierre_mensual_id)
                ->update([
                    'importe_saldo_mes' => round($totalSaldoMes, 2),
                    'updated_at'        => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $med = DB::table('medicamentos')->where('codigo', 'A0205')->first();
        if (!$med) {
            return;
        }

        $ingresoApertura = DB::table('ingresos')
            ->where('tipo_ingreso', 'apertura')
            ->first();

        $ingresoId = $ingresoApertura ? $ingresoApertura->id : 1;

        $lote = DB::table('lotes')
            ->where('medicamento_id', $med->id)
            ->where('ingreso_id', $ingresoId)
            ->first();

        if ($lote) {
            $diferencia = (int) $lote->cantidad_inicial - 250; // -400 unidades

            DB::table('lotes')
                ->where('id', $lote->id)
                ->update([
                    'cantidad_inicial' => 250,
                    'cantidad_actual'  => max(0, $lote->cantidad_actual - $diferencia),
                    'precio_unitario'  => 8.150000,
                    'importe_total'    => 2037.50,
                    'updated_at'       => now(),
                ]);
        }

        $detallesCierre = DB::table('cierre_mensual_detalles')
            ->where(function ($q) use ($med) {
                $q->where('medicamento_id', $med->id)
                  ->orWhere('codigo', 'A0205');
            })
            ->get();

        foreach ($detallesCierre as $det) {
            DB::table('cierre_mensual_detalles')
                ->where('id', $det->id)
                ->update([
                    'saldo_mes_cantidad' => 250.000,
                    'saldo_mes_precio'   => 8.150000,
                    'saldo_mes_importe'  => 2037.50,
                    'updated_at'         => now(),
                ]);

            $totalSaldoMes = DB::table('cierre_mensual_detalles')
                ->where('cierre_mensual_id', $det->cierre_mensual_id)
                ->sum('saldo_mes_importe');

            DB::table('cierres_mensuales')
                ->where('id', $det->cierre_mensual_id)
                ->update([
                    'importe_saldo_mes' => round($totalSaldoMes, 2),
                    'updated_at'        => now(),
                ]);
        }
    }
};
