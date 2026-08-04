<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleSalida extends Model
{
    protected $table = 'detalle_salidas';

    protected $fillable = [
        'salida_id',
        'lote_id',
        'cantidad',
    ];

    /**
     * Cabecera de la salida.
     */
    public function salida(): BelongsTo
    {
        return $this->belongsTo(Salida::class);
    }

    /**
     * Lote entregado.
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
