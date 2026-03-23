<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoCozinha extends Model
{
    protected $table = 'pedidos_cozinha';

    protected $fillable = [
        'comanda_item_id',
        'comanda_id',
        'mesa_id',
        'produto_nome',
        'adicionais_texto',
        'quantidade',
        'status',
        'visto_pelo_garcom',
    ];

    protected $casts = [
        'visto_pelo_garcom' => 'boolean',
    ];

    public function comanda_item(): BelongsTo
    {
        return $this->belongsTo(ComandaItem::class);
    }

    public function comanda(): BelongsTo
    {
        return $this->belongsTo(Comanda::class);
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }
}
