<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;color:#222}
.title{background:#0b3d62;color:#fff;font-weight:bold;font-size:16px;padding:10px}
.subtitle{background:#0b3d62;color:#fff;font-weight:bold;font-size:13px;padding:8px}
.note{color:#e85d04;font-size:15px;font-weight:bold}
.meta{border-collapse:collapse;width:100%;margin-bottom:12px}
.meta td{border:1px solid #bfc9d2;padding:6px}
.label{font-weight:bold;color:#0b3d62}
.items{border-collapse:collapse;width:100%}
.items th{background:#0b3d62;color:#fff;border:1px solid #7f8c96;padding:5px}
.items td{border:1px solid #9aa7b2;padding:5px}
.total{font-weight:bold;background:#f3f6f8}
.right{text-align:right}
.footer{margin-top:14px;border-top:2px solid #0b3d62;padding-top:8px;color:#667788}
</style></head>
<body>
<table class="meta">
<tr>
<td colspan="3" class="title">CAJA DE SALUD DE CAMINOS Y R.A.<br><span style="font-size:11px;">SISTEMA DE GESTIÓN DE ALMACÉN</span></td>
<td class="title" style="text-align:center;"><span style="font-size:10px;">N.º DE NOTA DE INGRESO</span><br><span class="note">{{ $ingreso->numero_nota }}</span></td>
</tr>
</table>
<table class="meta">
<tr><td><span class="label">Fecha:</span> {{ $ingreso->fecha_ingreso?->format('d/m/Y') }}</td><td><span class="label">Almacén:</span> {{ $ingreso->almacen }}</td></tr>
<tr><td><span class="label">Procedencia / proveedor:</span> {{ $ingreso->proveedor?->nombre ?? '—' }}</td><td><span class="label">Tipo:</span> {{ $ingreso->tipo_ingreso ?? '—' }}</td></tr>
<tr><td><span class="label">N.º remisión:</span> {{ $ingreso->numero_remision ?: '—' }}</td><td><span class="label">N.º factura:</span> {{ $ingreso->numero_factura ?: '—' }}</td></tr>
<tr><td colspan="2"><span class="label">N.º de Orden de Compra:</span> {{ $ingreso->numero_orden_compra ?: '—' }}</td></tr>
</table>
<table class="items">
<thead><tr><th>Partida</th><th>LINAME</th><th>Descripción / concentración</th><th>Forma / unidad</th><th>Lote</th><th>Vencimiento</th><th>Cantidad</th><th>P. Unit. (Bs)</th><th>Importe (Bs)</th></tr></thead>
<tbody>
@foreach($ingreso->lotes as $lote)
<tr>
<td>{{ $lote->medicamento->partidaPresupuestaria?->codigo ?? '—' }}</td><td>{{ $lote->medicamento->codigo }}</td>
<td>{{ $lote->medicamento->nombre }} {{ $lote->medicamento->concentracion }}</td>
<td>{{ $lote->medicamento->forma_farmaceutica }} / {{ $lote->medicamento->unidad_presentacion }}</td>
<td>{{ $lote->codigo_lote }}</td><td>{{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
<td>{{ $lote->cantidad_inicial }}</td><td>{{ number_format((float)$lote->precio_unitario,2,'.','') }}</td><td>{{ number_format((float)$lote->importe_total,2,'.','') }}</td>
</tr>
@endforeach
</tbody>
<tfoot><tr class="total"><td colspan="8" class="right">TOTAL (Bs)</td><td class="right">{{ number_format($total,2,'.','') }}</td></tr></tfoot>
</table>
<p><span class="label">Observaciones:</span> {{ $ingreso->observacion ?: 'Sin observaciones.' }}</p>
<p><span class="label">Recibido por:</span> {{ $ingreso->recibido_por }}</p>
<div class="footer">Documento generado por el Sistema de Gestión de Almacén · Caja de Salud de Caminos y R.A.</div>
</body></html>
