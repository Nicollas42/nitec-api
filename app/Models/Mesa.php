<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Representa uma mesa física do estabelecimento no banco isolado do cliente.
 */
class Mesa extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'mesas';

    /**
     * @var array
     */
    protected $fillable = [
        'nome_mesa',
        'status_mesa',
        'capacidade_pessoas'
    ];

    /**
     * Relacionamento: Uma mesa pode ter várias comandas.
     * * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function listar_comandas()
    {
        return $this->hasMany(Comanda::class, 'mesa_id');
    }
}