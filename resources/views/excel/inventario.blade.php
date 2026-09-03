<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;color:#222}
.title{background:#0b3d62;color:#fff;font-weight:bold;font-size:16px;padding:10px}
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
<tr><td colspan="6" class="title">CAJA DE SALUD DE CAMINOS Y R.A. — REPORTE DE INVENTARIO</td></tr>
<tr><td><span class="label">Emitido:</span> {{ $fecha->format('d/m/Y H:i') }}</td><td colspan="5"><span class="label">Productos con stock:</span> {{ count($productos) }}</td></tr>
</table>
<table class="items">
<thead><tr><th>LINAME</th><th>Producto</th><th>Partida</th><th>Grupo</th><th>Stock</th><th>Valor actual (Bs)</th></tr></thead>
<tbody>
@forelse($productos as $p)
<tr><td>{{ $p->codigo }}</td><td>{{ $p->nombre }}{{ $p->concentracion ? ' '.$p->concentracion : '' }}</td><td>{{ $p->partidaPresupuestaria?->codigo ?? '—' }}</td><td>{{ $p->grupo_producto ?? '—' }}</td><td class="right">{{ number_format((float)$p->stock_total,0,',','.') }}</td><td class="right">{{ number_format((float)$p->valor_total,2,'.','') }}</td></tr>
@empty
<tr><td colspan="6">No existen productos con stock.</td></tr>
@endforelse
</tbody>
@if(count($productos))
<tfoot><tr class="total"><td colspan="4" class="right">TOTAL</td><td class="right">{{ number_format($productos->sum('stock_total'),0,',','.') }}</td><td class="right">{{ number_format($productos->sum('valor_total'),2,'.','') }}</td></tr></tfoot>
@endif
</table>
<div class="footer">Documento generado por el Sistema de Gestión de Almacén · Caja de Salud de Caminos y R.A.</div>
</body></html>
