<?php
    echo "\xEF\xBB\xBF";

    $meses = [
        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
        5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
        9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
    ];

    $periodoTexto = ($meses[(int) $cierreMensual->periodo->format('n')] ?? '')
        . ' - ' . $cierreMensual->periodo->format('Y');
    $fechaSaldoMes = $cierreMensual->fecha_hasta->format('d/m/Y');

    // Fuente única de clasificación institucional: la misma utilizada por la vista previa.
    $clasificador = app(\App\Services\ClasificadorInventarioService::class);
    $gruposPrincipales = $clasificador->grupos();
    $subgruposLaboratorio = $clasificador->subgruposLaboratorio();

    $detallesColeccion = collect($detalles);

    // Clasificamos una sola vez por detalle para evitar discrepancias y trabajo repetido.
    $clasificados = $detallesColeccion->map(function ($d) use ($clasificador) {
        return [
            'detalle' => $d,
            'clasificacion' => $clasificador->clasificar(
                data_get($d, 'codigo'),
                data_get($d, 'grupo_producto')
            ),
        ];
    });

    $detallesAgrupados = $clasificados->groupBy(function ($item) {
        return $item['clasificacion']['grupo'] ?? 'SIN CLASIFICACIÓN';
    });

    $sinClasificar = $clasificados
        ->filter(function ($item) {
            return ($item['clasificacion']['clasificado'] ?? true) === false;
        })
        ->values();

    $ordenarItems = static function ($items) {
        return $items->sortBy(function ($item) {
            return strtoupper((string) data_get($item['detalle'], 'descripcion'));
        }, SORT_NATURAL | SORT_FLAG_CASE);
    };

    $numero = static function ($valor, $decimales) {
        return number_format((float) $valor, $decimales, '.', '');
    };

    // Renderizado de cada producto. Se mantiene aquí para no depender de otro Blade parcial
    // y evitar que un @endif/@foreach desbalanceado rompa la descarga completa.
    $renderProducto = static function ($item, $fila) use ($numero) {
        $d = $item['detalle'];
        $claseFila = ($fila % 2 === 0) ? 'row-a' : 'row-b';

        $partida = e((string) data_get($d, 'partida_codigo', ''));
        $codigo = e((string) data_get($d, 'codigo', ''));
        $descripcion = e((string) data_get($d, 'descripcion', ''));
        $forma = e((string) data_get($d, 'forma_farmaceutica', ''));

        $saq = $numero(data_get($d, 'saldo_anterior_cantidad', 0), 3);
        $sap = $numero(data_get($d, 'saldo_anterior_precio', 0), 6);
        $sai = $numero(data_get($d, 'saldo_anterior_importe', 0), 2);
        $trq = $numero(data_get($d, 'transferencia_cantidad', 0), 3);
        $trp = $numero(data_get($d, 'transferencia_precio', 0), 6);
        $tri = $numero(data_get($d, 'transferencia_importe', 0), 2);
        $clq = $numero(data_get($d, 'compra_local_cantidad', 0), 3);
        $clp = $numero(data_get($d, 'compra_local_precio', 0), 6);
        $cli = $numero(data_get($d, 'compra_local_importe', 0), 2);
        $tiq = $numero(data_get($d, 'total_ingresos_cantidad', 0), 3);
        $tip = $numero(data_get($d, 'total_ingresos_precio', 0), 6);
        $tii = $numero(data_get($d, 'total_ingresos_importe', 0), 2);
        $eq = $numero(data_get($d, 'egreso_cantidad', 0), 3);
        $ei = $numero(data_get($d, 'egreso_importe', 0), 2);
        $smq = $numero(data_get($d, 'saldo_mes_cantidad', 0), 3);
        $smp = $numero(data_get($d, 'saldo_mes_precio', 0), 6);
        $smi = $numero(data_get($d, 'saldo_mes_importe', 0), 2);

        return '<tr class="' . $claseFila . '">' .
            '<td>' . $partida . '</td>' .
            '<td>' . $codigo . '</td>' .
            '<td style="text-align:left">' . $descripcion . '</td>' .
            '<td>' . $forma . '</td>' .
            '<td class="cant-precio">' . $saq . '</td><td class="cant-precio">' . $sap . '</td><td class="importe">' . $sai . '</td>' .
            '<td class="cant-precio">' . $trq . '</td><td class="cant-precio">' . $trp . '</td><td class="importe">' . $tri . '</td>' .
            '<td class="cant-precio">' . $clq . '</td><td class="cant-precio">' . $clp . '</td><td class="importe">' . $cli . '</td>' .
            '<td class="cant-precio">' . $tiq . '</td><td class="cant-precio">' . $tip . '</td><td class="importe">' . $tii . '</td>' .
            '<td class="cant-precio">' . $eq . '</td><td class="importe">' . $ei . '</td>' .
            '<td class="cant-precio">' . $smq . '</td><td class="cant-precio">' . $smp . '</td><td class="importe">' . $smi . '</td>' .
            '</tr>';
    };

    $filasHtml = '';
    $fila = 0;

    foreach ($gruposPrincipales as $numeroGrupo => $grupo) {
        $itemsGrupo = $detallesAgrupados->get($grupo, collect());

        if ($itemsGrupo->isEmpty()) {
            continue;
        }

        $filasHtml .= '<tr class="grupo-row"><td colspan="21">' . e(($numeroGrupo + 1) . '. ' . $grupo) . '</td></tr>';

        if ($grupo === 'MATERIAL DE LABORATORIO Y REACTIVOS') {
            foreach ($subgruposLaboratorio as $subnumero => $subgrupo) {
                $itemsSub = $itemsGrupo->filter(function ($item) use ($subgrupo) {
                    return ($item['clasificacion']['subgrupo'] ?? null) === $subgrupo;
                });

                if ($itemsSub->isEmpty()) {
                    continue;
                }

                $filasHtml .= '<tr class="subgrupo-row"><td colspan="21">10.' . e($subnumero + 1) . '. ' . e($subgrupo) . '</td></tr>';

                foreach ($ordenarItems($itemsSub) as $item) {
                    $filasHtml .= $renderProducto($item, $fila++);
                }
            }

            // Por seguridad, cualquier producto del grupo 10 sin subgrupo válido sigue apareciendo.
            $itemsSinSub = $itemsGrupo->filter(function ($item) {
                return ($item['clasificacion']['subgrupo'] ?? null) === null;
            });

            foreach ($ordenarItems($itemsSinSub) as $item) {
                $filasHtml .= $renderProducto($item, $fila++);
            }
        } else {
            foreach ($ordenarItems($itemsGrupo) as $item) {
                $filasHtml .= $renderProducto($item, $fila++);
            }
        }
    }

    $advertenciaHtml = '';
    if ($sinClasificar->isNotEmpty()) {
        $codigosSinClasificar = $sinClasificar
            ->map(function ($item) {
                return trim((string) data_get($item['detalle'], 'codigo', 'SIN CÓDIGO'));
            })
            ->filter()
            ->values();

        $advertenciaHtml = '<div style="font-family:Arial,sans-serif;font-size:9pt;margin:8px 0;padding:7px;border:1px solid #c55a11;">' .
            '<strong>ADVERTENCIA DE CLASIFICACIÓN:</strong> ' .
            e($sinClasificar->count()) . ' ítem(s) no tienen una clasificación institucional válida. ' .
            'Fueron incluidos en "19. OTROS MATERIALES Y SUMINISTROS" para mantener la misma clasificación ' .
            'de la vista previa, pero se identifican aquí para revisión y no fueron omitidos.' .
            '<br><strong>Códigos:</strong> ' . e($codigosSinClasificar->join(', ')) .
            '</div>';
    }

    $resumenHtml = '';
    foreach ($resumen as $i => $r) {
        $clase = ($i % 2) ? 'row-b' : 'row-a';
        $resumenHtml .= '<tr class="' . $clase . '">' .
            '<td style="text-align:left">' . e($r['grupo']) . '</td>' .
            '<td>' . $numero($r['saldo_anterior_importe'] ?? 0, 2) . '</td>' .
            '<td>' . $numero($r['transferencia_importe'] ?? 0, 2) . '</td>' .
            '<td>' . $numero($r['compra_local_importe'] ?? 0, 2) . '</td>' .
            '<td>' . $numero($r['total_ingresos_importe'] ?? 0, 2) . '</td>' .
            '<td>' . $numero($r['egreso_importe'] ?? 0, 2) . '</td>' .
            '<td>' . $numero($r['saldo_mes_importe'] ?? 0, 2) . '</td>' .
            '</tr>';
    }
?>

<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<style>
table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:9pt}
th,td{border:1px solid #7f8c96;padding:4px 5px;vertical-align:middle;text-align:center;white-space:nowrap}
.title{background:#1f4663;color:#fff;font-weight:bold;text-align:center}
.t1{font-size:14pt;padding:7px}.t2{font-size:12pt;padding:6px}.t3,.t4{font-size:11pt;padding:5px}
.base{background:#d9e5ee;color:#1f2933;font-weight:bold}
.sa{background:#4f81bd;color:#fff;font-weight:bold}
.trans{background:#70ad47;color:#fff;font-weight:bold}
.compra{background:#ed7d31;color:#fff;font-weight:bold}
.total{background:#5b9bd5;color:#fff;font-weight:bold}
.egreso{background:#c55a11;color:#fff;font-weight:bold}
.saldo{background:#4472c4;color:#fff;font-weight:bold}
.cant-precio{background:#F2DCDB;color:#3f3f3f}
.importe{background:#f7f7f7;color:#3f3f3f}
.row-a{background:#fff}.row-b{background:#eef3f7}
.row-a .cant-precio,.row-b .cant-precio{background:#F2DCDB}
.row-a .importe,.row-b .importe{background:#f7f7f7}
.resumen-titulo{background:#1f4663;color:#fff;font-weight:bold}
.grupo-row{background:#1f4663;color:#fff;font-weight:bold;font-size:10pt}
.subgrupo-row{background:#9dc3e6;color:#17365d;font-weight:bold;font-size:9pt}
.grupo-row td,.subgrupo-row td{text-align:left;padding:6px}
.resumen-head{background:#d9e5ee;font-weight:bold}
</style>
</head>
<body>

<table>
<tr><th class="title t1" colspan="21">MOVIMIENTO MENSUAL FISICO VALORADO</th></tr>
<tr><th class="title t2" colspan="21">ALMACEN DE MEDICAMENTOS E INSUMOS MEDICOS</th></tr>
<tr><th class="title t3" colspan="21"><?= e(strtoupper($cierreMensual->almacen ?: 'REGIONAL LA PAZ')) ?></th></tr>
<tr><th class="title t4" colspan="21"><?= e($periodoTexto) ?></th></tr>
<tr>
<th class="base" rowspan="2">Partida</th>
<th class="base" rowspan="2">Código</th>
<th class="base" rowspan="2">Descripción</th>
<th class="base" rowspan="2">Forma</th>
<th class="sa" colspan="3">SALDO ANTERIOR</th>
<th class="trans" colspan="3">INGRESOS TRANS. ENTRE REGIONALES</th>
<th class="compra" colspan="3">INGRESOS COMPRAS LOCALES</th>
<th class="total" colspan="3">TOTAL INGRESOS REGIONAL</th>
<th class="egreso" colspan="2">EGRESOS REGIONAL</th>
<th class="saldo" colspan="3">SALDO DEL MES AL <?= e($fechaSaldoMes) ?></th>
</tr>
<tr>
<th class="cant-precio">SA Cant.</th><th class="cant-precio">SA P.Unit.</th><th class="importe">SA Importe</th>
<th class="cant-precio">Transf. Cant.</th><th class="cant-precio">Transf. P.Unit.</th><th class="importe">Transf. Importe</th>
<th class="cant-precio">Compra Cant.</th><th class="cant-precio">Compra P.Unit.</th><th class="importe">Compra Importe</th>
<th class="cant-precio">Total Ing. Cant.</th><th class="cant-precio">Total Ing. P.Unit.</th><th class="importe">Total Ing. Importe</th>
<th class="cant-precio">Egreso Cant.</th><th class="importe">Egreso Importe</th>
<th class="cant-precio">Saldo Cant.</th><th class="cant-precio">Saldo P.Unit.</th><th class="importe">Saldo Importe</th>
</tr>
{!! $filasHtml !!}
</table>

{!! $advertenciaHtml !!}

<table>
<tr><th class="resumen-titulo" colspan="7">RESUMEN POR GRUPO</th></tr>
<tr>
<th class="resumen-head">Grupo</th>
<th class="sa">Saldo anterior</th>
<th class="trans">Transferencias</th>
<th class="compra">Compras locales</th>
<th class="total">Total ingresos</th>
<th class="egreso">Egresos</th>
<th class="saldo">Saldo mes</th>
</tr>
{!! $resumenHtml !!}
</table>

</body>
</html>
