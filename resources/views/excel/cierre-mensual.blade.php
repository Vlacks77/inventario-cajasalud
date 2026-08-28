@php echo "\xEF\xBB\xBF"; @endphp
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<style>
table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt}
th,td{border:1px solid #8b96a1;padding:4px 6px;vertical-align:middle;text-align:center;white-space:nowrap}
.title{background:#1f4663;color:#ffffff;font-size:14pt;font-weight:bold;text-align:center;padding:7px}
.base{background:#d9e5ee;font-weight:bold}.sa{background:#4f81bd;color:#ffffff;font-weight:bold}.trans{background:#70ad47;color:#ffffff;font-weight:bold}.compra{background:#ed7d31;color:#ffffff;font-weight:bold}.total{background:#5b9bd5;color:#ffffff;font-weight:bold}.egreso{background:#c55a11;color:#ffffff;font-weight:bold}.saldo{background:#4472c4;color:#ffffff;font-weight:bold}
.row-a{background:#ffffff}.row-b{background:#eef3f7}.resumen-titulo{background:#1f4663;color:#ffffff;font-weight:bold}.resumen-head{background:#d9e5ee;font-weight:bold}
</style>
</head>
<body>
<table>
<tr><th class="title" colspan="21">INVENTARIO MENSUAL - {{ $cierreMensual->almacen }} - {{ $cierreMensual->fecha_desde->format('d/m/Y') }} al {{ $cierreMensual->fecha_hasta->format('d/m/Y') }}</th></tr>
<tr>
<th class="base">Partida</th><th class="base">Código</th><th class="base">Descripción</th><th class="base">Forma</th>
<th class="sa">SA Cant.</th><th class="sa">SA P.Unit.</th><th class="sa">SA Importe</th>
<th class="trans">Transf. Cant.</th><th class="trans">Transf. P.Unit.</th><th class="trans">Transf. Importe</th>
<th class="compra">Compra Cant.</th><th class="compra">Compra P.Unit.</th><th class="compra">Compra Importe</th>
<th class="total">Total Ing. Cant.</th><th class="total">Total Ing. P.Unit.</th><th class="total">Total Ing. Importe</th>
<th class="egreso">Egreso Cant.</th><th class="egreso">Egreso Importe</th>
<th class="saldo">Saldo Cant.</th><th class="saldo">Saldo P.Unit.</th><th class="saldo">Saldo Importe</th>
</tr>
@foreach($detalles as $i=>$d)
<tr class="{{ $i % 2 ? 'row-b' : 'row-a' }}">
<td>{{ $d->partida_codigo }}</td><td>{{ $d->codigo }}</td><td>{{ $d->descripcion }}</td><td>{{ $d->forma_farmaceutica }}</td>
<td>{{ $d->saldo_anterior_cantidad }}</td><td>{{ number_format((float)$d->saldo_anterior_precio,6,'.','') }}</td><td>{{ number_format((float)$d->saldo_anterior_importe,2,'.','') }}</td>
<td>{{ $d->transferencia_cantidad }}</td><td>{{ number_format((float)$d->transferencia_precio,6,'.','') }}</td><td>{{ number_format((float)$d->transferencia_importe,2,'.','') }}</td>
<td>{{ $d->compra_local_cantidad }}</td><td>{{ number_format((float)$d->compra_local_precio,6,'.','') }}</td><td>{{ number_format((float)$d->compra_local_importe,2,'.','') }}</td>
<td>{{ $d->total_ingresos_cantidad }}</td><td>{{ number_format((float)$d->total_ingresos_precio,6,'.','') }}</td><td>{{ number_format((float)$d->total_ingresos_importe,2,'.','') }}</td>
<td>{{ $d->egreso_cantidad }}</td><td>{{ number_format((float)$d->egreso_importe,2,'.','') }}</td>
<td>{{ $d->saldo_mes_cantidad }}</td><td>{{ number_format((float)$d->saldo_mes_precio,6,'.','') }}</td><td>{{ number_format((float)$d->saldo_mes_importe,2,'.','') }}</td>
</tr>
@endforeach
</table>
<br>
<table>
<tr><th class="resumen-titulo" colspan="7">RESUMEN POR GRUPO</th></tr>
<tr><th class="resumen-head">Grupo</th><th class="sa">Saldo anterior</th><th class="trans">Transferencias</th><th class="compra">Compras locales</th><th class="total">Total ingresos</th><th class="egreso">Egresos</th><th class="saldo">Saldo mes</th></tr>
@foreach($resumen as $i=>$r)<tr class="{{ $i % 2 ? 'row-b' : 'row-a' }}"><td>{{ $r['grupo'] }}</td><td>{{ number_format((float)$r['saldo_anterior_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['transferencia_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['compra_local_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['total_ingresos_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['egreso_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['saldo_mes_importe'],2,'.','') }}</td></tr>@endforeach
</table>
</body>
</html>
