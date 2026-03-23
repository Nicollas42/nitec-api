<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAdicional extends Model
{
    protected $table = 'itens_adicionais';

    protected $fillable = ['grupo_adicional_id', 'nome', 'preco'];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoAdicional::class, 'grupo_adicional_id');
    }
}
