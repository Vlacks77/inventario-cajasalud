<?php

use App\Models\Establecimiento;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $unidades = [
            "FARMACIA - HOSPITAL",
            "QUIROFANO - HOSPITAL",
            "HEMODIALISIS - HOSPITAL",
            "ENFERMERIA - HOSPITAL",
            "NUTRICION - HOSPITAL",
            "FISIOTERAPIA - HOSPITAL",
            "LABORATORIO - HOSPITAL",
            "RX - HOSPITAL",
            "ECOGRAFIA - HOSPITAL",
            "FARMACIA - POLICONSULTORIO CENTRAL",
            "ENFERMERIA - POLICONSULTORIO CENTRAL",
            "LABORATORIO - POLICONSULTORIO CENTRAL",
            "RX - POLICONSULTORIO CENTRAL",
            "ECOGRAFIA - POLICONSULTORIO CENTRAL",
            "FISIOTERAPIA - POLICONSULTORIO CENTRAL",
            "FARMACIA - POLICONSULTORIO EL ALTO",
            "LABORATORIO - POLICONSULTORIO EL ALTO",
            "ENFERMERIA - POLICONSULTORIO EL ALTO",
            "FISIOTERAPIA - POLICONSULTORIO EL ALTO",
            "CONSULTORIO SEDEM",
            "CONSULTORIO SEDCAM",
            "CONSULTORIO ABC",
            "CAMPAMENTO SAN BUENA AVENTURA",
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

    public function down(): void
    {
        // No se eliminan registros para preservar la integridad referencial
    }
};
