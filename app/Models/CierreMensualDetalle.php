<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CierreMensualDetalle extends Model {
 protected $table='cierre_mensual_detalles';
 protected $guarded=[];
 public function cierre(): BelongsTo { return $this->belongsTo(CierreMensual::class,'cierre_mensual_id'); }
 public function medicamento(): BelongsTo { return $this->belongsTo(Medicamento::class); }
}
