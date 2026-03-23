<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GrupoAdicional extends Model
{
    protected $table = 'grupos_adicionais';

    protected $fillable = ['nome', 'maximo_selecoes'];

    public function itens(): HasMany
    {
        return $this->hasMany(ItemAdicional::class, 'grupo_adicional_id')->orderBy('nome');
    }

    public function produtos(): BelongsToMany
    {
        return $this->belongsToMany(Produto::class, 'produto_grupos_adicionais');
    }
}
