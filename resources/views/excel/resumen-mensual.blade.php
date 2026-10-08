<?php
    echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
    $numero = static function ($valor, $decimales = 2) {
        return number_format((float) $valor, $decimales, '.', '');
    };
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 9pt; }
    table { border-collapse: collapse; margin-bottom: 25px; width: 100%; }
    th, td { border: 1px solid #7f8c96; padding: 5px 6px; vertical-align: middle; }
    .header-inst { background: #1f4663; color: #ffffff; text-align: center; font-weight: bold; }
    .h1 { font-size: 13pt; padding: 8px; }
    .h2 { font-size: 11pt; padding: 6px; }
    .h3 { font-size: 10pt; padding: 5px; }
    .table-title { background: #2c5282; color: #ffffff; font-size: 11pt; font-weight: bold; text-align: center; padding: 7px; }
    .th-base { background: #d9e5ee; color: #1a202c; font-weight: bold; text-align: center; }
    .th-sa { background: #4f81bd; color: #ffffff; font-weight: bold; text-align: center; }
    .th-trans { background: #70ad47; color: #ffffff; font-weight: bold; text-align: center; }
    .th-compra { background: #ed7d31; color: #ffffff; font-weight: bold; text-align: center; }
    .th-total { background: #5b9bd5; color: #ffffff; font-weight: bold; text-align: center; }
    .th-egreso { background: #c55a11; color: #ffffff; font-weight: bold; text-align: center; }
    .th-saldo { background: #3182ce; color: #ffffff; font-weight: bold; text-align: center; }
    .th-cant { background: #2b6cb0; color: #ffffff; font-weight: bold; text-align: center; }
    .th-val { background: #2c5282; color: #ffffff; font-weight: bold; text-align: center; }
    .num-col { text-align: center; width: 35px; }
    .text-col { text-align: left; }
    .num-val { text-align: right; mso-number-format: "\#\,\#\#0\.00"; }
    .num-qty { text-align: right; mso-number-format: "\#\,\#\#0\.000"; }
    .row-even { background: #ffffff; }
    .row-odd { background: #f7fafc; }
    .row-totales { background: #edf2f7; font-weight: bold; }
    .row-totales td { border-top: 2px solid #2d3748; border-bottom: 2px solid #2d3748; }
    .firmas-table { border: none; margin-top: 40px; page-break-inside: avoid; }
    .firmas-table td { border: none; padding: 10px 20px; vertical-align: top; text-align: center; }
    .firma-box { border-top: 1px solid #4a5568; padding-top: 8px; margin: 0 15px; }
    .firma-cargo { font-weight: bold; font-size: 9pt; color: #2d3748; }
    .firma-nombre { font-size: 8.5pt; color: #4a5568; margin-top: 3px; }
</style>
</head>
<body>

<!-- ENCABEZADO INSTITUCIONAL -->
<table>
    <tr><th class="header-inst h1" colspan="8">CAJA DE SALUD DE CAMINOS Y R.A.</th></tr>
    <tr><th class="header-inst h2" colspan="8">REGIONAL LA PAZ - ALMACÉN CENTRAL DE MEDICAMENTOS E INSUMOS MÉDICOS</th></tr>
    <tr><th class="header-inst h3" colspan="8">BALANCE MENSUAL OFICIAL - PERIODO: {{ $resumen['mes_nombre'] }} / {{ $resumen['anio'] }} ({{ $resumen['estado'] }})</th></tr>
</table>

<!-- TABLA A: RESUMEN CUENTA 121 (FINANCIERA / CONTABLE) -->
<table>
    <thead>
        <tr>
            <th class="table-title" colspan="8">TABLA A: RESUMEN CUENTA 121 (FINANCIERA / CONTABLE)</th>
        </tr>
        <tr>
            <th class="th-base num-col">N°</th>
            <th class="th-base text-col">GRUPO / PARTIDA</th>
            <th class="th-sa">SALDO ANTERIOR</th>
            <th class="th-trans">INGRESOS: TRANSFERENCIAS ENTRE REGIONALES</th>
            <th class="th-compra">INGRESOS: COMPRAS LOCALES</th>
            <th class="th-total">TOTAL INGRESOS REGIONAL</th>
            <th class="th-egreso">EGRESOS REGIONAL</th>
            <th class="th-saldo">SALDO DEL MES</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($resumen['tabla_a'] as $i => $fila)
            @php $clase = ($i % 2 === 0) ? 'row-even' : 'row-odd'; @endphp
            <tr class="{{ $clase }}">
                <td class="num-col">{{ $fila['numero'] }}</td>
                <td class="text-col">{{ $fila['grupo'] }}</td>
                <td class="num-val">{{ $numero($fila['saldo_anterior']) }}</td>
                <td class="num-val">{{ $numero($fila['transferencias']) }}</td>
                <td class="num-val">{{ $numero($fila['compras_locales']) }}</td>
                <td class="num-val">{{ $numero($fila['total_ingresos']) }}</td>
                <td class="num-val">{{ $numero($fila['egresos']) }}</td>
                <td class="num-val" style="font-weight:600;">{{ $numero($fila['saldo_mes']) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="row-totales">
            <td colspan="2" style="text-align:right; font-weight:bold;">TOTALES (BS):</td>
            <td class="num-val">{{ $numero($resumen['totales_a']['saldo_anterior']) }}</td>
            <td class="num-val">{{ $numero($resumen['totales_a']['transferencias']) }}</td>
            <td class="num-val">{{ $numero($resumen['totales_a']['compras_locales']) }}</td>
            <td class="num-val">{{ $numero($resumen['totales_a']['total_ingresos']) }}</td>
            <td class="num-val">{{ $numero($resumen['totales_a']['egresos']) }}</td>
            <td class="num-val" style="font-weight:bold; color:#1a365d;">{{ $numero($resumen['totales_a']['saldo_mes']) }}</td>
        </tr>
    </tfoot>
</table>

<!-- TABLA B: RESUMEN MENSUAL REGIONAL (FÍSICO - VALORADO / KARDEX GLOBAL) -->
<table>
    <thead>
        <tr>
            <th class="table-title" colspan="10">TABLA B: RESUMEN MENSUAL REGIONAL (FÍSICO - VALORADO / KARDEX GLOBAL)</th>
        </tr>
        <tr>
            <th class="th-base num-col" rowspan="2">N°</th>
            <th class="th-base text-col" rowspan="2">DESCRIPCIÓN (PARTIDA)</th>
            <th class="th-cant" colspan="4">CANTIDAD</th>
            <th class="th-val" colspan="4">VALORES (BS)</th>
        </tr>
        <tr>
            <th class="th-base">SALDO INICIAL</th>
            <th class="th-base">ENTRADAS</th>
            <th class="th-base">SALIDAS</th>
            <th class="th-base">SALDO FINAL</th>
            <th class="th-sa">SALDO INICIAL</th>
            <th class="th-total">ENTRADAS</th>
            <th class="th-egreso">SALIDAS</th>
            <th class="th-saldo">SALDO FINAL</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($resumen['tabla_b'] as $i => $fila)
            @php $clase = ($i % 2 === 0) ? 'row-even' : 'row-odd'; @endphp
            <tr class="{{ $clase }}">
                <td class="num-col">{{ $fila['numero'] }}</td>
                <td class="text-col">{{ $fila['descripcion'] }}</td>
                <td class="num-qty">{{ $numero($fila['cantidades']['saldo_inicial'], 3) }}</td>
                <td class="num-qty">{{ $numero($fila['cantidades']['entradas'], 3) }}</td>
                <td class="num-qty">{{ $numero($fila['cantidades']['salidas'], 3) }}</td>
                <td class="num-qty" style="font-weight:600;">{{ $numero($fila['cantidades']['saldo_final'], 3) }}</td>
                <td class="num-val">{{ $numero($fila['valores']['saldo_inicial']) }}</td>
                <td class="num-val">{{ $numero($fila['valores']['entradas']) }}</td>
                <td class="num-val">{{ $numero($fila['valores']['salidas']) }}</td>
                <td class="num-val" style="font-weight:600;">{{ $numero($fila['valores']['saldo_final']) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="row-totales">
            <td colspan="2" style="text-align:right; font-weight:bold;">TOTALES:</td>
            <td class="num-qty">{{ $numero($resumen['totales_b']['cantidades']['saldo_inicial'], 3) }}</td>
            <td class="num-qty">{{ $numero($resumen['totales_b']['cantidades']['entradas'], 3) }}</td>
            <td class="num-qty">{{ $numero($resumen['totales_b']['cantidades']['salidas'], 3) }}</td>
            <td class="num-qty" style="font-weight:bold;">{{ $numero($resumen['totales_b']['cantidades']['saldo_final'], 3) }}</td>
            <td class="num-val">{{ $numero($resumen['totales_b']['valores']['saldo_inicial']) }}</td>
            <td class="num-val">{{ $numero($resumen['totales_b']['valores']['entradas']) }}</td>
            <td class="num-val">{{ $numero($resumen['totales_b']['valores']['salidas']) }}</td>
            <td class="num-val" style="font-weight:bold; color:#1a365d;">{{ $numero($resumen['totales_b']['valores']['saldo_final']) }}</td>
        </tr>
    </tfoot>
</table>

<!-- RESIDUAL DE CUADRE CONTABLE -->
<table>
    <tr>
        <td colspan="10" style="background:#e6fffa; border:1px solid #38b2ac; color:#234e52; font-size:8.5pt; padding:6px 10px;">
            <strong>CERTIFICACIÓN DE CUADRE CONTABLE:</strong>
            Los totales monetarios de la Tabla B cuadran exactamente con la Cuenta 121 (Tabla A).
            Saldo Inicial: Bs {{ $numero($resumen['totales_b']['valores']['saldo_inicial']) }} |
            Entradas/Ingresos: Bs {{ $numero($resumen['totales_b']['valores']['entradas']) }} |
            Salidas/Egresos: Bs {{ $numero($resumen['totales_b']['valores']['salidas']) }} |
            Saldo Final: Bs {{ $numero($resumen['totales_b']['valores']['saldo_final']) }}.
        </td>
    </tr>
</table>

<!-- CASILLAS DE FIRMAS OFICIALES -->
<table class="firmas-table">
    <tr>
        <td style="width:33.33%;">
            <div style="height:55px;"></div>
            <div class="firma-box">
                <div class="firma-cargo">RESPONSABLE DE ALMACÉN</div>
                <div class="firma-nombre">Elaborado por: Dra. Carmen</div>
                <div style="font-size:7.5pt; color:#718096; margin-top:2px;">Firma y Sello</div>
            </div>
        </td>
        <td style="width:33.33%;">
            <div style="height:55px;"></div>
            <div class="firma-box">
                <div class="firma-cargo">ADMINISTRADOR / CONTADOR</div>
                <div class="firma-nombre">Revisado por</div>
                <div style="font-size:7.5pt; color:#718096; margin-top:2px;">Firma y Sello</div>
            </div>
        </td>
        <td style="width:33.33%;">
            <div style="height:55px;"></div>
            <div class="firma-box">
                <div class="firma-cargo">JEFATURA MÉDICA / REGIONAL</div>
                <div class="firma-nombre">Aprobado por</div>
                <div style="font-size:7.5pt; color:#718096; margin-top:2px;">Firma y Sello</div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
