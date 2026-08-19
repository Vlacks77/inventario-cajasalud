<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    /**
     * Historial de movimientos. Por ahora los movimientos disponibles son
     * ingresos; la estructura queda preparada para sumar salidas.
     *
     * Sin filtros devuelve únicamente los últimos 10 movimientos.
     * Con filtros se permite consultar hasta 300 coincidencias.
     */
    public function index(Request $request)
    {
        $buscar = trim($request->query('buscar', ''));
        $procedencia = trim($request->query('procedencia', ''));
        $tieneFiltros = $buscar !== ''
            || $procedencia !== ''
            || $request->filled('fecha_desde')
            || $request->filled('fecha_hasta');

        $lotes = Lote::with([
                'medicamento.partidaPresupuestaria',
                'proveedor',
                'ingreso',
            ])
            ->whereNotNull('ingreso_id')
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->whereHas('medicamento', function ($producto) use ($buscar) {
                    $producto->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%")
                        ->orWhere('concentracion', 'like', "%{$buscar}%")
                        ->orWhere('grupo_producto', 'like', "%{$buscar}%");
                });
            })
            ->when(
                $procedencia !== '',
                fn ($query) => $query->whereHas(
                    'proveedor',
                    fn ($p) => $p->where('nombre', 'like', "%{$procedencia}%")
                )
            )
            ->when(
                $request->filled('fecha_desde'),
                fn ($query) => $query->whereHas(
                    'ingreso',
                    fn ($i) => $i->whereDate('fecha_ingreso', '>=', $request->query('fecha_desde'))
                )
            )
            ->when(
                $request->filled('fecha_hasta'),
                fn ($query) => $query->whereHas(
                    'ingreso',
                    fn ($i) => $i->whereDate('fecha_ingreso', '<=', $request->query('fecha_hasta'))
                )
            )
            ->orderByDesc(
                \App\Models\Ingreso::select('fecha_ingreso')
                    ->whereColumn('ingresos.id', 'lotes.ingreso_id')
                    ->limit(1)
            )
            ->orderByDesc('lotes.id')
            ->limit($tieneFiltros ? 300 : 10)
            ->get();

        return response()->json($lotes);
    }
}
