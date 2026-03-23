<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComandaItem extends Model
{
    use HasFactory;

    // Define explicitamente o nome da tabela no banco
    protected $table = 'comanda_itens';

    protected $fillable = [
        'comanda_id', 
        'produto_id', 
        'quantidade', 
        'preco_unitario'
    ];

    /**
     * Retorna os detalhes do produto associado a este item.
     * * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buscar_produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    /**
     * Retorna os adicionais escolhidos para este item da comanda.
     */
    public function adicionais()
    {
        return $this->hasMany(ComandaItemAdicional::class, 'comanda_item_id');
    }
}