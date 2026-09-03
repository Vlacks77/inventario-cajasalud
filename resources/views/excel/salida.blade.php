<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;color:#222}
.title{background:#0b3d62;color:#fff;font-weight:bold;font-size:16px;padding:10px}
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
<td class="title" colspan="3">CAJA DE SALUD DE CAMINOS Y R.A.<br><span style="font-size:11px;">SISTEMA DE GESTIÓN DE ALMACÉN</span></td>
<td class="title" style="text-align:center;"><span style="font-size:10px;">N.º DE NOTA DE SALIDA</span><br><span class="note">{{ $salida->numero_salida }}</span></td>
</tr>
</table>
<table class="meta">
<tr><td><span class="label">Fecha:</span> {{ \Carbon\Carbon::parse($salida->fecha_salida)->format('d/m/Y') }}</td><td><span class="label">Almacén:</span> {{ $salida->almacen_origen ?? '—' }}</td></tr>
<tr><td><span class="label">Destino:</span> {{ $salida->establecimiento?->nombre ?? '—' }}</td><td><span class="label">Pedido:</span> {{ $salida->numero_pedido ?: '—' }}</td></tr>
</table>
<table class="items">
<thead><tr><th>Partida</th><th>LINAME</th><th>Producto</th><th>Lote</th><th>Vencimiento</th><th>Cantidad</th><th>P. Unit. (Bs)</th><th>P. Total (Bs)</th></tr></thead>
<tbody>
@foreach($salida->detalles as $d)
<tr>
<td>{{ $d->lote->medicamento->partidaPresupuestaria?->codigo ?? '—' }}</td>
<td>{{ $d->lote->medicamento->codigo }}</td>
<td>{{ $d->lote->medicamento->nombre }} {{ $d->lote->medicamento->concentracion }}</td>
<td>{{ $d->lote->codigo_lote }}</td>
<td>{{ $d->lote->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
<td>{{ $d->cantidad }}</td>
<td>{{ number_format((float)($d->lote->precio_unitario ?? 0),2,'.','') }}</td>
<td>{{ number_format((float)$d->cantidad*(float)($d->lote->precio_unitario ?? 0),2,'.','') }}</td>
</tr>
@endforeach
</tbody>
<tfoot><tr class="total"><td colspan="7" class="right">TOTAL (Bs)</td><td class="right">{{ number_format($total,2,'.','') }}</td></tr></tfoot>
</table>
<p><span class="label">Observaciones:</span> {{ $salida->observaciones ?: 'Sin observaciones.' }}</p>
<div class="footer">Documento generado por el Sistema de Gestión de Almacén · Caja de Salud de Caminos y R.A.</div>
</body></html>
