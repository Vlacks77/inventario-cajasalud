<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medicamentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Código institucional o Liname del medicamento
            $table->string('nombre');          // Ej: Paracetamol
            $table->string('concentracion');   // Ej: 500 mg
            $table->string('forma_farmaceutica'); // Ej: Comprimido, Jarabe, Ampolla
            $table->string('unidad_presentacion'); // Ej: Caja x 100, Frasco
            $table->integer('stock_minimo')->default(0); // Alerta para reponer inventario
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true); // Activo / Inactivo
            $table->timestamps(); // Registra fecha de creación y actualización
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicamentos');
    }
};
