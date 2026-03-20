<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdutoEstoqueLote extends Model
{
    /**
     * Nome explicito da tabela.
     *
     * @var string
     */
    protected $table = 'produto_estoque_lotes';

    /**
     * Atributos liberados para atribuicao em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'produto_id',
        'fornecedor_id',
        'estoque_entrada_id',
        'modo_origem',
        'data_validade',
        'quantidade_inicial',
        'quantidade_atual',
        'custo_unitario_medio',
    ];

    /**
     * Retorna o produto associado ao lote.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    /**
     * Retorna o fornecedor associado ao lote, quando existir.
     */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    /**
     * Retorna a entrada que originou o lote, quando existir.
     */
    public function estoque_entrada(): BelongsTo
    {
        return $this->belongsTo(EstoqueEntrada::class);
    }

    /**
     * Retorna os consumos FIFO vinculados a este lote.
     */
    public function consumos(): HasMany
    {
        return $this->hasMany(ProdutoEstoqueConsumo::class, 'produto_estoque_lote_id')->orderBy('id');
    }
}
