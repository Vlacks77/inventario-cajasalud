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
    Schema::create('detalle_salidas', function (Blueprint $table) {
        $table->id();

        // Cabecera de la salida
        $table->foreignId('salida_id')
              ->constrained('salidas')
              ->cascadeOnUpdate()
              ->cascadeOnDelete();

        // Lote del medicamento entregado
        $table->foreignId('lote_id')
              ->constrained('lotes')
              ->cascadeOnUpdate()
              ->restrictOnDelete();

        // Cantidad entregada
        $table->integer('cantidad');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_salidas');
    }
};
