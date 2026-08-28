<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CierreMensual extends Model {
 protected $table='cierres_mensuales';
 protected $fillable=['almacen','periodo','fecha_desde','fecha_hasta','usuario_id','estado','total_items','importe_saldo_anterior','importe_ingresos_transferencia','importe_ingresos_compra_local','importe_total_ingresos','importe_egresos','importe_saldo_mes','observacion','cerrado_en'];
 protected function casts(): array { return ['periodo'=>'date','fecha_desde'=>'date','fecha_hasta'=>'date','cerrado_en'=>'datetime']; }
 public function detalles(): HasMany { return $this->hasMany(CierreMensualDetalle::class); }
 public function usuario(): BelongsTo { return $this->belongsTo(User::class); }
}
