@php
    echo "\xEF\xBB\xBF";
    $meses = [1=>'ENERO',2=>'FEBRERO',3=>'MARZO',4=>'ABRIL',5=>'MAYO',6=>'JUNIO',7=>'JULIO',8=>'AGOSTO',9=>'SEPTIEMBRE',10=>'OCTUBRE',11=>'NOVIEMBRE',12=>'DICIEMBRE'];
    $periodoTexto = ($meses[(int)$cierreMensual->periodo->format('n')] ?? '') . ' - ' . $cierreMensual->periodo->format('Y');
    $fechaSaldoMes = $cierreMensual->fecha_hasta->format('d/m/Y');
    $gruposPrincipales = [
        'MEDICAMENTOS','PAPEL','HILADOS, TELAS, FIBRAS Y ALGODÓN','CONFECCIONES TEXTILES','PRENDAS DE VESTIR','CALZADOS','PRODUCTOS QUIMICOS Y FARMACEUTICOS','SOLUCIONES ANTISEPTICAS','MATERIAL DE CURACION','MATERIAL DE LABORATORIO Y REACTIVOS','MATERIAL DENTAL','PLACAS RADIOGRAFICAS','PRODUCTOS DE MINERALES NO METALICOS Y PLASTICOS','INSUMOS MEDICOS','INSTRUMENTAL MENOR MEDICO QUIRURGICO','UTILES DE ESCRITORIO Y OFICINA','UTILES Y MATERIALES ELECTRICOS','OTROS REPUESTOS Y ACCESORIOS','OTROS MATERIALES Y SUMINISTROS','MATERIAL DE PROTESIS Y ORTOPEDIA'
    ];
    $subgruposLaboratorio = ['BACTERIOLOGIA','DISCOS DE ANTIBIOGRAMA','HEMATOLOGIA','REACTIVOS','SEROLOGIA','PRODUCTOS QUIMICOS - LABORATORIO','MATERIAL DE LABORATORIO'];
    $normalizar = static function($v) { $v=trim((string)$v); $v=function_exists('iconv')?iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v):$v; return strtoupper($v); };
    $clasificar = static function($d) use ($normalizar,$gruposPrincipales,$subgruposLaboratorio) { $o=$normalizar($d->grupo_producto ?? ''); foreach($gruposPrincipales as $g) if($normalizar($g)===$o) return [$g,null]; foreach($subgruposLaboratorio as $s) if($normalizar($s)===$o) return ['MATERIAL DE LABORATORIO Y REACTIVOS',$s]; return ['OTROS MATERIALES Y SUMINISTROS',null]; };
    $detallesAgrupados = collect($detalles)->groupBy(fn($d)=>$clasificar($d)[0]);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta charset="UTF-8">
<style>
table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:9pt}
th,td{border:1px solid #7f8c96;padding:4px 5px;vertical-align:middle;text-align:center;white-space:nowrap}
.title{background:#1f4663;color:#fff;font-weight:bold;text-align:center}
.t1{font-size:14pt;padding:7px}.t2{font-size:12pt;padding:6px}.t3,.t4{font-size:11pt;padding:5px}
.base{background:#d9e5ee;color:#1f2933;font-weight:bold}
.sa{background:#4f81bd;color:#fff;font-weight:bold}
.trans{background:#70ad47;color:#fff;font-weight:bold}
.compra{background:#ed7d31;color:#fff;font-weight:bold}
.total{background:#5b9bd5;color:#fff;font-weight:bold}
.egreso{background:#c55a11;color:#fff;font-weight:bold}
.saldo{background:#4472c4;color:#fff;font-weight:bold}
.cant-precio{background:#F2DCDB;color:#3f3f3f}
.importe{background:#f7f7f7;color:#3f3f3f}
.row-a{background:#fff}.row-b{background:#eef3f7}
.row-a .cant-precio,.row-b .cant-precio{background:#F2DCDB}
.row-a .importe,.row-b .importe{background:#f7f7f7}
.resumen-titulo{background:#1f4663;color:#fff;font-weight:bold}.grupo-row{background:#1f4663;color:#fff;font-weight:bold;font-size:10pt}.subgrupo-row{background:#9dc3e6;color:#17365d;font-weight:bold;font-size:9pt}.grupo-row td,.subgrupo-row td{text-align:left;padding:6px}
.resumen-head{background:#d9e5ee;font-weight:bold}
</style>
</head>
<body>
<table>
<tr><th class="title t1" colspan="21">MOVIMIENTO MENSUAL FISICO VALORADO</th></tr>
<tr><th class="title t2" colspan="21">ALMACEN DE MEDICAMENTOS E INSUMOS MEDICOS</th></tr>
<tr><th class="title t3" colspan="21">{{ strtoupper($cierreMensual->almacen ?: 'REGIONAL LA PAZ') }}</th></tr>
<tr><th class="title t4" colspan="21">{{ $periodoTexto }}</th></tr>
<tr>
<th class="base" rowspan="2">Partida</th><th class="base" rowspan="2">Código</th><th class="base" rowspan="2">Descripción</th><th class="base" rowspan="2">Forma</th>
<th class="sa" colspan="3">SALDO ANTERIOR</th>
<th class="trans" colspan="3">INGRESOS TRANS. ENTRE REGIONALES</th>
<th class="compra" colspan="3">INGRESOS COMPRAS LOCALES</th>
<th class="total" colspan="3">TOTAL INGRESOS REGIONAL</th>
<th class="egreso" colspan="2">EGRESOS REGIONAL</th>
<th class="saldo" colspan="3">SALDO DEL MES AL {{ $fechaSaldoMes }}</th>
</tr>
<tr>
<th class="cant-precio">SA Cant.</th><th class="cant-precio">SA P.Unit.</th><th class="importe">SA Importe</th>
<th class="cant-precio">Transf. Cant.</th><th class="cant-precio">Transf. P.Unit.</th><th class="importe">Transf. Importe</th>
<th class="cant-precio">Compra Cant.</th><th class="cant-precio">Compra P.Unit.</th><th class="importe">Compra Importe</th>
<th class="cant-precio">Total Ing. Cant.</th><th class="cant-precio">Total Ing. P.Unit.</th><th class="importe">Total Ing. Importe</th>
<th class="cant-precio">Egreso Cant.</th><th class="importe">Egreso Importe</th>
<th class="cant-precio">Saldo Cant.</th><th class="cant-precio">Saldo P.Unit.</th><th class="importe">Saldo Importe</th>
</tr>
@php($fila=0)
@foreach($gruposPrincipales as $numero=>$grupo)
    @php($itemsGrupo=$detallesAgrupados->get($grupo, collect()))
    @if($itemsGrupo->isNotEmpty())
    <tr class="grupo-row"><td colspan="21">{{ $numero+1 }}. {{ $grupo }}</td></tr>
    @if($grupo === 'MATERIAL DE LABORATORIO Y REACTIVOS')
        @foreach($subgruposLaboratorio as $subnumero=>$subgrupo)
            @php($itemsSub=$itemsGrupo->filter(fn($d)=>$clasificar($d)[1] === $subgrupo)->sortBy(fn($d)=>strtoupper((string)$d->descripcion), SORT_NATURAL|SORT_FLAG_CASE))
            @if($itemsSub->isNotEmpty())
            <tr class="subgrupo-row"><td colspan="21">10.{{ $subnumero+1 }}. {{ $subgrupo }}</td></tr>
            @foreach($itemsSub as $d)
                @include('excel.partials.cierre-mensual-row',['d'=>$d,'i'=>$fila]) @php($fila++)
            @endforeach
            @endif
        @endforeach
        @php($itemsSinSub=$itemsGrupo->filter(fn($d)=>$clasificar($d)[1] === null)->sortBy(fn($d)=>strtoupper((string)$d->descripcion), SORT_NATURAL|SORT_FLAG_CASE))
        @foreach($itemsSinSub as $d)
            @include('excel.partials.cierre-mensual-row',['d'=>$d,'i'=>$fila]) @php($fila++)
        @endforeach
    @else
        @foreach($itemsGrupo->sortBy(fn($d)=>strtoupper((string)$d->descripcion), SORT_NATURAL|SORT_FLAG_CASE) as $d)
            @include('excel.partials.cierre-mensual-row',['d'=>$d,'i'=>$fila]) @php($fila++)
        @endforeach
    @endif
    @endif
@endforeach
</table>
<br>
<table>
<tr><th class="resumen-titulo" colspan="7">RESUMEN POR GRUPO</th></tr>
<tr><th class="resumen-head">Grupo</th><th class="sa">Saldo anterior</th><th class="trans">Transferencias</th><th class="compra">Compras locales</th><th class="total">Total ingresos</th><th class="egreso">Egresos</th><th class="saldo">Saldo mes</th></tr>
@foreach($resumen as $i=>$r)
<tr class="{{ $i % 2 ? 'row-b' : 'row-a' }}"><td style="text-align:left">{{ $r['grupo'] }}</td><td>{{ number_format((float)$r['saldo_anterior_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['transferencia_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['compra_local_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['total_ingresos_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['egreso_importe'],2,'.','') }}</td><td>{{ number_format((float)$r['saldo_mes_importe'],2,'.','') }}</td></tr>
@endforeach
</table>
</body>
</html>
