<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Los atributos que pueden asignarse masivamente.
     */
    protected $fillable = [
        'name',
        'username',
        'role',
        'regional',
        'email',
        'password',
    ];

    /**
     * Atributos ocultos al serializar el usuario.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversión de atributos.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
