<?php

namespace App\Services;

use App\Models\CierreMensual;
use App\Models\DetalleSalida;
use App\Models\Lote;
use App\Models\Medicamento;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ResumenMensualService
{
    private ClasificadorInventarioService $clasificador;

    public function __construct(ClasificadorInventarioService $clasificador)
    {
        $this->clasificador = $clasificador;
    }

    /**
     * Obtiene el resumen oficial del mes (Tablas A y B) para las 20 partidas oficiales.
     * Si el mes ya fue cerrado y congelado en `cierres_mensuales`, recupera el snapshot histórico.
     * Si no, calcula los valores en tiempo real (estado PRELIMINAR).
     */
    public function obtenerResumen(string|Carbon $periodo, string $almacen = 'REGIONAL LA PAZ'): array
    {
        $fecha = is_string($periodo)
            ? Carbon::createFromFormat('Y-m', substr($periodo, 0, 7))->startOfMonth()
            : $periodo->copy()->startOfMonth();

        $desde = $fecha->copy()->startOfMonth();
        $hasta = $fecha->copy()->endOfMonth();
        $periodoKey = $desde->format('Y-m');

        $mesesNombres = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
        ];
        $mesNum = (int) $desde->format('n');
        $anioNum = (int) $desde->format('Y');
        $mesNombre = $mesesNombres[$mesNum] ?? strtoupper($desde->translatedFormat('F'));

        // Verificar si existe un cierre histórico congelado
        $cierre = CierreMensual::whereDate('periodo', $desde->toDateString())->first();

        if ($cierre) {
            $detalles = $cierre->detalles()->get();
            $estado = 'CERRADO';
            $cerradoEn = $cierre->cerrado_en?->format('Y-m-d H:i:s');
            $cierreId = $cierre->id;
            $usuarioCierre = $cierre->usuario?->name;
            $observacion = $cierre->observacion;
        } else {
            $calculo = $this->calcularMes($periodoKey, $almacen);
            $detalles = collect($calculo['detalles']);
            $estado = 'PRELIMINAR';
            $cerradoEn = null;
            $cierreId = null;
            $usuarioCierre = null;
            $observacion = null;
        }

        return $this->construirTablasResumen(
            $detalles,
            [
                'periodo' => $periodoKey,
                'periodo_fecha' => $desde->toDateString(),
                'mes' => $mesNum,
                'mes_nombre' => $mesNombre,
                'anio' => $anioNum,
                'fecha_desde' => $desde->toDateString(),
                'fecha_hasta' => $hasta->toDateString(),
                'almacen' => $almacen,
                'estado' => $estado,
                'cierre_id' => $cierreId,
                'cerrado_en' => $cerradoEn,
                'usuario_cierre' => $usuarioCierre,
                'observacion' => $observacion,
                'total_items_catalogo' => $detalles->count(),
            ]
        );
    }

    /**
     * Construye la estructura de 20 partidas oficiales para TABLA A y TABLA B.
     */
    public function construirTablasResumen(Collection $detalles, array $meta): array
    {
        $gruposOficiales = $this->clasificador->grupos();

        // 1. Inicializar las 20 filas fijas en el orden oficial
        $partidasAcumuladas = [];
        foreach ($gruposOficiales as $idx => $grupoNombre) {
            $partidasAcumuladas[$grupoNombre] = [
                'numero' => $idx + 1,
                'grupo' => $grupoNombre,
                'saldo_anterior_cantidad' => 0.0,
                'saldo_anterior_importe' => 0.0,
                'transferencia_cantidad' => 0.0,
                'transferencia_importe' => 0.0,
                'compra_local_cantidad' => 0.0,
                'compra_local_importe' => 0.0,
                'total_ingresos_cantidad' => 0.0,
                'total_ingresos_importe' => 0.0,
                'egreso_cantidad' => 0.0,
                'egreso_importe' => 0.0,
                'saldo_mes_cantidad' => 0.0,
                'saldo_mes_importe' => 0.0,
                'items_con_stock' => 0,
            ];
        }

        // 2. Acumular los movimientos de cada ítem en su grupo correspondiente
        foreach ($detalles as $d) {
            $item = is_array($d) ? $d : $d->toArray();

            $clasif = $this->clasificador->clasificar(
                $item['codigo'] ?? null,
                $item['grupo_producto'] ?? null
            );

            $grupoDestino = $clasif['grupo'] ?? 'OTROS MATERIALES Y SUMINISTROS';

            if (!isset($partidasAcumuladas[$grupoDestino])) {
                $grupoDestino = 'OTROS MATERIALES Y SUMINISTROS';
            }

            $p = &$partidasAcumuladas[$grupoDestino];

            $saq = (float) ($item['saldo_anterior_cantidad'] ?? 0);
            $sai = (float) ($item['saldo_anterior_importe'] ?? 0);
            $trq = (float) ($item['transferencia_cantidad'] ?? 0);
            $tri = (float) ($item['transferencia_importe'] ?? 0);
            $clq = (float) ($item['compra_local_cantidad'] ?? 0);
            $cli = (float) ($item['compra_local_importe'] ?? 0);
            $tiq = (float) ($item['total_ingresos_cantidad'] ?? ($trq + $clq));
            $tii = (float) ($item['total_ingresos_importe'] ?? ($tri + $cli));
            $eq  = (float) ($item['egreso_cantidad'] ?? 0);
            $ei  = (float) ($item['egreso_importe'] ?? 0);
            $smq = (float) ($item['saldo_mes_cantidad'] ?? ($saq + $tiq - $eq));
            $smi = (float) ($item['saldo_mes_importe'] ?? ($sai + $tii - $ei));

            $p['saldo_anterior_cantidad'] += $saq;
            $p['saldo_anterior_importe']  += $sai;
            $p['transferencia_cantidad']  += $trq;
            $p['transferencia_importe']   += $tri;
            $p['compra_local_cantidad']   += $clq;
            $p['compra_local_importe']    += $cli;
            $p['total_ingresos_cantidad'] += $tiq;
            $p['total_ingresos_importe']  += $tii;
            $p['egreso_cantidad']         += $eq;
            $p['egreso_importe']          += $ei;
            $p['saldo_mes_cantidad']      += $smq;
            $p['saldo_mes_importe']       += $smi;

            if ($smq > 0) {
                $p['items_con_stock']++;
            }
        }
        unset($p);

        // 3. Estructurar TABLA A (Resumen Cuenta 121 - Financiera / Contable)
        $tablaA = [];
        $totalesA = [
            'saldo_anterior' => 0.0,
            'transferencias' => 0.0,
            'compras_locales' => 0.0,
            'total_ingresos' => 0.0,
            'egresos' => 0.0,
            'saldo_mes' => 0.0,
        ];

        // 4. Estructurar TABLA B (Resumen Mensual Regional - Físico - Valorado / Kardex Global)
        $tablaB = [];
        $totalesB = [
            'cantidades' => [
                'saldo_inicial' => 0.0,
                'entradas' => 0.0,
                'salidas' => 0.0,
                'saldo_final' => 0.0,
            ],
            'valores' => [
                'saldo_inicial' => 0.0,
                'entradas' => 0.0,
                'salidas' => 0.0,
                'saldo_final' => 0.0,
            ],
        ];

        foreach ($partidasAcumuladas as $p) {
            $num = $p['numero'];
            $nombre = $p['grupo'];

            $saImporte = round($p['saldo_anterior_importe'], 2);
            $trImporte = round($p['transferencia_importe'], 2);
            $clImporte = round($p['compra_local_importe'], 2);
            $tiImporte = round($p['total_ingresos_importe'], 2);
            $egImporte = round($p['egreso_importe'], 2);
            $smImporte = round($p['saldo_mes_importe'], 2);

            $saCant = round($p['saldo_anterior_cantidad'], 3);
            $tiCant = round($p['total_ingresos_cantidad'], 3);
            $egCant = round($p['egreso_cantidad'], 3);
            $smCant = round($p['saldo_mes_cantidad'], 3);

            // Fila TABLA A
            $tablaA[] = [
                'numero' => $num,
                'grupo' => $nombre,
                'saldo_anterior' => $saImporte,
                'transferencias' => $trImporte,
                'compras_locales' => $clImporte,
                'total_ingresos' => $tiImporte,
                'egresos' => $egImporte,
                'saldo_mes' => $smImporte,
                'items_con_stock' => $p['items_con_stock'],
            ];

            // Fila TABLA B
            $tablaB[] = [
                'numero' => $num,
                'descripcion' => $nombre,
                'cantidades' => [
                    'saldo_inicial' => $saCant,
                    'entradas' => $tiCant,
                    'salidas' => $egCant,
                    'saldo_final' => $smCant,
                ],
                'valores' => [
                    'saldo_inicial' => $saImporte,
                    'entradas' => $tiImporte,
                    'salidas' => $egImporte,
                    'saldo_final' => $smImporte,
                ],
                'items_con_stock' => $p['items_con_stock'],
            ];

            // Sumar a totales
            $totalesA['saldo_anterior']  += $saImporte;
            $totalesA['transferencias']  += $trImporte;
            $totalesA['compras_locales'] += $clImporte;
            $totalesA['total_ingresos']  += $tiImporte;
            $totalesA['egresos']         += $egImporte;
            $totalesA['saldo_mes']       += $smImporte;

            $totalesB['cantidades']['saldo_inicial'] += $saCant;
            $totalesB['cantidades']['entradas']      += $tiCant;
            $totalesB['cantidades']['salidas']       += $egCant;
            $totalesB['cantidades']['saldo_final']   += $smCant;

            $totalesB['valores']['saldo_inicial'] += $saImporte;
            $totalesB['valores']['entradas']      += $tiImporte;
            $totalesB['valores']['salidas']       += $egImporte;
            $totalesB['valores']['saldo_final']   += $smImporte;
        }

        // Redondear totales para evitar decimales flotantes
        foreach ($totalesA as $k => $v) {
            $totalesA[$k] = round($v, 2);
        }
        foreach ($totalesB['cantidades'] as $k => $v) {
            $totalesB['cantidades'][$k] = round($v, 3);
        }
        foreach ($totalesB['valores'] as $k => $v) {
            $totalesB['valores'][$k] = round($v, 2);
        }

        // Verificación de cuadre contable exacto (Tabla B Valores vs Tabla A)
        $cuadreExacto = (
            abs($totalesB['valores']['saldo_inicial'] - $totalesA['saldo_anterior']) < 0.01 &&
            abs($totalesB['valores']['entradas'] - $totalesA['total_ingresos']) < 0.01 &&
            abs($totalesB['valores']['salidas'] - $totalesA['egresos']) < 0.01 &&
            abs($totalesB['valores']['saldo_final'] - $totalesA['saldo_mes']) < 0.01
        );

        return array_merge($meta, [
            'tabla_a' => $tablaA,
            'totales_a' => $totalesA,
            'tabla_b' => $tablaB,
            'totales_b' => $totalesB,
            'cuadre_exacto' => $cuadreExacto,
            'cantidad_partidas' => count($tablaA),
        ]);
    }

    /**
     * Motor de cálculo dinámico para meses no congelados.
     * Reutiliza exactamente las reglas de negocio institucionales del Kardex y CPP.
     */
    public function calcularMes(string $periodo, string $almacen = 'REGIONAL LA PAZ'): array
    {
        $desde = Carbon::createFromFormat('Y-m', $periodo)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();
        $prev = $desde->copy()->subMonth()->startOfMonth();

        $anterior = CierreMensual::whereDate('periodo', $prev->toDateString())->first();
        $prevDetalles = $anterior ? $anterior->detalles()->get()->keyBy('medicamento_id') : collect();

        $productos = Medicamento::with('partidaPresupuestaria:id,codigo')
            ->where('estado', true)
            ->orderBy('partida_presupuestaria_id')
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get();

        $lotes = Lote::with('ingreso:id,fecha_ingreso,tipo_ingreso')
            ->whereHas('ingreso')
            ->get()
            ->groupBy('medicamento_id');

        $loteToMed = Lote::pluck('medicamento_id', 'id');

        $salidas = DetalleSalida::with('salida:id,fecha_salida,estado,tipo_salida')
            ->whereHas('salida', fn($q) => $q->where('estado', 'ACTIVA'))
            ->get()
            ->groupBy(fn($d) => $loteToMed[$d->lote_id] ?? 0);

        $detalles = [];

        foreach ($productos as $p) {
            $movLotes = $lotes->get($p->id, collect());
            $movSalidas = $salidas->get($p->id, collect());

            // 1. Saldo Anterior
            if ($anterior && $prevDetalles->has($p->id)) {
                $pd = $prevDetalles[$p->id];
                $saq = (float) $pd->saldo_mes_cantidad;
                $sai = (float) $pd->saldo_mes_importe;
            } else {
                $saq = 0.0;
                $sai = 0.0;
                foreach ($movLotes as $l) {
                    if ($l->es_reembolso) continue;
                    $f = $l->ingreso?->fecha_ingreso;
                    if ($f && Carbon::parse($f)->lt($desde)) {
                        $saq += (float) $l->cantidad_inicial;
                        $sai += (float) $l->importe_total;
                    }
                }
                foreach ($movSalidas as $s) {
                    $f = $s->salida?->fecha_salida;
                    if ($f && Carbon::parse($f)->lt($desde)) {
                        $q = (float) $s->cantidad;
                        $precio = $s->salida?->tipo_salida === 'EGRESO_REEMBOLSO'
                            ? (float) ($s->precio_unitario_reembolso ?? 0)
                            : (float) optional($movLotes->firstWhere('id', $s->lote_id))->precio_unitario;
                        $saq -= $q;
                        $sai -= $q * $precio;
                    }
                }
            }

            // 2. Ingresos del mes
            $trq = $tri = $clq = $cli = 0.0;
            foreach ($movLotes as $l) {
                $f = $l->ingreso?->fecha_ingreso;
                if (!$f || !Carbon::parse($f)->betweenIncluded($desde, $hasta)) continue;
                if ($l->ingreso?->tipo_ingreso === 'apertura') continue;

                $q = (float) $l->cantidad_inicial;
                $imp = (float) $l->importe_total;

                if (in_array($l->ingreso->tipo_ingreso, ['transferencia', 'transferencia_regional', 'reembolso'], true)) {
                    $trq += $q;
                    $tri += $imp;
                } else {
                    $clq += $q;
                    $cli += $imp;
                }
            }

            // 3. Egresos del mes
            $eq = $ei = 0.0;
            foreach ($movSalidas as $s) {
                $f = $s->salida?->fecha_salida;
                if (!$f || !Carbon::parse($f)->betweenIncluded($desde, $hasta)) continue;

                $q = (float) $s->cantidad;
                $precio = $s->salida?->tipo_salida === 'EGRESO_REEMBOLSO'
                    ? (float) ($s->precio_unitario_reembolso ?? 0)
                    : (float) optional($movLotes->firstWhere('id', $s->lote_id))->precio_unitario;

                $eq += $q;
                $ei += $q * $precio;
            }

            $tiq = $trq + $clq;
            $tii = $tri + $cli;
            $smq = max(0.0, $saq + $tiq - $eq);
            $smi = max(0.0, $sai + $tii - $ei);

            $detalles[] = [
                'medicamento_id' => $p->id,
                'partida_codigo' => $p->partidaPresupuestaria?->codigo,
                'codigo' => $p->codigo,
                'descripcion' => $p->descripcion_completa,
                'forma_farmaceutica' => $p->forma_farmaceutica,
                'grupo_producto' => $p->grupo_producto,
                'saldo_anterior_cantidad' => $saq,
                'saldo_anterior_precio' => $saq > 0 ? round($sai / $saq, 6) : 0,
                'saldo_anterior_importe' => $sai,
                'transferencia_cantidad' => $trq,
                'transferencia_precio' => $trq > 0 ? round($tri / $trq, 6) : 0,
                'transferencia_importe' => $tri,
                'compra_local_cantidad' => $clq,
                'compra_local_precio' => $clq > 0 ? round($cli / $clq, 6) : 0,
                'compra_local_importe' => $cli,
                'total_ingresos_cantidad' => $tiq,
                'total_ingresos_precio' => $tiq > 0 ? round($tii / $tiq, 6) : 0,
                'total_ingresos_importe' => $tii,
                'egreso_cantidad' => $eq,
                'egreso_importe' => $ei,
                'saldo_mes_cantidad' => $smq,
                'saldo_mes_precio' => $smq > 0 ? round($smi / $smq, 6) : 0,
                'saldo_mes_importe' => $smi,
            ];
        }

        return [
            'periodo' => $desde,
            'desde' => $desde,
            'hasta' => $hasta,
            'detalles' => $detalles,
        ];
    }
}
