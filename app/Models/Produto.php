<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use SoftDeletes;

    /**
     * Atributos liberados para atribuicao em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome_produto',
        'codigo_interno',
        'unidade_medida',
        'preco_venda',
        'preco_custo_medio',
        'margem_lucro_percentual',
        'categoria',
        'estoque_atual',
        'data_validade',
    ];

    /**
     * Retorna os aliases de codigo de barras vinculados ao produto.
     */
    public function codigos_barras(): HasMany
    {
        return $this->hasMany(ProdutoCodigoBarras::class)->orderBy('id');
    }

    /**
     * Retorna os vinculos editaveis entre produto e fornecedor.
     */
    public function produto_fornecedores(): HasMany
    {
        return $this->hasMany(ProdutoFornecedor::class)->orderBy('id');
    }

    /**
     * Retorna os lotes atuais e historicos do estoque do produto.
     */
    public function estoque_lotes(): HasMany
    {
        return $this->hasMany(ProdutoEstoqueLote::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Retorna os consumos FIFO registados para o produto.
     */
    public function estoque_consumos(): HasMany
    {
        return $this->hasMany(ProdutoEstoqueConsumo::class)->orderBy('id');
    }

    /**
     * Retorna os fornecedores associados ao produto via tabela pivot.
     */
    public function fornecedores(): BelongsToMany
    {
        return $this->belongsToMany(Fornecedor::class, 'produto_fornecedor')
            ->withPivot(['id', 'codigo_sku_fornecedor', 'unidade_embalagem', 'fator_conversao', 'ultimo_preco_compra']);
    }
}