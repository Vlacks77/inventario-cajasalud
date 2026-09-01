<!doctype html>
<html lang="es"><head><meta charset="utf-8"></head><body>
<h2>CAJA DE SALUD DE CAMINOS Y R.A.</h2>
<h3>NOTA DE INGRESO A ALMACÉN</h3>
<table border="1">
<tr><td><b>N.º nota</b></td><td>{{ $ingreso->numero_nota }}</td><td><b>Fecha</b></td><td>{{ $ingreso->fecha_ingreso?->format('d/m/Y') }}</td></tr>
<tr><td><b>Almacén</b></td><td>{{ $ingreso->almacen }}</td><td><b>Procedencia</b></td><td>{{ $ingreso->proveedor?->nombre }}</td></tr>
<tr><td><b>N.º remisión</b></td><td>{{ $ingreso->numero_remision ?: '—' }}</td><td><b>N.º factura</b></td><td>{{ $ingreso->numero_factura ?: '—' }}</td></tr><tr><td><b>N.º Orden de Compra</b></td><td colspan="3">{{ $ingreso->numero_orden_compra ?: '—' }}</td></tr>
</table><br>
<table border="1">
<thead><tr><th>Partida</th><th>LINAME</th><th>Descripción / concentración</th><th>Forma / unidad</th><th>Lote</th><th>Vencimiento</th><th>Cantidad</th><th>P. unit. (Bs)</th><th>Importe (Bs)</th></tr></thead>
<tbody>@foreach($ingreso->lotes as $lote)<tr>
<td>{{ $lote->medicamento->partidaPresupuestaria?->codigo ?? '—' }}</td><td>{{ $lote->medicamento->codigo }}</td>
<td>{{ $lote->medicamento->nombre }} {{ $lote->medicamento->concentracion }}</td>
<td>{{ $lote->medicamento->forma_farmaceutica }} / {{ $lote->medicamento->unidad_presentacion }}</td>
<td>{{ $lote->codigo_lote }}</td><td>{{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
<td>{{ $lote->cantidad_inicial }}</td><td>{{ $lote->precio_unitario }}</td><td>{{ $lote->importe_total }}</td>
</tr>@endforeach</tbody>
<tfoot><tr><td colspan="8"><b>TOTAL (Bs)</b></td><td><b>{{ number_format($total,2,'.','') }}</b></td></tr></tfoot>
</table><br>
<p><b>Observaciones:</b> {{ $ingreso->observacion ?: 'Sin observaciones.' }}</p>
<p><b>Recibido por:</b> {{ $ingreso->recibido_por }}</p>
</body></html>