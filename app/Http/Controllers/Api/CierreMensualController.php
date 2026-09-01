<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CierreMensual;
use App\Models\DetalleSalida;
use App\Models\Lote;
use App\Models\Medicamento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ClasificadorInventarioService;

class CierreMensualController extends Controller
{
    private ClasificadorInventarioService $clasificador;

    public function __construct(ClasificadorInventarioService $clasificador)
    {
        $this->clasificador = $clasificador;
    }

    public function index() {
        return CierreMensual::with('usuario:id,name')->latest('periodo')->get()->map(fn($c)=>[
            'id'=>$c->id,'periodo'=>$c->periodo->format('Y-m-d'),'fecha_desde'=>$c->fecha_desde->format('Y-m-d'),'fecha_hasta'=>$c->fecha_hasta->format('Y-m-d'),
            'almacen'=>$c->almacen,'estado'=>$c->estado,'total_items'=>$c->total_items,'importe_saldo_mes'=>$c->importe_saldo_mes,'cerrado_en'=>$c->cerrado_en?->format('Y-m-d H:i'),'usuario'=>$c->usuario?->name
        ]);
    }

    public function preview(Request $request) {
        $data=$request->validate(['periodo'=>['required','date_format:Y-m'],'almacen'=>['nullable','string','max:150']]);
        $calc=$this->calcular($data['periodo'], $data['almacen'] ?? 'REGIONAL LA PAZ');
        return response()->json([
            'periodo'=>$calc['periodo']->format('Y-m-d'),
            'fecha_desde'=>$calc['desde']->format('Y-m-d'),
            'fecha_hasta'=>$calc['hasta']->format('Y-m-d'),
            'total_items'=>count($calc['detalles']),
            'totales'=>$calc['totales'],
            'resumen_grupos'=>$this->resumenGrupos($calc['detalles']),
            // Se envían todos los ítems para reproducir en pantalla el movimiento
            // mensual físico valorado, igual que la planilla institucional.
            'detalles'=>$calc['detalles'],
        ]);
    }

    public function store(Request $request) {
        $data=$request->validate(['periodo'=>['required','date_format:Y-m'],'almacen'=>['nullable','string','max:150'],'observacion'=>['nullable','string']]);
        $periodo=Carbon::createFromFormat('Y-m',$data['periodo'])->startOfMonth()->toDateString();
        if (CierreMensual::whereDate('periodo',$periodo)->exists()) return response()->json(['message'=>'Este mes ya fue cerrado y se conserva como documento histórico.'],422);
        $calc=$this->calcular($data['periodo'],$data['almacen'] ?? 'REGIONAL LA PAZ');
        $cierre=DB::transaction(function() use($calc,$data,$request){
            $t=$calc['totales'];
            $c=CierreMensual::create([
                'almacen'=>$data['almacen'] ?? 'REGIONAL LA PAZ','periodo'=>$calc['periodo'],'fecha_desde'=>$calc['desde'],'fecha_hasta'=>$calc['hasta'],'usuario_id'=>$request->user()->id,
                'estado'=>'CERRADO','total_items'=>count($calc['detalles']),'importe_saldo_anterior'=>$t['saldo_anterior_importe'],'importe_ingresos_transferencia'=>$t['transferencia_importe'],'importe_ingresos_compra_local'=>$t['compra_local_importe'],'importe_total_ingresos'=>$t['total_ingresos_importe'],'importe_egresos'=>$t['egreso_importe'],'importe_saldo_mes'=>$t['saldo_mes_importe'],'observacion'=>$data['observacion'] ?? null,'cerrado_en'=>now()
            ]);
            foreach(array_chunk($calc['detalles'],500) as $chunk) {
                foreach($chunk as &$d){$d['cierre_mensual_id']=$c->id;$d['created_at']=now();$d['updated_at']=now();}
                DB::table('cierre_mensual_detalles')->insert($chunk);
            }
            return $c;
        });
        return response()->json(['message'=>'Cierre mensual registrado correctamente. El resultado queda congelado para trazabilidad.','cierre'=>$cierre],201);
    }

    public function show(CierreMensual $cierreMensual) {
        // Se recuperan TODOS los detalles congelados del cierre. La organización
        // visual por grupos y subgrupos se realiza en el frontend con el mismo
        // catálogo usado por el movimiento mensual.
        $det=$cierreMensual->detalles()->orderBy('codigo')->orderBy('descripcion')->get();

        return response()->json([
            'cierre'=>[
                'id'=>$cierreMensual->id,
                'periodo'=>$cierreMensual->periodo?->format('Y-m-d'),
                'fecha_desde'=>$cierreMensual->fecha_desde?->format('Y-m-d'),
                'fecha_hasta'=>$cierreMensual->fecha_hasta?->format('Y-m-d'),
                'almacen'=>$cierreMensual->almacen,
                'estado'=>$cierreMensual->estado,
                'total_items'=>$cierreMensual->total_items,
                'cerrado_en'=>$cierreMensual->cerrado_en?->format('Y-m-d H:i:s'),
            ],
            'detalles'=>$det,
            'resumen_grupos'=>$this->resumenGrupos($det->all())
        ]);
    }

    /**
     * Genera un PDF del cierre histórico respetando los filtros enviados
     * desde la vista. Por seguridad de rendimiento, el PDF no permite
     * descargar miles de filas sin filtrar: para el cierre completo se usa
     * Excel, o se puede activar "Solo con stock".
     */
    public function pdf(Request $request, CierreMensual $cierreMensual) {
        @set_time_limit(300);

        $data = $request->validate([
            'ids' => ['nullable','array','max:4493'],
            'ids.*' => ['integer'],
            'stock_only' => ['nullable','boolean'],
        ]);

        $ids = $data['ids'] ?? null;
        $stockOnly = (bool)($data['stock_only'] ?? false);

        // Un PDF sin selección explícita puede contener miles de filas y
        // provocar que DomPDF consuma demasiada memoria. Obligar a usar
        // filtros para PDF hace que Excel quede como formato de respaldo
        // para el inventario completo.
        if ($ids === null && !$stockOnly) {
            return response()->json([
                'message' => 'Para generar PDF seleccione un filtro o active "Solo con stock". Para el inventario completo use EXCEL.'
            ], 422);
        }

        $detalles = $cierreMensual->detalles()
            ->when($ids !== null, fn($q) => $q->whereIn('id', $ids))
            ->when($stockOnly, fn($q) => $q->where('saldo_mes_cantidad','>',0))
            ->orderBy('codigo')
            ->orderBy('descripcion')
            ->get();

        // Cuando la vista envía IDs, conserva exactamente el orden que el
        // usuario estaba viendo después de aplicar sus filtros.
        if ($ids !== null) {
            $posiciones = array_flip(array_map('intval', $ids));
            $detalles = $detalles
                ->sortBy(fn($d) => $posiciones[(int)$d->id] ?? PHP_INT_MAX)
                ->values();
        }

        if ($detalles->isEmpty()) {
            return response()->json(['message'=>'No hay ítems que coincidan con los filtros seleccionados.'],422);
        }

        // DomPDF puede consumir demasiada memoria con miles de filas.
        // El PDF queda destinado a vistas filtradas; Excel es el formato
        // recomendado para el cierre completo.
        if ($detalles->count() > 1000) {
            return response()->json([
                'message' => 'La selección contiene '.$detalles->count().' ítems. Para PDF filtre hasta 1000 ítems; para el inventario completo utilice EXCEL completo.'
            ], 422);
        }

        $cierreMensual->load('usuario');
        $resumen=$this->resumenGrupos($detalles->all());

        $pdf=Pdf::setOptions([
            'isHtml5ParserEnabled'=>true,
            'isRemoteEnabled'=>false,
            'dpi'=>72,
            'defaultFont'=>'DejaVu Sans',
        ])->loadView('pdf.cierre-mensual',compact('cierreMensual','detalles','resumen'))
          ->setPaper('a3','landscape');

        $sufijo = $stockOnly ? '-solo-stock' : '-filtrado';
        return $pdf->download('cierre-mensual-'.$cierreMensual->periodo->format('Y-m').$sufijo.'.pdf');
    }

    /**
     * Exporta el cierre completo o la selección actual a Excel.
     * Excel se mantiene como formato recomendado para los 4493 ítems.
     */
    public function excel(Request $request, CierreMensual $cierreMensual) {
        $data = $request->validate([
            'ids' => ['nullable','array','max:4493'],
            'ids.*' => ['integer'],
            'stock_only' => ['nullable','boolean'],
        ]);

        $ids = $data['ids'] ?? null;

        $detalles=$cierreMensual->detalles()
            ->when($ids !== null, fn($q) => $q->whereIn('id', $ids))
            ->when((bool)($data['stock_only'] ?? false), fn($q) => $q->where('saldo_mes_cantidad','>',0))
            ->orderBy('partida_codigo')
            ->orderBy('descripcion')
            ->get();

        if ($ids !== null) {
            $posiciones = array_flip(array_map('intval', $ids));
            $detalles = $detalles
                ->sortBy(fn($d) => $posiciones[(int)$d->id] ?? PHP_INT_MAX)
                ->values();
        }

        if ($detalles->isEmpty()) {
            return response()->json(['message'=>'No hay ítems que coincidan con los filtros seleccionados.'],422);
        }

        $cierreMensual->load('usuario');
        $resumen=$this->resumenGrupos($detalles->all());
        $sufijo=$ids !== null ? '-filtrado' : '-completo';

        return response(
            view('excel.cierre-mensual',compact('cierreMensual','detalles','resumen'))->render(),
            200,
            [
                'Content-Type'=>'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition'=>'attachment; filename="cierre-mensual-'.$cierreMensual->periodo->format('Y-m').$sufijo.'.xls"'
            ]
        );
    }

    /**
     * Fase 2: desglose auditable de un producto antes de cerrar el mes.
     * No guarda ni modifica inventario; reutiliza exactamente el mismo motor
     * de cálculo de la previsualización general.
     */
    public function productoPreview(Request $request, Medicamento $medicamento)
    {
        $data = $request->validate([
            'periodo' => ['required', 'date_format:Y-m'],
            'almacen' => ['nullable', 'string', 'max:150'],
        ]);

        $calc = $this->calcular($data['periodo'], $data['almacen'] ?? 'REGIONAL LA PAZ');

        $detalle = collect($calc['detalles'])
            ->firstWhere('medicamento_id', $medicamento->id);

        if (!$detalle) {
            return response()->json(['message' => 'El producto no está disponible para el cierre solicitado.'], 404);
        }

        $desde = $calc['desde'];
        $hasta = $calc['hasta'];

        $lotes = Lote::with([
                'ingreso:id,fecha_ingreso,tipo_ingreso,numero_nota,numero_remision,almacen',
                'proveedor:id,nombre',
            ])
            ->where('medicamento_id', $medicamento->id)
            ->orderBy('id')
            ->get();

        $ingresos = $lotes
            ->filter(function ($lote) use ($desde, $hasta) {
                if (!$lote->ingreso?->fecha_ingreso) return false;
                return Carbon::parse($lote->ingreso->fecha_ingreso)
                    ->betweenIncluded($desde, $hasta);
            })
            ->map(function ($lote) {
                $tipo = in_array($lote->ingreso->tipo_ingreso, ['transferencia', 'transferencia_regional'], true)
                    ? 'Transferencia entre regionales'
                    : 'Compra local / otro ingreso';

                return [
                    'fecha' => $lote->ingreso->fecha_ingreso?->format('Y-m-d'),
                    'tipo' => $tipo,
                    'numero_nota' => $lote->ingreso->numero_nota,
                    'numero_remision' => $lote->ingreso->numero_remision,
                    'lote' => $lote->codigo_lote,
                    'proveedor' => $lote->proveedor?->nombre,
                    'cantidad' => (float) $lote->cantidad_inicial,
                    'precio_unitario' => (float) $lote->precio_unitario,
                    'importe' => (float) $lote->importe_total,
                ];
            })->values();

        $egresos = DetalleSalida::with([
                'salida:id,fecha_salida,numero_salida,numero_pedido,establecimiento_id,estado',
                'salida.establecimiento:id,nombre',
                'lote:id,medicamento_id,codigo_lote,precio_unitario',
            ])
            ->whereHas('salida', function ($q) use ($desde, $hasta) {
                $q->where('estado', 'ACTIVA')
                    ->whereBetween('fecha_salida', [$desde->toDateString(), $hasta->toDateString()]);
            })
            ->whereIn('lote_id', $lotes->pluck('id'))
            ->orderBy('id')
            ->get()
            ->map(function ($salida) {
                $precio = (float) ($salida->lote?->precio_unitario ?? 0);

                return [
                    'fecha' => $salida->salida?->fecha_salida?->format('Y-m-d'),
                    'numero_salida' => $salida->salida?->numero_salida,
                    'numero_pedido' => $salida->salida?->numero_pedido,
                    'destino' => $salida->salida?->establecimiento?->nombre,
                    'lote' => $salida->lote?->codigo_lote,
                    'cantidad' => (float) $salida->cantidad,
                    'precio_unitario' => $precio,
                    'importe' => (float) $salida->cantidad * $precio,
                ];
            })->values();

        $stockActualCantidad = (float) $lotes->sum('cantidad_actual');
        $stockActualImporte = (float) $lotes->sum(function ($lote) {
            return (float) $lote->cantidad_actual * (float) $lote->precio_unitario;
        });

        return response()->json([
            'periodo' => $calc['periodo']->format('Y-m-d'),
            'producto' => [
                'id' => $medicamento->id,
                'codigo' => $medicamento->codigo,
                'nombre' => $this->nombreProducto($medicamento),
                'forma_farmaceutica' => $medicamento->forma_farmaceutica,
                'grupo_producto' => $medicamento->grupo_producto,
            ],
            'calculo' => $detalle,
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'stock_actual' => [
                'cantidad' => $stockActualCantidad,
                'importe_estimado' => $stockActualImporte,
            ],
            'nota' => 'La comparación con el stock actual es informativa. Puede diferir del saldo del cierre si existen movimientos posteriores al último día del periodo.',
        ]);
    }

    private function calcular(string $periodo,string $almacen): array {
        $desde=Carbon::createFromFormat('Y-m',$periodo)->startOfMonth(); $hasta=$desde->copy()->endOfMonth(); $prev=$desde->copy()->subMonth()->startOfMonth();
        $anterior=CierreMensual::whereDate('periodo',$prev->toDateString())->first();
        $prevDetalles=$anterior ? $anterior->detalles()->get()->keyBy('medicamento_id') : collect();
        $productos=Medicamento::with('partidaPresupuestaria:id,codigo')->where('estado',true)->orderBy('partida_presupuestaria_id')->orderBy('codigo')->orderBy('nombre')->get();
        $lotes=Lote::with('ingreso:id,fecha_ingreso,tipo_ingreso')->whereHas('ingreso')->get()->groupBy('medicamento_id');
        $salidas=DetalleSalida::with('salida:id,fecha_salida,estado')->whereHas('salida',fn($q)=>$q->where('estado','ACTIVA'))->get()->groupBy(fn($d)=>$d->lote?->medicamento_id);
        // detalleSalidas no carga lote si no se pide: relacionamos por lote_id mediante mapa
        $loteToMed=Lote::pluck('medicamento_id','id');
        $salidas=DetalleSalida::with('salida:id,fecha_salida,estado')->whereHas('salida',fn($q)=>$q->where('estado','ACTIVA'))->get()->groupBy(fn($d)=>$loteToMed[$d->lote_id] ?? 0);
        $detalles=[];
        foreach($productos as $p){
            $movLotes=$lotes->get($p->id,collect());$movSalidas=$salidas->get($p->id,collect());
            if($anterior && $prevDetalles->has($p->id)){$pd=$prevDetalles[$p->id];$saq=(float)$pd->saldo_mes_cantidad;$sai=(float)$pd->saldo_mes_importe;}
            else {
                $saq=0;$sai=0;
                foreach($movLotes as $l){$f=$l->ingreso?->fecha_ingreso;if($f && Carbon::parse($f)->lt($desde)){$saq+=(float)$l->cantidad_inicial;$sai+=(float)$l->importe_total;}}
                foreach($movSalidas as $s){$f=$s->salida?->fecha_salida;if($f && Carbon::parse($f)->lt($desde)){$q=(float)$s->cantidad;$precio=(float)optional($movLotes->firstWhere('id',$s->lote_id))->precio_unitario;$saq-=$q;$sai-=$q*$precio;}}
            }
            $trq=$tri=$clq=$cli=0;
            foreach($movLotes as $l){$f=$l->ingreso?->fecha_ingreso;if(!$f||!Carbon::parse($f)->betweenIncluded($desde,$hasta))continue;$q=(float)$l->cantidad_inicial;$imp=(float)$l->importe_total;if(in_array($l->ingreso->tipo_ingreso, ['transferencia', 'transferencia_regional'], true)){$trq+=$q;$tri+=$imp;}else{$clq+=$q;$cli+=$imp;}}
            $eq=$ei=0;
            foreach($movSalidas as $s){$f=$s->salida?->fecha_salida;if(!$f||!Carbon::parse($f)->betweenIncluded($desde,$hasta))continue;$q=(float)$s->cantidad;$precio=(float)optional($movLotes->firstWhere('id',$s->lote_id))->precio_unitario;$eq+=$q;$ei+=$q*$precio;}
            $tiq=$trq+$clq;$tii=$tri+$cli;$smq=$saq+$tiq-$eq;$smi=$sai+$tii-$ei;
            // Protección contra pequeños negativos de redondeo o datos históricos incompletos.
            $smi=max(0,$smi);$smq=max(0,$smq);
            $detalles[]=['medicamento_id'=>$p->id,'partida_codigo'=>$p->partidaPresupuestaria?->codigo,'codigo'=>$p->codigo,'descripcion'=>$this->nombreProducto($p),'forma_farmaceutica'=>$p->forma_farmaceutica,'grupo_producto'=>$p->grupo_producto,
              'saldo_anterior_cantidad'=>$saq,'saldo_anterior_precio'=>$saq?round($sai/$saq,6):0,'saldo_anterior_importe'=>$sai,'transferencia_cantidad'=>$trq,'transferencia_precio'=>$trq?round($tri/$trq,6):0,'transferencia_importe'=>$tri,'compra_local_cantidad'=>$clq,'compra_local_precio'=>$clq?round($cli/$clq,6):0,'compra_local_importe'=>$cli,'total_ingresos_cantidad'=>$tiq,'total_ingresos_precio'=>$tiq?round($tii/$tiq,6):0,'total_ingresos_importe'=>$tii,'egreso_cantidad'=>$eq,'egreso_importe'=>$ei,'saldo_mes_cantidad'=>$smq,'saldo_mes_precio'=>$smq?round($smi/$smq,6):0,'saldo_mes_importe'=>$smi];
        }
        $totales=[];
        foreach(['saldo_anterior_importe','transferencia_importe','compra_local_importe','total_ingresos_importe','egreso_importe','saldo_mes_importe'] as $k) {
            $totales[$k]=array_sum(array_column($detalles,$k));
        }
        return compact('detalles','totales')+['periodo'=>$desde,'desde'=>$desde,'hasta'=>$hasta];
    }

    /**
     * El catálogo actual ya incorpora la concentración dentro del nombre del
     * producto. Solo se añade la concentración si en el futuro llega separada
     * y todavía no forma parte del texto, evitando duplicados como:
     * "Cloroquina fosfato 250 mg ... 250 mg ...".
     */
    private function nombreProducto(Medicamento $medicamento): string
    {
        $nombre = trim((string) $medicamento->nombre);
        $concentracion = trim((string) ($medicamento->concentracion ?? ''));

        if ($concentracion === '') {
            return $nombre;
        }

        $normalizar = static function (string $texto): string {
            $texto = mb_strtolower($texto, 'UTF-8');
            $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
            return preg_replace('/\\s+/', ' ', trim($texto));
        };

        return str_contains($normalizar($nombre), $normalizar($concentracion))
            ? $nombre
            : trim($nombre . ' ' . $concentracion);
    }

    private function resumenGrupos(iterable $detalles): array
    {
        $out = [];

        foreach ($detalles as $d) {
            $a = is_array($d) ? $d : $d->toArray();
            $clasificacion = $this->clasificador->clasificar(
                $a['codigo'] ?? null,
                $a['grupo_producto'] ?? null
            );

            $grupo = $clasificacion['grupo'] ?? 'SIN CLASIFICACIÓN';

            if (!isset($out[$grupo])) {
                $out[$grupo] = [
                    'grupo' => $grupo,
                    'saldo_anterior_importe' => 0,
                    'transferencia_importe' => 0,
                    'compra_local_importe' => 0,
                    'total_ingresos_importe' => 0,
                    'egreso_importe' => 0,
                    'saldo_mes_importe' => 0,
                ];
            }

            foreach (array_keys($out[$grupo]) as $k) {
                if (str_ends_with($k, 'importe')) {
                    $out[$grupo][$k] += (float) ($a[$k] ?? 0);
                }
            }
        }

        // Mantiene el orden institucional de los 20 grupos y deja los
        // productos sin clasificación al final, sin ocultarlos.
        $orden = array_flip($this->clasificador->grupos());
        uksort($out, static function ($a, $b) use ($orden) {
            $pa = $orden[$a] ?? PHP_INT_MAX;
            $pb = $orden[$b] ?? PHP_INT_MAX;
            return $pa <=> $pb ?: strcasecmp($a, $b);
        });

        return array_values($out);
    }
}
