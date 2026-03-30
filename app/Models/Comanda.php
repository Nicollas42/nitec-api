<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // 🟢 Importado para exclusão lógica

class Comanda extends Model
{
    use HasFactory, SoftDeletes; // 🟢 SoftDeletes ativado

    protected $fillable = [
        'mesa_id', 'cliente_id', 'usuario_id', 'status_comanda', 'motivo_cancelamento',
        'tipo_conta', 'valor_total', 'desconto', 'forma_pagamento', 'data_hora_abertura', 'data_hora_fechamento'
    ];
    
    /**
     * Casts para garantir que as datas sejam tratadas como objetos Carbon/Datetime.
     */
    protected $casts = [
        'data_hora_abertura' => 'datetime',
        'data_hora_fechamento' => 'datetime',
    ];

    /**
     * Retorna os itens consumidos nesta comanda específica.
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function listar_itens()
    {
        return $this->hasMany(ComandaItem::class, 'comanda_id');
    }

    /**
     * Retorna a mesa atrelada a esta comanda.
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buscar_mesa()
    {
        return $this->belongsTo(Mesa::class, 'mesa_id');
    }

    /**
     * Retorna o cliente dono desta comanda (caso seja individual).
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buscar_cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Retorna o utilizador (atendente) que abriu a comanda.
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function buscar_usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}