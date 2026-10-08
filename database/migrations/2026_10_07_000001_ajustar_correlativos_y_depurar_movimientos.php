<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modificar tabla salidas:
        // Quitar la restricción UNIQUE global de numero_salida (para permitir que se resetee cada mes)
        // y agregar índice compuesto (fecha_salida, numero_salida)
        Schema::table('salidas', function (Blueprint $table) {
            $table->dropUnique('salidas_numero_salida_unique');
            $table->index(['fecha_salida', 'numero_salida'], 'salidas_fecha_numero_salida_idx');
        });

        // 2. Modificar tabla ingresos:
        // Agregar campo correlativo_anual para tracking numérico por año,
        // quitar UNIQUE global de numero_nota para permitir numeración anual independiente,
        // y agregar índice compuesto (fecha_ingreso, numero_nota)
        Schema::table('ingresos', function (Blueprint $table) {
            $table->unsignedInteger('correlativo_anual')->nullable()->after('numero_nota');
            $table->dropUnique('ingresos_numero_nota_unique');
            $table->index(['fecha_ingreso', 'numero_nota'], 'ingresos_fecha_numero_nota_idx');
        });

        // 3. Depuración de movimientos de prueba:
        // Eliminar salidas y sus detalles
        DB::table('detalle_salidas')->delete();
        DB::table('salidas')->delete();

        // Eliminar lotes posteriores a la apertura (ingreso_id != 1)
        DB::table('lotes')->where('ingreso_id', '!=', 1)->delete();

        // Eliminar ingresos posteriores a la apertura (id != 1)
        DB::table('ingresos')->where('id', '!=', 1)->delete();

        // Restaurar todos los lotes de apertura para que cantidad_actual sea igual a cantidad_inicial
        DB::table('lotes')->where('ingreso_id', 1)->update([
            'cantidad_actual' => DB::raw('cantidad_inicial'),
        ]);

        // Reiniciar AUTO_INCREMENT de salidas a 1 (solo en MySQL)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE salidas AUTO_INCREMENT = 1');
        }
    }

    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropIndex('ingresos_fecha_numero_nota_idx');
            $table->unique('numero_nota', 'ingresos_numero_nota_unique');
            $table->dropColumn('correlativo_anual');
        });

        Schema::table('salidas', function (Blueprint $table) {
            $table->dropIndex('salidas_fecha_numero_salida_idx');
            $table->unique('numero_salida', 'salidas_numero_salida_unique');
        });
    }
};
