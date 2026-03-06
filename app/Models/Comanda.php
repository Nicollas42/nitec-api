<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comanda extends Model
{
    use HasFactory;

    protected $fillable = [
        'mesa_id', 
        'cliente_id', 
        'usuario_id',
        'status_comanda', 
        'valor_total',
        'tipo_conta',
    ];

    /**
     * Retorna os itens consumidos nesta comanda específica.
     * * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function listar_itens()
    {
        return $this->hasMany(ComandaItem::class, 'comanda_id');
    }

    /**
     * Retorna a mesa atrelada a esta comanda.
     * * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buscar_mesa()
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }

    /**
     * Retorna o cliente dono desta comanda (caso seja individual).
     * * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buscar_cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function buscar_usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}