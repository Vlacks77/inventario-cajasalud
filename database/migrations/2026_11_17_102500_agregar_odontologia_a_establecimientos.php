<?php

use App\Models\Establecimiento;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Incorpora las 3 unidades de Odontología como establecimientos de tipo UNIDAD SOLICITANTE.
     */
    public function up(): void
    {
        $unidades = [
            'ODONTOLOGIA - HOSPITAL',
            'ODONTOLOGIA - POLICONSULTORIO CENTRAL',
            'ODONTOLOGIA - POLICONSULTORIO EL ALTO',
        ];

        foreach ($unidades as $nombre) {
            Establecimiento::firstOrCreate(
                ['nombre' => $nombre],
                [
                    'tipo'   => 'UNIDAD SOLICITANTE',
                    'estado' => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se eliminan registros para preservar la integridad referencial histórica
    }
};
