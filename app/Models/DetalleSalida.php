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
        'precio_unitario_reembolso',  // CPP calculado en consolidación; null en salidas normales
    ];

    protected function casts(): array
    {
        return [
            'cantidad'                    => 'integer',
            'precio_unitario_reembolso'   => 'decimal:6',
        ];
    }

    /**
     * Precio unitario efectivo para el Kardex:
     *  - En egresos de reembolso devuelve el CPP calculado.
     *  - En salidas normales devuelve el precio_unitario del lote asociado.
     */
    public function getPrecioUnitarioEfectivoAttribute(): float
    {
        if ($this->precio_unitario_reembolso !== null) {
            return (float) $this->precio_unitario_reembolso;
        }

        return (float) ($this->lote?->precio_unitario ?? 0);
    }

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
