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
        'tipo_salida',          // 'normal' | 'EGRESO_REEMBOLSO'
        'movimiento_origen_id', // FK nullable → ingresos.id (solo para EGRESO_REEMBOLSO)
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

    /**
     * Ingreso de reembolso que originó este egreso consolidado.
     * Solo aplica cuando tipo_salida = 'EGRESO_REEMBOLSO'.
     */
    public function movimientoOrigen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Ingreso::class, 'movimiento_origen_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** Excluye los egresos de reembolso (para reportes de salidas regulares). */
    public function scopeNormal($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('tipo_salida')->orWhere('tipo_salida', 'normal');
        });
    }

    /** Filtra solo egresos de reembolso. */
    public function scopeEgresoReembolso($query)
    {
        return $query->where('tipo_salida', 'EGRESO_REEMBOLSO');
    }
}
