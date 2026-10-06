<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetalleSalida;
use App\Models\Lote;
use Illuminate\Http\Request;

class KardexController extends Controller
{
    /**
     * Devuelve una línea cronológica única de ingresos y salidas.
     * Sin filtros: últimos 10 movimientos. Con filtros: hasta 300.
     *
     * Los ingresos de reembolso (tipo_ingreso = 'reembolso') se muestran con
     * tipo = 'REEMBOLSO_INGRESO' para que el frontend los pueda distinguir.
     * Las salidas de tipo EGRESO_REEMBOLSO usan el CPP en lugar del precio
     * del lote individual.
     */
    public function index(Request $request)
    {
        $buscar      = trim($request->query('buscar', ''));
        $procedencia = trim($request->query('procedencia', ''));
        $tipo        = strtoupper(trim($request->query('tipo', '')));
        if (!in_array($tipo, ['', 'INGRESO', 'SALIDA', 'REEMBOLSO_INGRESO', 'EGRESO_REEMBOLSO'], true)) {
            $tipo = '';
        }
        $tieneFiltros = $buscar !== '' || $procedencia !== '' || $tipo !== ''
            || $request->filled('fecha_desde') || $request->filled('fecha_hasta');
        $limite = $tieneFiltros ? 300 : 10;

        // ── INGRESOS ────────────────────────────────────────────────────────
        $ingresos = in_array($tipo, ['SALIDA', 'EGRESO_REEMBOLSO']) ? collect()
            : Lote::with(['medicamento.partidaPresupuestaria', 'proveedor', 'ingreso.usuario'])
                ->whereNotNull('ingreso_id')
                ->when($buscar !== '', function ($query) use ($buscar) {
                    $query->whereHas('medicamento', function ($producto) use ($buscar) {
                        $producto->where('codigo', 'like', "%{$buscar}%")
                            ->orWhere('nombre', 'like', "%{$buscar}%")
                            ->orWhere('grupo_producto', 'like', "%{$buscar}%");
                    });
                })
                ->when($procedencia !== '', fn ($q) => $q->whereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$procedencia}%")))
                ->when($request->filled('fecha_desde'), fn ($q) => $q->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '>=', $request->query('fecha_desde'))))
                ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereHas('ingreso', fn ($i) => $i->whereDate('fecha_ingreso', '<=', $request->query('fecha_hasta'))))
                // Filtro por tipo de ingreso
                ->when($tipo === 'INGRESO', fn ($q) => $q->whereHas('ingreso', fn ($i) => $i->where('tipo_ingreso', '!=', 'reembolso')))
                ->when($tipo === 'REEMBOLSO_INGRESO', fn ($q) => $q->whereHas('ingreso', fn ($i) => $i->where('tipo_ingreso', 'reembolso')))
                ->orderByDesc(\App\Models\Ingreso::select('fecha_ingreso')->whereColumn('ingresos.id', 'lotes.ingreso_id')->limit(1))
                ->orderByDesc('lotes.id')
                ->limit($limite)
                ->get()
                ->map(function ($lote) {
                    $esReembolso = $lote->ingreso?->tipo_ingreso === 'reembolso';
                    return [
                        'id'               => 'I-'.$lote->id,
                        'tipo'             => $esReembolso ? 'REEMBOLSO_INGRESO' : 'INGRESO',
                        'es_reembolso'     => $esReembolso,
                        'fecha'            => optional($lote->ingreso)->fecha_ingreso,
                        'registrado_en'    => optional($lote->ingreso)->created_at,
                        'usuario'          => optional(optional($lote->ingreso)->usuario)->name,
                        'usuario_username' => optional(optional($lote->ingreso)->usuario)->username,
                        'referencia'       => optional($lote->ingreso)->numero_nota,
                        'documento'        => optional($lote->ingreso)->numero_remision,
                        'partida'          => optional($lote->medicamento?->partidaPresupuestaria)->codigo,
                        'codigo'           => optional($lote->medicamento)->codigo,
                        'producto'         => optional($lote->medicamento)->nombre,
                        'forma'            => optional($lote->medicamento)->forma_farmaceutica,
                        'procedencia'      => optional($lote->proveedor)->nombre,
                        'lote'             => $lote->codigo_lote,
                        'vencimiento'      => $lote->fecha_vencimiento,
                        'cantidad'         => $lote->cantidad_inicial,
                        'precio_unitario'  => $lote->precio_unitario,
                        'total'            => $lote->importe_total,
                    ];
                });

        // ── SALIDAS ─────────────────────────────────────────────────────────
        $salidas = in_array($tipo, ['INGRESO', 'REEMBOLSO_INGRESO']) ? collect()
            : DetalleSalida::with(['salida.establecimiento', 'salida.usuario', 'lote.medicamento.partidaPresupuestaria'])
                ->whereHas('salida', fn ($q) => $q->where('estado', 'ACTIVA'))
                ->when($buscar !== '', function ($query) use ($buscar) {
                    $query->whereHas('lote.medicamento', function ($producto) use ($buscar) {
                        $producto->where('codigo', 'like', "%{$buscar}%")
                            ->orWhere('nombre', 'like', "%{$buscar}%")
                            ->orWhere('grupo_producto', 'like', "%{$buscar}%");
                    });
                })
                ->when($procedencia !== '', fn ($q) => $q->whereHas('salida.establecimiento', fn ($e) => $e->where('nombre', 'like', "%{$procedencia}%")))
                ->when($request->filled('fecha_desde'), fn ($q) => $q->whereHas('salida', fn ($s) => $s->whereDate('fecha_salida', '>=', $request->query('fecha_desde'))))
                ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereHas('salida', fn ($s) => $s->whereDate('fecha_salida', '<=', $request->query('fecha_hasta'))))
                // Filtro por tipo de salida
                ->when($tipo === 'SALIDA', fn ($q) => $q->whereHas('salida', fn ($s) => $s->where(fn ($s2) => $s2->whereNull('tipo_salida')->orWhere('tipo_salida', 'normal'))))
                ->when($tipo === 'EGRESO_REEMBOLSO', fn ($q) => $q->whereHas('salida', fn ($s) => $s->where('tipo_salida', 'EGRESO_REEMBOLSO')))
                ->orderByDesc(\App\Models\Salida::select('fecha_salida')->whereColumn('salidas.id', 'detalle_salidas.salida_id')->limit(1))
                ->orderByDesc('detalle_salidas.id')
                ->limit($limite)
                ->get()
                ->map(function ($detalle) {
                    $lote        = $detalle->lote;
                    $salida      = $detalle->salida;
                    $esReembolso = $salida?->tipo_salida === 'EGRESO_REEMBOLSO';

                    // Para egresos de reembolso usamos el CPP almacenado en el detalle;
                    // para salidas normales usamos el precio_unitario del lote.
                    $precioUnit = $esReembolso
                        ? (float) ($detalle->precio_unitario_reembolso ?? 0)
                        : (float) optional($lote)->precio_unitario;

                    return [
                        'id'               => 'S-'.$detalle->id,
                        'tipo'             => $esReembolso ? 'EGRESO_REEMBOLSO' : 'SALIDA',
                        'es_reembolso'     => $esReembolso,
                        'fecha'            => optional($salida)->fecha_salida,
                        'registrado_en'    => optional($salida)->created_at,
                        'usuario'          => optional(optional($salida)->usuario)->name,
                        'usuario_username' => optional(optional($salida)->usuario)->username,
                        'referencia'       => optional($salida)->numero_salida
                            ? ($esReembolso ? 'RMB-Egr N.º ' : 'N.º ').optional($salida)->numero_salida
                            : '—',
                        'documento'        => optional($salida)->numero_pedido,
                        'partida'          => optional($lote?->medicamento?->partidaPresupuestaria)->codigo,
                        'codigo'           => optional($lote?->medicamento)->codigo,
                        'producto'         => optional($lote?->medicamento)->nombre,
                        'forma'            => optional($lote?->medicamento)->forma_farmaceutica,
                        'procedencia'      => optional($salida?->establecimiento)->nombre,
                        'lote'             => optional($lote)->codigo_lote,
                        'vencimiento'      => optional($lote)->fecha_vencimiento,
                        'cantidad'         => $detalle->cantidad,
                        'precio_unitario'  => $precioUnit,
                        'total'            => round((float) $detalle->cantidad * $precioUnit, 2),
                    ];
                });

        $movimientos = $ingresos->merge($salidas)
            ->sortByDesc(fn ($fila) => sprintf('%s-%s', $fila['fecha'] ?? '', $fila['id']))
            ->values()
            ->take($limite)
            ->values();

        return response()->json($movimientos);
    }
}
