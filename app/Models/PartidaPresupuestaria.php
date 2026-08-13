<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartidaPresupuestaria extends Model
{
    protected $table = 'partidas_presupuestarias';
    protected $fillable = ['codigo', 'nombre', 'estado'];

    public function productos(): HasMany
    {
        return $this->hasMany(Medicamento::class, 'partida_presupuestaria_id');
    }
}
