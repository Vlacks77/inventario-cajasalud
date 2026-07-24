<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Medicamento extends Model
{
    // Le decimos exactamente qué tabla usar en la base de datos
    protected $table = 'medicamentos';

    // Definimos qué campos se pueden llenar desde el sistema
    protected $fillable = [
        'codigo',
        'nombre',
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
}
