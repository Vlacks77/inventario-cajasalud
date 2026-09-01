<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingreso extends Model
{
    protected $fillable = [
        'proveedor_id',
        'usuario_id',
        'almacen',
        'fecha_ingreso',
        'numero_nota',
        'numero_remision',
        'numero_factura',
        'numero_orden_compra',
        'tipo_ingreso',
        'observacion',
        'recibido_por',
        'autorizado_por',
    ];

    protected function casts(): array
    {
        return ['fecha_ingreso' => 'date'];
    }

    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }

    /**
     * Usuario autenticado que registró el ingreso.
     * Los ingresos históricos pueden no tener usuario asociado.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
    public function lotes(): HasMany { return $this->hasMany(Lote::class); }
}
