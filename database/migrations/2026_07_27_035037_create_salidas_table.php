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
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            // Relaciones con el medicamento y de qué lote específico está saliendo
            $table->foreignId('medicamento_id')->constrained('medicamentos');
            $table->foreignId('lote_id')->constrained('lotes');
            
            // Datos de la salida
            $table->integer('cantidad');
            $table->string('destino'); // Ej: Farmacia Central, Quirófano...
            $table->string('entregado_a')->nullable(); // Nombre del doctor/enfermera
            $table->date('fecha_salida');
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};
