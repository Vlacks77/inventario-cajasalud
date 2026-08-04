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

        // Información general de la salida
        $table->date('fecha_salida');

        // Destino de la salida
        $table->foreignId('establecimiento_id')
              ->constrained('establecimientos')
              ->cascadeOnUpdate()
              ->restrictOnDelete();

        // Personas involucradas
        $table->string('solicitado_por', 150);
        $table->string('entregado_a', 150)->nullable();

        // Observaciones
        $table->text('observaciones')->nullable();

        // Estado de la salida
        $table->string('estado', 20)->default('ACTIVA');

        // Usuario que registró la salida
        $table->foreignId('usuario_id')
              ->constrained('users')
              ->cascadeOnUpdate()
              ->restrictOnDelete();

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
