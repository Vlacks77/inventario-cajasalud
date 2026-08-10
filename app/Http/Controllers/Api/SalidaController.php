<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SalidaService;
use Illuminate\Http\Request;

class SalidaController extends Controller
{
    public function store(Request $request, SalidaService $salidaService)
    {
        // Validar la cabecera y los detalles de la salida
        $datos = $request->validate([
            'fecha_salida' => 'required|date',
            'establecimiento_id' => 'required|exists:establecimientos,id',
            'solicitado_por' => 'required|string|max:255',
            'entregado_a' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',

            'detalle' => 'required|array|min:1',

            'detalle.*.lote_id' => 'required|exists:lotes,id',
            'detalle.*.cantidad' => 'required|integer|min:1',
        ]);

        // Delegar toda la lógica de negocio al Service
        $salida = $salidaService->registrar($datos);

        return response()->json([
            'message' => 'Salida registrada correctamente.',
            'salida' => $salida,
        ], 201);
    }
}
