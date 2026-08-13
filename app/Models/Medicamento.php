<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Medicamento extends Model
{
    public const TIPOS_PRODUCTO = [
        'MEDICAMENTO', 'INSUMO_MEDICO', 'PROTESIS', 'MATERIAL', 'OTRO',
    ];
    // Le decimos exactamente qué tabla usar en la base de datos
    protected $table = 'medicamentos';

    // Definimos qué campos se pueden llenar desde el sistema
    protected $fillable = [
        'codigo',
        'partida_presupuestaria_id',
        'nombre',
        'tipo_producto',
        'concentracion',
        'forma_farmaceutica',
        'unidad_presentacion',
        'stock_minimo',
        'descripcion',
        'estado'
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'stock_minimo' => 'integer',
        ];
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    public function partidaPresupuestaria(): BelongsTo
    {
        return $this->belongsTo(PartidaPresupuestaria::class);
    }
}
