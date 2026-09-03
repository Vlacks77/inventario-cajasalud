@php
    $logoCaja = public_path('img/logo-caminos-ra-pdf.jpg');
    $logoSistemas = public_path('img/logo-sistemas-la-paz.jpg');
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 18px 22px 24px 22px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color:#222; margin:0; }
.header { width:100%; border-collapse:collapse; border-bottom:2px solid #0b3d62; padding-bottom:5px; }
.header td { border:0; padding:0; vertical-align:middle; }
.logo-caja { width:76px; height:auto; }
.header-title { text-align:center; color:#0b3d62; font-size:11px; font-weight:bold; text-transform:uppercase; }
.meta { width:100%; border-collapse:collapse; margin:12px 0 10px; }
.meta td { border:1px solid #bfc9d2; padding:5px; vertical-align:top; }
.label { font-weight:bold; color:#0b3d62; }
.title { color:#0b3d62; font-size:11px; font-weight:bold; margin:12px 0 8px; text-transform:uppercase; }
.t { width:100%; border-collapse:collapse; table-layout:fixed; }
.t th { background:#0b3d62; color:#fff; font-size:7px; }
.t th,.t td { border:1px solid #9aa7b2; padding:3px; vertical-align:top; word-wrap:break-word; }
.t td { font-size:7px; }
.r { text-align:right; }
.c { text-align:center; }
.footer { width:100%; border-top:2px solid #0b3d62; margin-top:26px; padding-top:6px; border-collapse:collapse; }
.footer td { border:0; vertical-align:middle; }
.small { font-size:7px; color:#667788; text-align:center; }
.logo-sistemas { width:105px; height:auto; display:block; margin-left:auto; }
.badge-ingreso { color:#087f3f; font-weight:bold; }
.badge-salida { color:#c64a00; font-weight:bold; }
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
<td style="width:82%;" class="header-title">KARDEX / MOVIMIENTOS DEL PRODUCTO</td>
</tr>
</table>

<table class="meta">
<tr>
<td><span class="label">LINAME:</span> {{ $producto->codigo }}</td>
<td><span class="label">Producto:</span> {{ $producto->nombre }}</td>
</tr>
<tr>
<td><span class="label">Desde:</span> {{ $desde ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : 'Todos' }}</td>
<td><span class="label">Hasta:</span> {{ $hasta ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : 'Todos' }}</td>
</tr>
</table>

<div class="title">Trazabilidad de movimientos</div>
<table class="t">
<thead>
<tr>
<th style="width:10%;">Fecha</th>
<th style="width:10%;">Tipo</th>
<th style="width:17%;">Referencia</th>
<th style="width:16%;">Lote</th>
<th style="width:11%;">Entrada</th>
<th style="width:11%;">Salida</th>
<th style="width:11%;">Stock</th>
<th style="width:14%;">Usuario</th>
</tr>
</thead>
<tbody>
@forelse($mov as $m)
<tr>
<td>{{ \Carbon\Carbon::parse($m['fecha'])->format('d/m/Y') }}</td>
<td class="c {{ $m['tipo'] === 'INGRESO' ? 'badge-ingreso' : 'badge-salida' }}">{{ $m['tipo'] }}</td>
<td>{{ $m['referencia'] }}</td>
<td>{{ $m['lote'] }}</td>
<td class="r">{{ $m['entrada'] ? number_format($m['entrada'],0,',','.') : '—' }}</td>
<td class="r">{{ $m['salida'] ? number_format($m['salida'],0,',','.') : '—' }}</td>
<td class="r"><strong>{{ number_format($m['stock'],0,',','.') }}</strong></td>
<td>{{ $m['usuario'] }}</td>
</tr>
@empty
<tr><td colspan="8" class="c">No existen movimientos para los filtros seleccionados.</td></tr>
@endforelse
</tbody>
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
