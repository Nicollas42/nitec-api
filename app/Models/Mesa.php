<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    use HasFactory;

    protected $fillable = ['nome_mesa', 'status_mesa'];

    /**
     * Retorna todas as comandas atreladas a esta mesa.
     * * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function listar_comandas()
    {
        return $this->hasMany(Comanda::class, 'mesa_id');
    }
}