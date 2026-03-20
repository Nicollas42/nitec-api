<?php

namespace App\Services;

use App\Models\EstoqueEntrada;
use App\Models\EstoquePerda;
use App\Models\Produto;
use App\Models\ProdutoEstoqueConsumo;
use App\Models\ProdutoEstoqueLote;
use App\Models\ProdutoFornecedor;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GestaoEstoqueService
{
    /**
     * Regista uma nova entrada de estoque e cria o lote correspondente para FIFO.
     *
     * @return array<string, int|float|string|null>
     */
    public function registrar_entrada(
        Produto $produto,
        ?ProdutoFornecedor $produto_fornecedor,
        int $usuario_id,
        string $modo_entrada,
        int $quantidade_comprada,
        float $custo_unitario_compra,
        ?string $data_validade_lote = null
    ): array {
        $fator_conversao = $produto_fornecedor?->fator_conversao ?? 1;
        $unidades_entrada = max(0, $quantidade_comprada * $fator_conversao);
        $custo_total_entrada = round($quantidade_comprada * $custo_unitario_compra, 2);
        $custo_unitario_unitario = $unidades_entrada > 0
            ? round($custo_total_entrada / $unidades_entrada, 2)
            : 0;

        $entrada = EstoqueEntrada::query()->create([
            'produto_id' => $produto->id,
            'fornecedor_id' => $produto_fornecedor?->fornecedor_id,
            'usuario_id' => $usuario_id,
            'quantidade_comprada' => $quantidade_comprada,
            'custo_unitario_compra' => $custo_unitario_compra,
            'custo_total_entrada' => $custo_total_entrada,
        ]);

        $this->criar_lote([
            'produto_id' => $produto->id,
            'fornecedor_id' => $produto_fornecedor?->fornecedor_id,
            'estoque_entrada_id' => $entrada->id,
            'modo_origem' => $modo_entrada,
            'data_validade' => $data_validade_lote ?: $produto->data_validade,
            'quantidade_inicial' => $unidades_entrada,
            'quantidade_atual' => $unidades_entrada,
            'custo_unitario_medio' => $custo_unitario_unitario,
        ]);

        if ($produto_fornecedor instanceof ProdutoFornecedor) {
            $produto_fornecedor->update([
                'ultimo_preco_compra' => $custo_unitario_compra,
            ]);
        }

        $this->recalcular_resumo_produto($produto);

        return [
            'produto_id' => $produto->id,
            'fornecedor_id' => $produto_fornecedor?->fornecedor_id,
            'codigo_sku_fornecedor' => $produto_fornecedor?->codigo_sku_fornecedor,
            'fator_conversao' => $fator_conversao,
            'quantidade_comprada' => $quantidade_comprada,
            'unidades_entrada' => $unidades_entrada,
            'custo_unitario_compra' => $custo_unitario_compra,
            'custo_total_entrada' => $custo_total_entrada,
            'preco_custo_medio_atualizado' => (float) $produto->fresh()->preco_custo_medio,
            'estoque_atual' => (int) $produto->fresh()->estoque_atual,
        ];
    }

    /**
     * Ajusta o saldo do produto quando o cadastro salva um valor total manual de estoque.
     */
    public function sincronizar_estoque_cadastro(Produto $produto, int $estoque_desejado, float $custo_unitario_referencia): void
    {
        $estoque_atual = $this->obter_quantidade_atual_produto($produto->id);

        if ($estoque_desejado === $estoque_atual) {
            $this->recalcular_resumo_produto($produto);
            return;
        }

        if ($estoque_desejado > $estoque_atual) {
            $diferenca = $estoque_desejado - $estoque_atual;

            $this->criar_lote([
                'produto_id' => $produto->id,
                'fornecedor_id' => null,
                'estoque_entrada_id' => null,
                'modo_origem' => 'ajuste_cadastro',
                'data_validade' => $produto->data_validade,
                'quantidade_inicial' => $diferenca,
                'quantidade_atual' => $diferenca,
                'custo_unitario_medio' => max(0, $custo_unitario_referencia),
            ]);
        } else {
            $this->consumir_estoque_fifo(
                $produto,
                $estoque_atual - $estoque_desejado,
                'ajuste_cadastro',
                null
            );
        }

        $this->recalcular_resumo_produto($produto);
    }

    /**
     * Regista uma perda de estoque consumindo os lotes mais antigos primeiro.
     */
    public function registrar_perda(Produto $produto, int $usuario_id, int $quantidade, string $motivo): EstoquePerda
    {
        $this->garantir_saldo_suficiente($produto, $quantidade);

        $perda = EstoquePerda::query()->create([
            'produto_id' => $produto->id,
            'usuario_id' => $usuario_id,
            'quantidade' => $quantidade,
            'motivo' => $motivo,
            'custo_total_perda' => 0,
        ]);

        $custo_total_perda = $this->consumir_estoque_fifo($produto, $quantidade, 'estoque_perda', $perda->id);

        $perda->update([
            'custo_total_perda' => $custo_total_perda,
        ]);

        return $perda->fresh();
    }

    /**
     * Consome o estoque em FIFO para um item de comanda recem-criado.
     */
    public function consumir_para_comanda_item(Produto $produto, int $quantidade, int $comanda_item_id): float
    {
        $this->garantir_saldo_suficiente($produto, $quantidade);

        return $this->consumir_estoque_fifo($produto, $quantidade, 'comanda_item', $comanda_item_id);
    }

    /**
     * Devolve ao estoque os consumos rastreados por uma referencia operacional.
     */
    public function restaurar_por_referencia(string $referencia_tipo, ?int $referencia_id): void
    {
        $consumos = $this->obter_consumos_por_referencia($referencia_tipo, $referencia_id);

        if ($consumos->isEmpty()) {
            return;
        }

        $produto_ids = [];

        foreach ($consumos as $consumo) {
            $lote = ProdutoEstoqueLote::query()
                ->lockForUpdate()
                ->find($consumo->produto_estoque_lote_id);

            if ($lote instanceof ProdutoEstoqueLote) {
                $lote->increment('quantidade_atual', $consumo->quantidade);
            }

            $produto_ids[] = (int) $consumo->produto_id;
        }

        ProdutoEstoqueConsumo::query()
            ->whereKey($consumos->pluck('id')->all())
            ->delete();

        foreach (array_unique($produto_ids) as $produto_id) {
            $produto = Produto::query()->find($produto_id);

            if ($produto instanceof Produto) {
                $this->recalcular_resumo_produto($produto);
            }
        }
    }

    /**
     * Devolve apenas parte do consumo mais recente de uma referencia, util para reduzir itens na comanda.
     */
    public function restaurar_quantidade_por_referencia(string $referencia_tipo, int $referencia_id, int $quantidade): void
    {
        $consumos = $this->obter_consumos_por_referencia($referencia_tipo, $referencia_id);

        if ($consumos->isEmpty() || $quantidade <= 0) {
            return;
        }

        $restante = $quantidade;
        $produto_ids = [];

        foreach ($consumos as $consumo) {
            if ($restante <= 0) {
                break;
            }

            $lote = ProdutoEstoqueLote::query()
                ->lockForUpdate()
                ->find($consumo->produto_estoque_lote_id);

            if (!$lote instanceof ProdutoEstoqueLote) {
                continue;
            }

            $quantidade_retorno = min($restante, (int) $consumo->quantidade);
            $lote->increment('quantidade_atual', $quantidade_retorno);

            if ($quantidade_retorno >= (int) $consumo->quantidade) {
                $consumo->delete();
            } else {
                $consumo->decrement('quantidade', $quantidade_retorno);
            }

            $produto_ids[] = (int) $consumo->produto_id;
            $restante -= $quantidade_retorno;
        }

        foreach (array_unique($produto_ids) as $produto_id) {
            $produto = Produto::query()->find($produto_id);

            if ($produto instanceof Produto) {
                $this->recalcular_resumo_produto($produto);
            }
        }
    }

    /**
     * Recalcula os campos canonicamente agregados do produto a partir dos lotes ativos.
     */
    public function recalcular_resumo_produto(Produto $produto): void
    {
        $resumo = ProdutoEstoqueLote::query()
            ->where('produto_id', $produto->id)
            ->where('quantidade_atual', '>', 0)
            ->selectRaw('COALESCE(SUM(quantidade_atual), 0) as quantidade_total')
            ->selectRaw('COALESCE(SUM(quantidade_atual * custo_unitario_medio), 0) as valor_total')
            ->first();

        $estoque_total = (int) ($resumo?->quantidade_total ?? 0);
        $valor_total = (float) ($resumo?->valor_total ?? 0);
        $preco_custo_medio = $estoque_total > 0 ? round($valor_total / $estoque_total, 2) : 0;

        $produto->forceFill([
            'estoque_atual' => $estoque_total,
            'preco_custo_medio' => $preco_custo_medio,
        ])->save();
    }

    /**
     * Garante que o produto ainda possua saldo suficiente antes de uma baixa.
     */
    private function garantir_saldo_suficiente(Produto $produto, int $quantidade): void
    {
        $saldo_atual = $this->obter_quantidade_atual_produto($produto->id);

        if ($saldo_atual < $quantidade) {
            throw ValidationException::withMessages([
                'produto_id' => ['Estoque insuficiente para esta operacao.'],
            ]);
        }
    }

    /**
     * Consome o saldo dos lotes em FIFO e regista as faixas consumidas.
     */
    private function consumir_estoque_fifo(Produto $produto, int $quantidade, string $referencia_tipo, ?int $referencia_id): float
    {
        $lotes = ProdutoEstoqueLote::query()
            ->where('produto_id', $produto->id)
            ->where('quantidade_atual', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $restante = $quantidade;
        $custo_total = 0;

        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }

            $quantidade_consumida = min($restante, (int) $lote->quantidade_atual);

            if ($quantidade_consumida <= 0) {
                continue;
            }

            $lote->decrement('quantidade_atual', $quantidade_consumida);

            ProdutoEstoqueConsumo::query()->create([
                'produto_id' => $produto->id,
                'fornecedor_id' => $lote->fornecedor_id,
                'produto_estoque_lote_id' => $lote->id,
                'referencia_tipo' => $referencia_tipo,
                'referencia_id' => $referencia_id,
                'quantidade' => $quantidade_consumida,
                'custo_unitario_medio' => $lote->custo_unitario_medio,
            ]);

            $custo_total += $quantidade_consumida * (float) $lote->custo_unitario_medio;
            $restante -= $quantidade_consumida;
        }

        if ($restante > 0) {
            throw ValidationException::withMessages([
                'produto_id' => ['Nao foi possivel consumir o estoque solicitado integralmente.'],
            ]);
        }

        $this->recalcular_resumo_produto($produto);

        return round($custo_total, 2);
    }

    /**
     * Cria um novo lote de estoque mantendo um unico ponto de entrada para a modelagem.
     *
     * @param array<string, mixed> $dados_lote
     */
    private function criar_lote(array $dados_lote): ProdutoEstoqueLote
    {
        return ProdutoEstoqueLote::query()->create($dados_lote);
    }

    /**
     * Obtém a quantidade total atual do produto a partir dos lotes abertos.
     */
    private function obter_quantidade_atual_produto(int $produto_id): int
    {
        return (int) ProdutoEstoqueLote::query()
            ->where('produto_id', $produto_id)
            ->where('quantidade_atual', '>', 0)
            ->sum('quantidade_atual');
    }

    /**
     * Recupera os consumos associados a uma referencia operacional especifica.
     *
     * @return Collection<int, ProdutoEstoqueConsumo>
     */
    private function obter_consumos_por_referencia(string $referencia_tipo, ?int $referencia_id): Collection
    {
        return ProdutoEstoqueConsumo::query()
            ->where('referencia_tipo', $referencia_tipo)
            ->when(
                $referencia_id === null,
                fn ($consulta) => $consulta->whereNull('referencia_id'),
                fn ($consulta) => $consulta->where('referencia_id', $referencia_id)
            )
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();
    }
}
