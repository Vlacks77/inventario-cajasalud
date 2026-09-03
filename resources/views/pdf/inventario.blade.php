@php
    $logoCaja = public_path('img/logo-caminos-ra-pdf.jpg');
    $logoSistemas = public_path('img/logo-sistemas-la-paz.jpg');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 18px 18px 24px 18px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color:#222; margin:0; }
.header { width:100%; border-collapse:collapse; border-bottom:2px solid #0b3d62; padding-bottom:5px; }
.header td { border:0; padding:0; vertical-align:middle; }
.logo-caja { width:72px; height:auto; }
.header-title { text-align:center; color:#0b3d62; font-size:11px; font-weight:bold; text-transform:uppercase; }
.meta { width:100%; border-collapse:collapse; margin:10px 0 9px; }
.meta td { border:1px solid #bfc9d2; padding:4px; }
.label { font-weight:bold; color:#0b3d62; }
.t { width:100%; border-collapse:collapse; table-layout:fixed; }
.t th { background:#0b3d62; color:#fff; font-size:6.5px; }
.t th,.t td { border:1px solid #9aa7b2; padding:2.5px; vertical-align:top; word-wrap:break-word; }
.t td { font-size:6.5px; }
.r { text-align:right; }
.c { text-align:center; }
.total { background:#f7f7f7; font-weight:bold; }
.footer { width:100%; border-top:2px solid #0b3d62; margin-top:20px; padding-top:5px; border-collapse:collapse; }
.footer td { border:0; vertical-align:middle; }
.small { font-size:6.5px; color:#667788; text-align:center; }
.logo-sistemas { width:100px; height:auto; display:block; margin-left:auto; }
</style>
</head>
<body>
<table class="header">
<tr>
<td style="width:18%;">
@if(file_exists($logoCaja))
<img class="logo-caja" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($logoCaja)) }}">
@endif
</td>
<td style="width:82%;" class="header-title">REPORTE DE INVENTARIO — CAJA DE SALUD DE CAMINOS Y R.A.</td>
</tr>
</table>

<table class="meta">
<tr>
<td><span class="label">Emitido:</span> {{ $fecha->format('d/m/Y H:i') }}</td>
<td><span class="label">Productos con stock:</span> {{ count($productos) }}</td>
</tr>
@if(!empty($filtros['producto_ids']))
<tr><td colspan="2"><span class="label">Filtro:</span> Productos seleccionados</td></tr>
@endif
</table>

<table class="t">
<thead>
<tr>
<th style="width:10%;">LINAME</th>
<th style="width:31%;">Producto</th>
<th style="width:10%;">Partida</th>
<th style="width:19%;">Grupo</th>
<th style="width:10%;">Stock</th>
<th style="width:20%;">Valor actual (Bs)</th>
</tr>
</thead>
<tbody>
@forelse($productos as $p)
<tr>
<td>{{ $p->codigo }}</td>
<td>{{ $p->nombre }}{{ $p->concentracion ? ' '.$p->concentracion : '' }}</td>
<td>{{ $p->partidaPresupuestaria?->codigo ?? '—' }}</td>
<td>{{ $p->grupo_producto ?? '—' }}</td>
<td class="r">{{ number_format((float)$p->stock_total,0,',','.') }}</td>
<td class="r">{{ number_format((float)$p->valor_total,2,',','.') }}</td>
</tr>
@empty
<tr><td colspan="6" class="c">No existen productos con stock para generar el reporte.</td></tr>
@endforelse
</tbody>
@if(count($productos))
<tfoot>
<tr class="total">
<td colspan="4" class="r">TOTAL</td>
<td class="r">{{ number_format($productos->sum('stock_total'),0,',','.') }}</td>
<td class="r">{{ number_format($productos->sum('valor_total'),2,',','.') }}</td>
</tr>
</tfoot>
@endif
</table>

<table class="footer">
<tr>
<td style="width:70%;" class="small">
Documento generado por el Sistema de Gestión de Almacén<br>
Caja de Salud de Caminos y R.A.
</td>
<td style="width:30%;">
@if(file_exists($logoSistemas))
<img class="logo-sistemas" src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($logoSistemas)) }}">
@endif
</td>
</tr>
</table>
</body>
</html>
