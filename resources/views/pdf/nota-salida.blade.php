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
.note-box { border:1.5px solid #0b3d62; width:150px; margin-left:auto; text-align:center; }
.note-label { background:#0b3d62; color:#fff; font-weight:bold; font-size:8px; padding:5px 4px; }
.note-number { color:#ed5b00; font-size:13px; font-weight:bold; padding:6px 4px; }
.meta { width:100%; border-collapse:collapse; margin:12px 0 10px; }
.meta td { border:1px solid #bfc9d2; padding:5px; vertical-align:top; }
.label { font-weight:bold; color:#0b3d62; }
.items { width:100%; border-collapse:collapse; table-layout:fixed; }
.items th { background:#0b3d62; color:#fff; font-size:7px; }
.items th, .items td { border:1px solid #9aa7b2; padding:3px; vertical-align:top; word-wrap:break-word; }
.items td { font-size:7px; }
.right { text-align:right; }
.footer-info { margin-top:10px; border-top:1px solid #d6dde3; padding-top:7px; }
.signatures { width:100%; margin-top:32px; border-collapse:collapse; }
.signatures td { text-align:center; width:50%; padding-top:18px; }
.line { border-top:1px solid #333; width:75%; margin:auto; padding-top:4px; }
.bottom { width:100%; border-top:2px solid #0b3d62; margin-top:28px; padding-top:6px; border-collapse:collapse; }
.bottom td { border:0; vertical-align:middle; }
.small { font-size:7px; color:#667788; text-align:center; }
.logo-sistemas { width:105px; height:auto; display:block; margin-left:auto; }
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
    <td style="width:52%;" class="header-title">NOTA DE SALIDA DE ALMACÉN</td>
    <td style="width:30%; text-align:right;">
        <table class="note-box">
            <tr><td class="note-label">N.º DE NOTA DE SALIDA</td></tr>
            <tr><td class="note-number">{{ $salida->numero_salida }}</td></tr>
        </table>
    </td>
</tr>
</table>

<table class="meta">
<tr><td><span class="label">Fecha:</span> {{ \Carbon\Carbon::parse($salida->fecha_salida)->format('d/m/Y') }}</td><td><span class="label">Almacén:</span> {{ $salida->almacen_origen }}</td></tr>
<tr><td><span class="label">Destino:</span> {{ $salida->establecimiento?->nombre ?? '—' }}</td><td><span class="label">Pedido / referencia:</span> {{ $salida->numero_pedido ?: '—' }}</td></tr>
<tr><td><span class="label">Solicitado por:</span> {{ $salida->solicitado_por ?: '—' }}</td><td><span class="label">Registrado por:</span> {{ $salida->usuario?->name ?? '—' }}</td></tr>
</table>

<table class="items">
<thead><tr>
<th style="width:8%">Partida</th>
<th style="width:9%">LINAME</th>
<th style="width:25%">Producto</th>
<th style="width:11%">Lote</th>
<th style="width:11%">Vencimiento</th>
<th style="width:8%">Cant.</th>
<th style="width:13%">P. Unit. (Bs)</th>
<th style="width:15%">P. Total (Bs)</th>
</tr></thead>
<tbody>
@foreach($salida->detalles as $d)
<tr>
<td>{{ $d->lote->medicamento->partidaPresupuestaria?->codigo ?? '—' }}</td>
<td>{{ $d->lote->medicamento->codigo }}</td>
<td>{{ $d->lote->medicamento->nombre }} {{ $d->lote->medicamento->concentracion }}</td>
<td>{{ $d->lote->codigo_lote }}</td>
<td>{{ $d->lote->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</td>
<td class="right">{{ number_format($d->cantidad,0,',','.') }}</td>
<td class="right">{{ number_format((float)($d->lote->precio_unitario ?? 0),2,',','.') }}</td>
<td class="right">{{ number_format((float)$d->cantidad * (float)($d->lote->precio_unitario ?? 0),2,',','.') }}</td>
</tr>
@endforeach
</tbody>
<tfoot><tr>
<td colspan="7" class="right"><strong>TOTAL (Bs)</strong></td>
<td class="right"><strong>{{ number_format($total,2,',','.') }}</strong></td>
</tr></tfoot>
</table>

<div class="footer-info">
<strong>Total literal:</strong> {{ method_exists($salida, 'getTotalLiteralAttribute') ? $salida->total_literal : '' }}<br>
<strong>Observaciones:</strong> {{ $salida->observaciones ?: 'Sin observaciones.' }}
</div>

<table class="signatures">
<tr>
    <td><div class="line">Entregado por: {{ $salida->usuario?->name ?? '—' }}</div></td>
    <td><div class="line">Recibido por: {{ $salida->entregado_a ?: '—' }}</div></td>
</tr>
</table>

<table class="bottom">
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
