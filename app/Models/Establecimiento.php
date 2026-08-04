<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establecimiento extends Model
{
    protected $table = 'establecimientos';

    protected $fillable = [
        'nombre',
        'sigla',
        'tipo',
        'estado',
    ];

    /**
     * Salidas realizadas hacia este establecimiento.
     */
    public function salidas(): HasMany
    {
        return $this->hasMany(Salida::class);
    }
}
