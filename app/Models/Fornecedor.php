<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fornecedor extends Model
{
    /**
     * Nome explicito da tabela para evitar pluralizacao incorreta.
     *
     * @var string
     */
    protected $table = 'fornecedores';

    /**
     * Atributos liberados para atribuicao em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nome_fantasia',
        'razao_social',
        'cnpj',
        'telefone',
        'email',
        'vendedor',
        'contato_vendedor',
        'status_fornecedor',
    ];

    /**
     * Retorna os vinculos editaveis entre fornecedor e produto.
     */
    public function produto_fornecedores(): HasMany
    {
        return $this->hasMany(ProdutoFornecedor::class)->orderBy('id');
    }

    /**
     * Retorna os produtos vinculados ao fornecedor.
     */
    public function produtos(): BelongsToMany
    {
        return $this->belongsToMany(Produto::class, 'produto_fornecedor')
            ->withPivot(['id', 'codigo_sku_fornecedor', 'fator_conversao', 'ultimo_preco_compra']);
    }

    /**
     * Retorna as entradas de estoque relacionadas ao fornecedor.
     */
    public function estoque_entradas(): HasMany
    {
        return $this->hasMany(EstoqueEntrada::class)->orderByDesc('id');
    }

    /**
     * Retorna os lotes de estoque que ainda preservam a origem deste fornecedor.
     */
    public function estoque_lotes(): HasMany
    {
        return $this->hasMany(ProdutoEstoqueLote::class)->orderBy('id');
    }
}
