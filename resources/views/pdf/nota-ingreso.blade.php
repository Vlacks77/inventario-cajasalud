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
.title { color:#0b3d62; font-size:11px; font-weight:bold; margin:12px 0 8px; text-transform:uppercase; }
.meta { width:100%; border-collapse:collapse; margin-bottom:10px; }
.meta td { border:1px solid #bfc9d2; padding:5px; vertical-align:top; }
.label { font-weight:bold; color:#0b3d62; }
.items { width:100%; border-collapse:collapse; table-layout:fixed; }
.items th { background:#0b3d62; color:#fff; font-size:7px; }
.items th, .items td { border:1px solid #9aa7b2; padding:3px; vertical-align:top; word-wrap:break-word; }
.items td { font-size:7px; }
.right { text-align:right; }
.footer-info { margin-top:10px; border-top:1px solid #d6dde3; padding-top:7px; }
.signatures { width:100%; margin-top:32px; border-collapse:collapse; }
.signatures td { text-align:center; padding-top:18px; }
.line { border-top:1px solid #333; width:55%; margin:auto; padding-top:4px; }
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
    <td style="width:52%;" class="header-title">NOTA DE INGRESO A ALMACÉN</td>
    <td style="width:30%; text-align:right;">
        <table class="note-box">
            <tr><td class="note-label">N.º DE NOTA DE INGRESO</td></tr>
            <tr><td class="note-number">{{ $ingreso->numero_nota }}</td></tr>
        </table>
    </td>
</tr>
</table>

<table class="meta" style="margin-top:12px;">
<tr><td><span class="label">Fecha:</span> {{ $ingreso->fecha_ingreso->format('d/m/Y') }}</td><td><span class="label">Almacén:</span> {{ $ingreso->almacen }}</td></tr>
<tr><td><span class="label">Procedencia / proveedor:</span> {{ $ingreso->proveedor?->nombre ?? '—' }}</td><td><span class="label">Tipo de ingreso:</span> {{ $ingreso->tipo_ingreso ?? '—' }}</td></tr>
<tr><td><span class="label">N.º remisión:</span> {{ $ingreso->numero_remision ?: '—' }}</td><td><span class="label">N.º factura:</span> {{ $ingreso->numero_factura ?: '—' }}</td></tr>
<tr><td colspan="2"><span class="label">N.º de Orden de Compra:</span> {{ $ingreso->numero_orden_compra ?: '—' }}</td></tr>
</table>

<table class="items">
<thead><tr>
<th style="width:8%">Partida</th>
<th style="width:8%">LINAME</th>
<th style="width:20%">Descripción / concentración</th>
<th style="width:13%">Forma / unidad</th>
<th style="width:10%">Lote</th>
<th style="width:11%">Vencimiento</th>
<th style="width:7%">Cant.</th>
<th style="width:11%">P. unit. (Bs)</th>
<th style="width:12%">Importe (Bs)</th>
</tr></thead>
<tbody>
@foreach($ingreso->lotes as $lote)
<tr>
<td>{{ $lote->medicamento->partidaPresupuestaria?->codigo ?? '—' }}</td>
<td>{{ $lote->medicamento->codigo }}</td>
<td>{{ $lote->medicamento->nombre }} {{ $lote->medicamento->concentracion }}</td>
<td>{{ $lote->medicamento->forma_farmaceutica }} / {{ $lote->medicamento->unidad_presentacion }}</td>
<td>{{ $lote->codigo_lote }}</td>
<td>{{ $lote->fecha_vencimiento?->format('d/m/Y') ?? 'No aplica' }}</td>
<td class="right">{{ number_format($lote->cantidad_inicial,0,',','.') }}</td>
<td class="right">{{ number_format($lote->precio_unitario,2,',','.') }}</td>
<td class="right">{{ number_format($lote->importe_total,2,',','.') }}</td>
</tr>
@endforeach
</tbody>
<tfoot><tr>
<td colspan="8" class="right"><strong>TOTAL (Bs)</strong></td>
<td class="right"><strong>{{ number_format($total,2,',','.') }}</strong></td>
</tr></tfoot>
</table>

<div class="footer-info">
<strong>Total literal:</strong> {{ $totalLiteral }}<br>
<strong>Observaciones:</strong> {{ $ingreso->observacion ?: 'Sin observaciones.' }}
</div>

<table class="signatures">
<tr><td><div class="line">Recibido por: {{ $ingreso->recibido_por }}</div></td></tr>
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
