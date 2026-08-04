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
        'proveedor_id',
        'codigo_lote',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual'
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad_inicial' => 'integer',
            'cantidad_actual' => 'integer',
        ];
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
