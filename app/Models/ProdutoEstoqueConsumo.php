<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoEstoqueConsumo extends Model
{
    /**
     * Nome explicito da tabela.
     *
     * @var string
     */
    protected $table = 'produto_estoque_consumos';

    /**
     * Atributos liberados para atribuicao em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'produto_id',
        'fornecedor_id',
        'produto_estoque_lote_id',
        'referencia_tipo',
        'referencia_id',
        'quantidade',
        'custo_unitario_medio',
    ];

    /**
     * Retorna o produto associado ao consumo.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    /**
     * Retorna o fornecedor associado ao consumo, quando existir.
     */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    /**
     * Retorna o lote consumido por esta movimentacao.
     */
    public function produto_estoque_lote(): BelongsTo
    {
        return $this->belongsTo(ProdutoEstoqueLote::class, 'produto_estoque_lote_id');
    }
}
