<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoFornecedor extends Model
{
    /**
     * Indica se a tabela utiliza timestamps automaticos.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Nome explicito da tabela pivot.
     *
     * @var string
     */
    protected $table = 'produto_fornecedor';

    /**
     * Atributos liberados para atribuicao em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'produto_id',
        'fornecedor_id',
        'codigo_sku_fornecedor',
        'unidade_embalagem',
        'fator_conversao',
        'ultimo_preco_compra',
    ];

    /**
     * Retorna o produto associado ao vinculo.
     */
    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    /**
     * Retorna o fornecedor associado ao vinculo.
     */
    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}