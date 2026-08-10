<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;

class LoteController extends Controller
{
    public function index(int $medicamento)
    {
        $lotes = Lote::where('medicamento_id', $medicamento)
            ->where('cantidad_actual', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->get([
                'id',
                'codigo_lote',
                'fecha_vencimiento',
                'cantidad_actual',
            ]);

        return response()->json($lotes);
    }
}
