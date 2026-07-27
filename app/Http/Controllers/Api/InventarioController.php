<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicamento;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function index()
    {
        // Traemos todos los medicamentos y, al mismo tiempo, cargamos sus lotes asociados
        $inventario = Medicamento::with('lotes.proveedor')->get()->map(function ($medicamento) {
            
            // Sumamos la cantidad de todos los lotes para tener el Stock Total
            $stock_total = $medicamento->lotes->sum('cantidad_actual');
            
            // Buscamos la fecha de vencimiento más próxima de sus lotes
            $proximo_vencimiento = $medicamento->lotes->where('cantidad_actual', '>', 0)->min('fecha_vencimiento');

            return [
                'id' => $medicamento->id,
                'codigo' => $medicamento->codigo,
                'nombre' => $medicamento->nombre,
                'forma_farmaceutica' => $medicamento->forma_farmaceutica,
                'concentracion' => $medicamento->concentracion,
                'stock_total' => $stock_total,
                'proximo_vencimiento' => $proximo_vencimiento ?? 'Sin stock',
                'lotes' => $medicamento->lotes
            ];
        });

        return response()->json($inventario);
    }
}