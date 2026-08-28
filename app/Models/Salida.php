<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Salida extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha_salida',
        'almacen_origen',
        'numero_salida',
        'numero_pedido',
        'establecimiento_id',
        'solicitado_por',
        'entregado_a',
        'observaciones',
        'estado',
        'usuario_id',
    ];

    /**
     * Conversión de fechas para que Laravel las maneje como Carbon.
     * Esto permite usar format() de forma segura en Kardex, reportes
     * y en la validación detallada del cierre mensual.
     */
    protected function casts(): array
    {
        return [
            'fecha_salida' => 'date',
        ];
    }

    /**
     * Establecimiento al que se envía la salida.
     */
    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    /**
     * Usuario que registró la salida.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Detalle de medicamentos incluidos en la salida.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleSalida::class);
    }
}
