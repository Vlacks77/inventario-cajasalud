<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Crea los usuarios iniciales del sistema.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'dra.carmen-almacen'],
            [
                'name' => 'Dra. Carmen Gutierrez- Encargada de Almacen',
                'role' => 'almacen',
                'email' => 'dra.carmen-almacen@cajasalud.local',
                'password' => Hash::make('cscgutierrez'),
            ]
        );


        User::updateOrCreate(
            ['username' => 'csc'],
            [
                'name' => 'Kdu - Encargado de Sistemas',
                'role' => 'admin',
                'email' => 'csc@cajasalud.local',
                'password' => Hash::make('+kdu140876$'),
            ]
        );

        User::updateOrCreate(
            ['username' => 'auxiliar'],
            [
                'name' => 'Auxiliar de Farmacia',
                'role' => 'auxiliar',
                'email' => 'auxiliar@cajasalud.local',
                'password' => Hash::make('Caminos-Aux#2026!'),
            ]
        );

        User::updateOrCreate(
            ['username' => 'sistemas'],
            [
                'name' => 'Administrador de Sistemas',
                'role' => 'admin',
                'email' => 'sistemas@cajasalud.local',
                'password' => Hash::make('Caminos-Sis#2026!'),
            ]
        );

        User::updateOrCreate(
            ['username' => 'invitado'],
            [
                'name' => 'Usuario Invitado',
                'role' => 'invitado',
                'email' => 'invitado@cajasalud.local',
                'password' => Hash::make('Caminos-Inv#2026!'),
            ]
        );
    }
}
