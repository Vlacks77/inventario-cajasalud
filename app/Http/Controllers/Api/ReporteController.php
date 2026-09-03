<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingreso;
use App\Models\Salida;
use App\Models\Medicamento;
use App\Models\Lote;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteController extends Controller
{
    public function ingresos(Request $request) {
        $q = trim($request->query('buscar',''));
        return Ingreso::with('proveedor')->when($q !== '', function($query) use($q){
            $query->where(function($x) use($q){ $x->where('numero_nota','like',"%$q%")->orWhere('numero_remision','like',"%$q%"); });
        })->latest('fecha_ingreso')->get()->map(fn($i)=>['id'=>$i->id,'numero_nota'=>$i->numero_nota,'numero_remision'=>$i->numero_remision,'fecha'=>$i->fecha_ingreso?->format('Y-m-d'),'proveedor'=>$i->proveedor?->nombre]);
    }
    public function salidas(Request $request) {
        $q=trim($request->query('buscar',''));
        return Salida::with('establecimiento')->when($q !== '', function($query) use($q){$query->where(function($x) use($q){$x->where('numero_salida','like',"%$q%")->orWhere('numero_pedido','like',"%$q%");});})->latest('fecha_salida')->limit(100)->get()->map(fn($s)=>['id'=>$s->id,'numero_salida'=>$s->numero_salida,'numero_pedido'=>$s->numero_pedido,'fecha'=>$s->fecha_salida,'destino'=>$s->establecimiento?->nombre]);
    }
    public function ingresoExcel(Ingreso $ingreso) {
        $ingreso->load(['proveedor', 'lotes.medicamento.partidaPresupuestaria']);
        $total = $ingreso->lotes->sum('importe_total');
        $numero = str_replace(['N.º ', ' '], ['', '-'], $ingreso->numero_nota);
        return $this->excel(
            'Nota de ingreso '.$ingreso->numero_nota,
            view('excel.ingreso', compact('ingreso','total'))->render(),
            'nota-ingreso-'.$numero.'.xls'
        );
    }

    public function ingresoPdf(Ingreso $ingreso) {
        $ingreso->load(['proveedor', 'lotes.medicamento.partidaPresupuestaria']);
        $total = $ingreso->lotes->sum('importe_total');

        $pdf = Pdf::loadView('pdf.nota-ingreso', [
            'ingreso' => $ingreso,
            'total' => $total,
            'totalLiteral' => $this->montoEnLetras((float) $total),
        ])->setPaper('letter', 'portrait');

        $contenido = $pdf->output();
        $nombre = 'nota-ingreso-'.str_replace(['N.º ', ' '], ['', '-'], $ingreso->numero_nota).'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function salidaPdf(Salida $salida) {
        $salida->load(['establecimiento','usuario','detalles.lote.medicamento.partidaPresupuestaria']);
        $total = $salida->detalles->sum(fn($d) => (float) $d->cantidad * (float) ($d->lote->precio_unitario ?? 0));

        $pdf = Pdf::loadView('pdf.nota-salida', compact('salida', 'total'))->setPaper('letter','portrait');
        $contenido = $pdf->output();
        $nombre = 'nota-salida-'.$salida->numero_salida.'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Content-Length' => (string) strlen($contenido),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
    public function salidaExcel(Salida $salida) { $salida->load(['establecimiento','usuario','detalles.lote.medicamento.partidaPresupuestaria']); $total = $salida->detalles->sum(fn($d) => (float) $d->cantidad * (float) ($d->lote->precio_unitario ?? 0)); return $this->excel('Reporte de salida '.$salida->numero_salida, view('excel.salida',compact('salida','total'))->render(), 'nota-salida-'.$salida->numero_salida.'.xls'); }
    public function inventario(Request $request) {
        $data=$this->inventarioData($request); return Pdf::loadView('pdf.inventario',['productos'=>$data,'filtros'=>$request->all(),'fecha'=>now()])->setPaper('letter','portrait')->download('reporte-inventario-'.now()->format('Ymd-His').'.pdf', ['Content-Type' => 'application/pdf']);
    }
    public function inventarioExcel(Request $request) { $data=$this->inventarioData($request); return $this->excel('Inventario de almacén',view('excel.inventario',['productos'=>$data,'fecha'=>now()])->render(),'reporte-inventario-'.now()->format('Ymd-His').'.xls'); }
    private function inventarioData(Request $r) {
        $buscar=trim($r->query('buscar','')); $partida=trim($r->query('partida','')); $grupo=trim($r->query('grupo','')); $ids=array_filter(array_map('intval', explode(',', (string)$r->query('producto_ids',''))));
        return Medicamento::with(['partidaPresupuestaria','lotes'=>fn($q)=>$q->with('proveedor')->where('cantidad_actual','>',0)])->where('estado',true)
          ->when(!empty($ids),fn($q)=>$q->whereIn('id',$ids))
          ->when(empty($ids),fn($q)=>$q->whereHas('lotes',fn($x)=>$x->where('cantidad_actual','>',0)))
          ->when(empty($ids) && $buscar!=='',fn($q)=>$q->where(fn($x)=>$x->where('codigo','like',"%$buscar%")->orWhere('nombre','like',"%$buscar%")))
          ->when($partida!=='',fn($q)=>$q->whereHas('partidaPresupuestaria',fn($x)=>$x->where('codigo',$partida)))
          ->when($grupo!=='',fn($q)=>$q->where('grupo_producto',$grupo))->orderBy('nombre')->get()->map(function($p){$p->stock_total=$p->lotes->sum('cantidad_actual');$p->valor_total=$p->lotes->sum(fn($l)=>$l->cantidad_actual*(float)$l->precio_unitario);return $p;});
    }
    public function kardex(Request $request) {
        $productoId=$request->query('producto_id'); abort_unless($productoId,422,'Seleccione un producto');
        $producto=Medicamento::findOrFail($productoId); $desde=$request->query('desde');$hasta=$request->query('hasta');
        $lotes=Lote::with(['ingreso.proveedor','ingreso.usuario','detalleSalidas.salida.establecimiento','detalleSalidas.salida.usuario'])->where('medicamento_id',$productoId)->get(); $mov=[];
        foreach($lotes as $l){ if($l->ingreso && (!$desde || $l->ingreso->fecha_ingreso >= $desde) && (!$hasta || $l->ingreso->fecha_ingreso <= $hasta)) $mov[]=['fecha'=>$l->ingreso->fecha_ingreso,'tipo'=>'INGRESO','referencia'=>$l->ingreso->numero_nota,'lote'=>$l->codigo_lote,'entrada'=>$l->cantidad_inicial,'salida'=>0,'usuario'=>$l->ingreso->usuario?->name ?? 'Sin trazabilidad']; foreach($l->detalleSalidas as $d){$s=$d->salida;if($s && (!$desde||$s->fecha_salida >= $desde)&&(!$hasta||$s->fecha_salida <=$hasta))$mov[]=['fecha'=>$s->fecha_salida,'tipo'=>'SALIDA','referencia'=>'Salida N.º '.$s->numero_salida,'lote'=>$l->codigo_lote,'entrada'=>0,'salida'=>$d->cantidad,'usuario'=>$s->usuario?->name ?? 'Sin trazabilidad'];}}
        usort($mov,fn($a,$b)=>strcmp($a['fecha'],$b['fecha'])); $stock=0; foreach($mov as &$m){$stock += $m['entrada']-$m['salida'];$m['stock']=$stock;} return Pdf::loadView('pdf.kardex',compact('producto','mov','desde','hasta'))->setPaper('letter','portrait')->download('kardex-'.$producto->codigo.'.pdf', ['Content-Type' => 'application/pdf']);
    }
    private function montoEnLetras(float $monto): string
    {
        $enteros = (int) floor($monto);
        $centavos = (int) round(($monto - $enteros) * 100);
        return strtoupper($this->numeroEnLetras($enteros).' BOLIVIANOS CON '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100');
    }

    private function numeroEnLetras(int $numero): string
    {
        $unidades = ['cero','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve','diez','once','doce','trece','catorce','quince','dieciséis','diecisiete','dieciocho','diecinueve','veinte'];
        if ($numero <= 20) return $unidades[$numero];
        $decenas = [2=>'veinte',3=>'treinta',4=>'cuarenta',5=>'cincuenta',6=>'sesenta',7=>'setenta',8=>'ochenta',9=>'noventa'];
        if ($numero < 30) return 'veinti'.$unidades[$numero - 20];
        if ($numero < 100) return $decenas[intdiv($numero,10)].($numero % 10 ? ' y '.$unidades[$numero % 10] : '');
        $centenas = [1=>'ciento',2=>'doscientos',3=>'trescientos',4=>'cuatrocientos',5=>'quinientos',6=>'seiscientos',7=>'setecientos',8=>'ochocientos',9=>'novecientos'];
        if ($numero === 100) return 'cien';
        if ($numero < 1000) return $centenas[intdiv($numero,100)].($numero % 100 ? ' '.$this->numeroEnLetras($numero % 100) : '');
        if ($numero < 1000000) return ($numero < 2000 ? 'mil' : $this->numeroEnLetras(intdiv($numero,1000)).' mil').($numero % 1000 ? ' '.$this->numeroEnLetras($numero % 1000) : '');
        return $numero === 1000000 ? 'un millón' : (string) $numero;
    }

    private function excel($title,$html,$name){return response("<html><head><meta charset='UTF-8'></head><body><h2>$title</h2>$html</body></html>",200,['Content-Type'=>'application/vnd.ms-excel; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$name.'"']);}
}
