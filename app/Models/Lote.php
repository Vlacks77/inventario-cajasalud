<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    protected $table = 'lotes';

    protected $fillable = [
        'medicamento_id',
        'ingreso_id',
        'proveedor_id',
        'codigo_lote',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual',
        'precio_unitario',
        'importe_total',
        'es_reembolso',   // true = lote virtual de reembolso; no disponible para dispensación regular
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad_inicial'  => 'integer',
            'cantidad_actual'   => 'integer',
            'precio_unitario'   => 'decimal:2',
            'importe_total'     => 'decimal:2',
            'es_reembolso'      => 'boolean',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filtra únicamente los lotes virtuales de reembolso.
     * Útil para la lógica interna del ReembolsoInventarioService.
     */
    public function scopeReembolso($query)
    {
        return $query->where('es_reembolso', true);
    }

    /**
     * Excluye los lotes virtuales de reembolso.
     * Aplicar en farmacia para mostrar solo lotes físicos dispensables.
     */
    public function scopeNoReembolso($query)
    {
        return $query->where('es_reembolso', false);
    }
    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(Ingreso::class);
    }

    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medicamento::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }
    public function detalleSalidas(): HasMany
    {
    return $this->hasMany(DetalleSalida::class);
    }
}
