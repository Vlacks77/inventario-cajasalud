<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas_presupuestarias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('ingresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('almacen', 150);
            $table->date('fecha_ingreso');
            $table->string('numero_nota', 30)->nullable()->unique();
            $table->string('numero_remision', 100)->nullable();
            $table->string('numero_factura', 100)->nullable();
            $table->string('tipo_ingreso', 50)->default('compra_local');
            $table->text('observacion')->nullable();
            $table->string('recibido_por', 255);
            $table->string('autorizado_por', 255)->default('CAJA NACIONAL DE CAMINOS');
            $table->timestamps();
        });

        Schema::table('medicamentos', function (Blueprint $table) {
            $table->foreignId('partida_presupuestaria_id')->nullable()->after('id')
                ->constrained('partidas_presupuestarias')->nullOnDelete();
            $table->string('tipo_producto', 100)->default('Medicamento')->after('nombre');
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->foreignId('ingreso_id')->nullable()->after('id')->constrained('ingresos')->nullOnDelete();
            $table->decimal('precio_unitario', 14, 2)->default(0)->after('cantidad_actual');
            $table->decimal('importe_total', 14, 2)->default(0)->after('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ingreso_id');
            $table->dropColumn(['precio_unitario', 'importe_total']);
        });
        Schema::table('medicamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partida_presupuestaria_id');
            $table->dropColumn('tipo_producto');
        });
        Schema::dropIfExists('ingresos');
        Schema::dropIfExists('partidas_presupuestarias');
    }
};
