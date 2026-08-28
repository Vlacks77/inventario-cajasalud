<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cierres_mensuales', function (Blueprint $table) {
            $table->id();
            $table->string('almacen', 150)->default('REGIONAL LA PAZ');
            $table->date('periodo')->unique(); // primer día del mes cerrado
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('estado', 20)->default('CERRADO');
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('importe_saldo_anterior', 16, 2)->default(0);
            $table->decimal('importe_ingresos_transferencia', 16, 2)->default(0);
            $table->decimal('importe_ingresos_compra_local', 16, 2)->default(0);
            $table->decimal('importe_total_ingresos', 16, 2)->default(0);
            $table->decimal('importe_egresos', 16, 2)->default(0);
            $table->decimal('importe_saldo_mes', 16, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
            $table->foreign('usuario_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('cierre_mensual_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cierre_mensual_id')->constrained('cierres_mensuales')->cascadeOnDelete();
            $table->unsignedBigInteger('medicamento_id')->nullable();
            $table->string('partida_codigo', 20)->nullable();
            $table->string('codigo', 100)->nullable();
            $table->string('descripcion', 500);
            $table->string('forma_farmaceutica', 255)->nullable();
            $table->string('grupo_producto', 255)->nullable();
            $table->decimal('saldo_anterior_cantidad', 16, 3)->default(0);
            $table->decimal('saldo_anterior_precio', 16, 6)->default(0);
            $table->decimal('saldo_anterior_importe', 16, 2)->default(0);
            $table->decimal('transferencia_cantidad', 16, 3)->default(0);
            $table->decimal('transferencia_precio', 16, 6)->default(0);
            $table->decimal('transferencia_importe', 16, 2)->default(0);
            $table->decimal('compra_local_cantidad', 16, 3)->default(0);
            $table->decimal('compra_local_precio', 16, 6)->default(0);
            $table->decimal('compra_local_importe', 16, 2)->default(0);
            $table->decimal('total_ingresos_cantidad', 16, 3)->default(0);
            $table->decimal('total_ingresos_precio', 16, 6)->default(0);
            $table->decimal('total_ingresos_importe', 16, 2)->default(0);
            $table->decimal('egreso_cantidad', 16, 3)->default(0);
            $table->decimal('egreso_importe', 16, 2)->default(0);
            $table->decimal('saldo_mes_cantidad', 16, 3)->default(0);
            $table->decimal('saldo_mes_precio', 16, 6)->default(0);
            $table->decimal('saldo_mes_importe', 16, 2)->default(0);
            $table->timestamps();
            $table->index(['cierre_mensual_id', 'codigo']);
            $table->index('medicamento_id');
        });
    }
    public function down(): void { Schema::dropIfExists('cierre_mensual_detalles'); Schema::dropIfExists('cierres_mensuales'); }
};
