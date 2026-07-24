<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicamento;
use Illuminate\Http\JsonResponse;

class InventarioController extends Controller
{
    /** Devuelve el inventario con stock consolidado y detalle de lotes. */
    public function index(): JsonResponse
    {
        $medicamentos = Medicamento::query()
            ->select([
                'id', 'codigo', 'nombre', 'concentracion', 'forma_farmaceutica',
                'unidad_presentacion', 'stock_minimo', 'estado',
            ])
            ->withSum('lotes as stock_actual', 'cantidad_actual')
            ->with([
                'lotes' => fn ($query) => $query
                    ->select([
                        'id', 'medicamento_id', 'proveedor_id', 'codigo_lote',
                        'fecha_vencimiento', 'cantidad_actual',
                    ])
                    ->with('proveedor:id,nombre')
                    ->orderBy('fecha_vencimiento'),
            ])
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => $medicamentos]);
    }
}
