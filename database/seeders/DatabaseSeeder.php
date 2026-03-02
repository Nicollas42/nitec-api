<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Este seeder rodará automaticamente para cada novo bar cadastrado.
     */
    public function run(): void
    {
        // Criamos o dono do bar padrão
        User::create([
            'name' => 'Dono do Estabelecimento',
            'email' => 'admin@nitecsystem.com.br', // Email padrão de acesso inicial
            'password' => Hash::make('admin123'), // Senha padrão para o cliente trocar depois
            'tipo_usuario' => 'cliente', // Define que ele não é o Super Admin da Nitec
        ]);
    }
}