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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
        
            // Relaciones con las otras tablas
            $table->foreignId('medicamento_id')->constrained('medicamentos')->onDelete('restrict');
            $table->foreignId('proveedor_id')->constrained('proveedores')->onDelete('restrict');
            
            $table->string('codigo_lote'); // El lote impreso en la caja
            $table->date('fecha_vencimiento');
            $table->integer('cantidad_inicial'); // Cuántos ingresaron originalmente
            $table->integer('cantidad_actual');  // Cuántos quedan disponibles en este lote
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
