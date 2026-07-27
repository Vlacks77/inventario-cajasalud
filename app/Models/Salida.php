<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salida extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicamento_id',
        'lote_id',
        'cantidad',
        'destino',
        'entregado_a',
        'fecha_salida',
        'observaciones'
    ];

    // Una salida pertenece a un medicamento
    public function medicamento() {
        return $this->belongsTo(Medicamento::class);
    }

    // Una salida descuenta de un lote específico
    public function lote() {
        return $this->belongsTo(Lote::class);
    }
}
