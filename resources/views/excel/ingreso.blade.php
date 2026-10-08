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
@php
    $itemsMostrar = ($ingreso->tipo_ingreso === 'reembolso')
        ? $ingreso->lotes->groupBy('medicamento_id')->map(function($grupo) {
            $primer = $grupo->first();
            $cantTotal = (float) $grupo->sum('cantidad_inicial');
            $impTotal = (float) $grupo->sum('importe_total');
            $cpp = $cantTotal > 0 ? ($impTotal / $cantTotal) : 0;
            return (object) [
                'medicamento' => $primer->medicamento,
                'codigo_lote' => $grupo->pluck('codigo_lote')->filter()->unique()->implode(', ') ?: 'REEMBOLSO',
                'fecha_vencimiento' => $primer->fecha_vencimiento,
                'cantidad_inicial' => $cantTotal,
                'precio_unitario' => $cpp,
                'importe_total' => $impTotal,
            ];
        })
        : $ingreso->lotes;
@endphp
@foreach($itemsMostrar as $lote)
@php
    $med = $lote->medicamento;
    $descripcion = $med->nombre ?? '';
    if (!empty($med->concentracion) && !str_contains(mb_strtolower($descripcion), mb_strtolower($med->concentracion))) {
        $descripcion .= ' ' . $med->concentracion;
    }
@endphp
<tr>
<td>{{ $med->partidaPresupuestaria?->codigo ?? '—' }}</td><td>{{ $med->codigo }}</td>
<td>{{ $descripcion }}</td>
<td>{{ $med->forma_farmaceutica }} / {{ $med->unidad_presentacion }}</td>
<td>{{ $lote->codigo_lote }}</td><td>{{ $lote->fecha_vencimiento ? \Carbon\Carbon::parse($lote->fecha_vencimiento)->format('d/m/Y') : 'No aplica' }}</td>
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
