<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicamento;
use App\Models\Lote;
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

    /** Consulta de movimientos de ingreso para el Kardex institucional. */
    public function kardex(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));
        $procedencia = trim($request->query('procedencia', ''));

        $lotes = Lote::with(['medicamento.partidaPresupuestaria', 'proveedor', 'ingreso'])
            ->whereNotNull('ingreso_id')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->whereHas('medicamento', function ($producto) use ($buscar) {
                    $producto->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('concentracion', 'like', "%{$buscar}%");
                });
            })
            ->when($procedencia !== '', fn ($query) => $query->whereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$procedencia}%")))
            ->when($request->filled('fecha_desde'), fn ($query) => $query->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '>=', $request->query('fecha_desde'))))
            ->when($request->filled('fecha_hasta'), fn ($query) => $query->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '<=', $request->query('fecha_hasta'))))
            ->latest('id')->limit(300)->get();

        return response()->json($lotes);
    }
}
