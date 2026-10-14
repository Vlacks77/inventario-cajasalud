<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetalleSalida;
use App\Models\Lote;
use Illuminate\Http\Request;

class LoteController extends Controller
{
    public function index(int $medicamento, Request $request)
    {
        $salidaId = $request->query('salida_id');

        $lotes = Lote::where('medicamento_id', $medicamento)
            ->where('es_reembolso', false)
            ->where(function ($q) use ($salidaId) {
                $q->where('cantidad_actual', '>', 0);
                if ($salidaId) {
                    $q->orWhereHas('detalleSalidas', fn ($d) => $d->where('salida_id', $salidaId));
                }
            })
            ->orderBy('fecha_vencimiento')
            ->get([
                'id',
                'codigo_lote',
                'fecha_vencimiento',
                'cantidad_actual',
                'precio_unitario',
            ]);

        if ($salidaId) {
            $detallesSalida = DetalleSalida::where('salida_id', $salidaId)
                ->pluck('cantidad', 'lote_id');

            $lotes->transform(function ($lote) use ($detallesSalida) {
                $cantSalida = (int) ($detallesSalida[$lote->id] ?? 0);
                $lote->salida_prestada = $cantSalida;
                $lote->cantidad_disponible = (int) $lote->cantidad_actual + $cantSalida;
                return $lote;
            });
        } else {
            $lotes->transform(function ($lote) {
                $lote->salida_prestada = 0;
                $lote->cantidad_disponible = (int) $lote->cantidad_actual;
                return $lote;
            });
        }

        return response()->json($lotes);
    }
}
