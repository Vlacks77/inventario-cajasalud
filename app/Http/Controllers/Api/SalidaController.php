<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Salida;
use App\Models\Lote;
use Illuminate\Support\Facades\DB;

class SalidaController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validamos que lleguen los datos requeridos
        $request->validate([
            'lote_id' => 'required|exists:lotes,id',
            'cantidad' => 'required|integer|min:1',
            'destino' => 'required|string',
            'fecha_salida' => 'required|date',
        ]);

        try {
            DB::beginTransaction(); // Iniciamos protección de datos

            // 2. Buscamos el lote del cual queremos sacar medicamentos
            $lote = Lote::findOrFail($request->lote_id);

            // 3. Verificamos que no intenten sacar más de lo que hay
            if ($lote->cantidad_actual < $request->cantidad) {
                return response()->json(['message' => 'No hay stock suficiente en este lote.'], 400);
            }

            // 4. Descontamos el stock
            $lote->cantidad_actual -= $request->cantidad;
            $lote->save();

            // 5. Registramos la salida para auditoría (Kardex)
            Salida::create([
                'medicamento_id' => $lote->medicamento_id,
                'lote_id' => $lote->id,
                'cantidad' => $request->cantidad,
                'destino' => $request->destino,
                'entregado_a' => $request->entregado_a,
                'fecha_salida' => $request->fecha_salida,
                'observaciones' => $request->observaciones,
            ]);

            DB::commit(); // Confirmamos los cambios

            return response()->json(['message' => 'Salida registrada y stock descontado correctamente.'], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // Si hay error, deshacemos todo
            return response()->json(['message' => 'Error en el servidor: ' . $e->getMessage()], 500);
        }
    }
}
