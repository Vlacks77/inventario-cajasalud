<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salida;
use App\Services\SalidaService;
use Illuminate\Http\Request;

class SalidaController extends Controller
{
    public function siguienteNumero()
    {
        $siguiente = ((int) Salida::max('numero_salida')) + 1;

        return response()->json([
            'numero_salida' => $siguiente,
        ]);
    }

    public function store(Request $request, SalidaService $salidaService)
    {
        $datos = $request->validate([
            'fecha_salida' => 'required|date',
            'establecimiento_id' => 'required|exists:establecimientos,id',
            'numero_pedido' => 'nullable|string|max:100',
            'solicitado_por' => 'required|string|max:255',
            'entregado_a' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',

            'detalle' => 'required|array|min:1',
            'detalle.*.lote_id' => 'required|exists:lotes,id',
            'detalle.*.cantidad' => 'required|integer|min:1',
        ]);

        $salida = $salidaService->registrar($datos);

        return response()->json([
            'message' => 'Salida registrada correctamente.',
            'salida' => $salida,
        ], 201);
    }
}
