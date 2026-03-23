<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandaItemAdicional extends Model
{
    protected $table = 'comanda_item_adicionais';

    protected $fillable = ['comanda_item_id', 'item_adicional_id', 'quantidade', 'preco_unitario'];

    public function comanda_item(): BelongsTo
    {
        return $this->belongsTo(ComandaItem::class, 'comanda_item_id');
    }

    public function buscar_item_adicional(): BelongsTo
    {
        return $this->belongsTo(ItemAdicional::class, 'item_adicional_id');
    }
}
