<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; 

    /**
     * Atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'name',
        'email',
        'telefone',          // 🟢 Adicionado para contato
        'password',
        'tipo_usuario',
        'status_conta',      // 'ativo', 'inativo' ou 'demitido'
        'tipo_contrato',     // 'fixo' ou 'temporario'
        'expiracao_acesso',  // Data/Hora de bloqueio automático para temporários
    ];

    /**
     * Atributos que devem ser ocultados para serialização.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Atributos que devem ser convertidos (cast).
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'expiracao_acesso' => 'datetime', // Garante que é tratado como Data/Hora pelo Carbon
        ];
    }
}