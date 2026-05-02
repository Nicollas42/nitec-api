<?php

namespace App\Http\Controllers;

use App\Models\CardapioConfig;
use App\Models\CardapioPdf;
use App\Models\Cliente;
use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Mesa;
use App\Services\ComandaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CardapioController extends Controller
{
    public function __construct(private ComandaService $comandaService) {}

    public function config_publica(): JsonResponse
    {
        $config = CardapioConfig::obter();
        $pdfs   = CardapioPdf::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get()
            ->map(fn (CardapioPdf $pdf) => [
                'id'            => $pdf->id,
                'nome_cardapio' => $pdf->nome_cardapio,
                'arquivo_url'   => $pdf->arquivo_url,
                'ordem'         => $pdf->ordem,
            ])
            ->values();

        return response()->json([
            'config' => $this->mapear_config($config),
            'pdfs'   => $pdfs,
        ]);
    }

    public function dados_mesa_publica(Request $request, int $id): JsonResponse
    {
        $mesa = Mesa::findOrFail($id);
        $comandaId = (int) $request->query('comanda_id');

        $comanda = null;
        if ($comandaId > 0) {
            $comanda = Comanda::where('id', $comandaId)
                ->where('mesa_id', $mesa->id)
                ->whereIn('status_comanda', ['aberta', 'pendente'])
                ->with([
                    'buscar_cliente',
                    'listar_itens' => function ($query) {
                        $query->orderBy('id');
                    },
                    'listar_itens.buscar_produto',
                    'listar_itens.adicionais.buscar_item_adicional',
                ])
                ->first();
        }

        return response()->json([
            'mesa' => [
                'id'                      => $mesa->id,
                'nome_mesa'               => $mesa->nome_mesa,
                'status_mesa'             => $mesa->status_mesa,
                'sessao_uuid'             => $mesa->sessao_uuid,
                'solicitando_atendimento' => (bool) $mesa->solicitando_atendimento,
            ],
            'comanda' => $comanda ? $this->mapear_comanda_cliente($comanda) : null,
        ]);
    }

    /**
     * Login por CPF — cliente já cadastrado.
     */
    public function login_cliente(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'mesa_id'     => ['required', 'integer', 'exists:mesas,id'],
            'cpf'         => ['required', 'string'],
            'sessao_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        $cpf = preg_replace('/\D/', '', $dados['cpf']);

        if (!Cliente::validar_cpf($cpf)) {
            return response()->json(['mensagem' => 'CPF invalido.'], 422);
        }

        $cliente = Cliente::where('cpf', $cpf)->first();

        if (!$cliente) {
            return response()->json([
                'mensagem'       => 'CPF nao cadastrado. Realize o cadastro.',
                'nao_encontrado' => true,
            ], 404);
        }

        return $this->criar_comanda_pendente(
            (int) $dados['mesa_id'],
            $cliente,
            $dados['sessao_uuid'] ?? null,
        );
    }

    /**
     * Cadastro de novo cliente com CPF.
     */
    public function registrar_cliente(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'mesa_id'     => ['required', 'integer', 'exists:mesas,id'],
            'nome'        => ['required', 'string', 'max:100'],
            'telefone'    => ['required', 'string', 'max:20'],
            'cpf'         => ['required', 'string'],
            'sessao_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        $cpf = preg_replace('/\D/', '', $dados['cpf']);

        if (!Cliente::validar_cpf($cpf)) {
            return response()->json(['mensagem' => 'CPF invalido.'], 422);
        }

        $existente = Cliente::where('cpf', $cpf)->first();
        if ($existente) {
            return response()->json([
                'mensagem'      => 'CPF ja cadastrado. Use o login.',
                'ja_cadastrado' => true,
            ], 409);
        }

        DB::beginTransaction();
        try {
            $cliente = Cliente::create([
                'nome_cliente' => trim($dados['nome']),
                'telefone'     => trim($dados['telefone']),
                'cpf'          => $cpf,
            ]);

            $resultado = $this->criar_comanda_pendente(
                (int) $dados['mesa_id'],
                $cliente,
                $dados['sessao_uuid'] ?? null,
            );

            DB::commit();
            return $resultado;
        } catch (\Throwable $erro) {
            DB::rollBack();
            report($erro);
            return response()->json(['mensagem' => 'Erro ao registrar cliente.'], 500);
        }
    }

    /**
     * Polling do cliente — verifica status da comanda (pendente→aberta, aberta→fechada/cancelada).
     * Quando fechada/cancelada, devolve os itens consumidos e o total para o popup de despedida.
     */
    public function status_comanda_publica(int $id): JsonResponse
    {
        $comanda = Comanda::with([
            'buscar_cliente',
            'listar_itens.buscar_produto',
        ])->find($id);

        if (!$comanda) {
            return response()->json(['status' => 'rejeitada']);
        }

        $payload = [
            'status'     => $comanda->status_comanda,
            'comanda_id' => $comanda->id,
            'mesa_id'    => $comanda->mesa_id,
            'nome'       => $comanda->buscar_cliente?->nome_cliente,
            'telefone'   => $comanda->buscar_cliente?->telefone,
        ];

        // Envia detalhes do consumo quando a conta foi encerrada
        if (in_array($comanda->status_comanda, ['fechada', 'cancelada'])) {
            $itens = $comanda->listar_itens->map(fn (ComandaItem $item) => [
                'nome_produto'   => $item->buscar_produto?->nome_produto ?? '(produto removido)',
                'quantidade'     => (int) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
                'subtotal'       => round((float) $item->preco_unitario * (int) $item->quantidade, 2),
            ])->values();

            $payload['valor_total'] = (float) $comanda->valor_total;
            $payload['desconto']    = (float) ($comanda->desconto ?? 0);
            $payload['itens']       = $itens;
        } else {
            // Para comandas ativas, envia o token para que o frontend mantenha a URL de sessão
            $payload['token_cliente'] = $comanda->token_cliente;
        }

        return response()->json($payload);
    }

    /**
     * Restaura a sessão do cliente pelo token pessoal (URL #/cardapio/mesa/{id}/s/{token}).
     *
     * Regra de negócio:
     *  - Se a comanda ainda está ativa (aberta/pendente): retorna os dados completos da sessão.
     *  - Se já foi encerrada: retorna os dados de encerramento para exibir o popup de despedida.
     *  - Se não encontrada: retorna 404 — o frontend deve redirecionar para o link cru.
     *
     * @param string $token UUID do token_cliente
     * @return JsonResponse
     */
    public function sessao_por_token(string $token): JsonResponse
    {
        $comanda = Comanda::with([
            'buscar_cliente',
            'listar_itens.buscar_produto',
        ])->where('token_cliente', $token)->first();

        if (!$comanda) {
            return response()->json(['mensagem' => 'Sessao nao encontrada.'], 404);
        }

        $cliente = $comanda->buscar_cliente;

        $payload = [
            'comanda_id'   => $comanda->id,
            'mesa_id'      => $comanda->mesa_id,
            'status'       => $comanda->status_comanda,
            'sessao_uuid'  => $comanda->buscar_mesa?->sessao_uuid,
            'nome'         => $cliente?->nome_cliente,
            'cpf'          => $cliente?->cpf,
            'telefone'     => $cliente?->telefone,
            'token_cliente'=> $comanda->token_cliente,
        ];

        if (in_array($comanda->status_comanda, ['fechada', 'cancelada'])) {
            $itens = $comanda->listar_itens->map(fn (ComandaItem $item) => [
                'nome_produto'   => $item->buscar_produto?->nome_produto ?? '(produto removido)',
                'quantidade'     => (int) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
                'subtotal'       => round((float) $item->preco_unitario * (int) $item->quantidade, 2),
            ])->values();

            $payload['valor_total'] = (float) $comanda->valor_total;
            $payload['desconto']    = (float) ($comanda->desconto ?? 0);
            $payload['itens']       = $itens;
        }

        return response()->json($payload);
    }

    /**
     * Cria uma comanda com status 'pendente' e gera sessao_uuid na mesa se necessário.
     */
    private function criar_comanda_pendente(int $mesa_id, Cliente $cliente, ?string $sessao_uuid_cliente): JsonResponse
    {
        DB::beginTransaction();
        try {
            $mesa = Mesa::lockForUpdate()->findOrFail($mesa_id);

            // Validação de sessão — impede uso de QR antigo
            if ($mesa->sessao_uuid && $sessao_uuid_cliente && $sessao_uuid_cliente !== $mesa->sessao_uuid) {
                DB::rollBack();
                return response()->json(['mensagem' => 'Sessao expirada. Recarregue a pagina.'], 403);
            }

            // Verifica se já existe comanda aberta ou pendente para este cliente nesta mesa
            $comanda_existente = Comanda::where('mesa_id', $mesa_id)
                ->where('cliente_id', $cliente->id)
                ->whereIn('status_comanda', ['aberta', 'pendente'])
                ->first();

            if ($comanda_existente) {
                // Garante que comanda legacy (sem token) recebe um token agora
                if (!$comanda_existente->token_cliente) {
                    $comanda_existente->token_cliente = Str::uuid()->toString();
                    $comanda_existente->save();
                }

                DB::commit();
                return response()->json([
                    'comanda_id'   => $comanda_existente->id,
                    'mesa_id'      => $mesa_id,
                    'status'       => $comanda_existente->status_comanda,
                    'sessao_uuid'  => $mesa->sessao_uuid,
                    'nome'         => $cliente->nome_cliente,
                    'cpf'          => $cliente->cpf,
                    'telefone'     => $cliente->telefone,
                    'token_cliente'=> $comanda_existente->token_cliente,
                ]);
            }

            // Gera sessao_uuid se a mesa ainda não tem uma
            if (!$mesa->sessao_uuid) {
                $mesa->sessao_uuid = Str::uuid()->toString();
                $mesa->save();
            }

            // Cria comanda pendente com token de sessão individual
            $comanda = Comanda::create([
                'mesa_id'            => $mesa_id,
                'cliente_id'         => $cliente->id,
                'status_comanda'     => 'pendente',
                'valor_total'        => 0,
                'tipo_conta'         => 'digital',
                'data_hora_abertura' => Carbon::now(),
                'token_cliente'      => Str::uuid()->toString(),
            ]);

            DB::commit();

            return response()->json([
                'comanda_id'   => $comanda->id,
                'mesa_id'      => $mesa_id,
                'status'       => 'pendente',
                'sessao_uuid'  => $mesa->sessao_uuid,
                'nome'         => $cliente->nome_cliente,
                'cpf'          => $cliente->cpf,
                'telefone'     => $cliente->telefone,
                'token_cliente'=> $comanda->token_cliente,
            ], 201);
        } catch (\Throwable $erro) {
            DB::rollBack();
            report($erro);
            return response()->json(['mensagem' => 'Erro ao processar.'], 500);
        }
    }

    /**
     * Recebe apenas uma chamada do cliente pedindo atendimento — sem lista
     * de produtos. Empilha o pedido na pilha de solicitações da mesa para
     * que vários clientes da mesma mesa possam chamar o garçom em paralelo.
     */
    public function solicitar_atendimento(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'mesa_id'      => ['required', 'integer', 'exists:mesas,id'],
            'nome_cliente' => ['nullable', 'string', 'max:100'],
            'telefone'     => ['nullable', 'string', 'max:20'],
        ]);

        $mesa = Mesa::findOrFail($dados['mesa_id']);

        // Normaliza para array — suporta o formato antigo (objeto único).
        $pilha = $mesa->solicitacao_detalhes ?? [];
        if (!empty($pilha) && array_is_list($pilha) === false) {
            $pilha = [$pilha];
        }

        $pilha[] = [
            'nome_cliente'  => trim($dados['nome_cliente'] ?? '(Não informado)'),
            'telefone'      => trim($dados['telefone'] ?? '(Não informado)'),
            'solicitado_em' => Carbon::now()->toISOString(),
        ];

        $mesa->update([
            'solicitando_atendimento' => true,
            'solicitacao_detalhes'    => $pilha,
        ]);

        return response()->json(['mensagem' => 'Garcom notificado!']);
    }

    /**
     * Retorna o PDF de um cardápio específico (endpoint público, sem auth).
     */
    public function servir_pdf_publico(int $id): BinaryFileResponse
    {
        $pdf = CardapioPdf::where('ativo', true)->findOrFail($id);

        abort_unless(Storage::disk('public')->exists($pdf->arquivo_path), 404);

        return response()->file(Storage::disk('public')->path($pdf->arquivo_path), [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ÁREA ADMIN (auth obrigatória)
    // ─────────────────────────────────────────────────────────────────────

    public function config_admin(): JsonResponse
    {
        return response()->json($this->mapear_config(CardapioConfig::obter()));
    }

    public function atualizar_config(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome_exibicao'        => ['required', 'string', 'max:100'],
            'subtitulo'            => ['nullable', 'string', 'max:200'],
            'mensagem_boas_vindas' => ['nullable', 'string', 'max:500'],
            'cor_primaria'         => ['required', 'string', 'max:20'],
            'cor_destaque'         => ['required', 'string', 'max:20'],
            'cor_fundo'            => ['required', 'string', 'max:20'],
            'logo_url'             => ['nullable', 'string', 'max:500'],
        ]);

        $config = CardapioConfig::obter();
        $config->update($dados);

        return response()->json($this->mapear_config($config->fresh()));
    }

    /**
     * Lista todos os PDFs cadastrados (ativos e inativos) para gestão no admin.
     */
    public function listar_pdfs_admin(): JsonResponse
    {
        $pdfs = CardapioPdf::orderBy('ordem')
            ->orderBy('id')
            ->get()
            ->map(fn (CardapioPdf $pdf) => [
                'id'               => $pdf->id,
                'nome_cardapio'    => $pdf->nome_cardapio,
                'arquivo_url'      => $pdf->arquivo_url,
                'arquivo_tamanho'  => $pdf->arquivo_tamanho,
                'ordem'            => $pdf->ordem,
                'ativo'            => $pdf->ativo,
                'criado_em'        => $pdf->created_at?->toISOString(),
            ]);

        return response()->json(['pdfs' => $pdfs]);
    }

    /**
     * Faz upload de um novo PDF (multipart/form-data).
     * Campos: nome_cardapio (string), arquivo (file, pdf até 20 MB).
     */
    public function upload_pdf_admin(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome_cardapio' => ['required', 'string', 'max:100'],
            'arquivo'       => ['required', 'file', 'mimes:pdf', 'max:20480'], // 20 MB
        ]);

        $arquivo       = $request->file('arquivo');
        $nome_seguro   = Str::slug(Str::limit($dados['nome_cardapio'], 40, '')) ?: 'cardapio';
        $nome_arquivo  = $nome_seguro . '_' . Str::random(8) . '.pdf';
        $caminho       = 'cardapio-pdfs/' . $nome_arquivo;

        Storage::disk('public')->put($caminho, file_get_contents($arquivo->getRealPath()));

        $proximaOrdem = (int) (CardapioPdf::max('ordem') ?? 0) + 1;

        $pdf = CardapioPdf::create([
            'nome_cardapio' => $dados['nome_cardapio'],
            'arquivo_path'  => $caminho,
            'ordem'         => $proximaOrdem,
            'ativo'         => true,
        ]);

        return response()->json([
            'pdf' => [
                'id'              => $pdf->id,
                'nome_cardapio'   => $pdf->nome_cardapio,
                'arquivo_url'     => $pdf->arquivo_url,
                'arquivo_tamanho' => $pdf->arquivo_tamanho,
                'ordem'           => $pdf->ordem,
                'ativo'           => $pdf->ativo,
            ],
        ], 201);
    }

    /**
     * Atualiza metadados do PDF (nome, ordem, ativo). Não substitui o arquivo.
     */
    public function atualizar_pdf_admin(Request $request, int $id): JsonResponse
    {
        $dados = $request->validate([
            'nome_cardapio' => ['sometimes', 'string', 'max:100'],
            'ordem'         => ['sometimes', 'integer', 'min:0'],
            'ativo'         => ['sometimes', 'boolean'],
        ]);

        $pdf = CardapioPdf::findOrFail($id);
        $pdf->update($dados);

        return response()->json([
            'pdf' => [
                'id'              => $pdf->id,
                'nome_cardapio'   => $pdf->nome_cardapio,
                'arquivo_url'     => $pdf->arquivo_url,
                'arquivo_tamanho' => $pdf->arquivo_tamanho,
                'ordem'           => $pdf->ordem,
                'ativo'           => $pdf->ativo,
            ],
        ]);
    }

    /**
     * Remove o PDF do storage e do banco.
     */
    public function excluir_pdf_admin(int $id): JsonResponse
    {
        $pdf = CardapioPdf::findOrFail($id);

        if ($pdf->arquivo_path && Storage::disk('public')->exists($pdf->arquivo_path)) {
            Storage::disk('public')->delete($pdf->arquivo_path);
        }

        $pdf->delete();

        return response()->json(['sucesso' => true]);
    }

    private function mapear_comanda_cliente(Comanda $comanda): array
    {
        $itens = $comanda->listar_itens
            ->map(fn (ComandaItem $item) => $this->mapear_item_comanda_cliente($item))
            ->values()
            ->all();

        return [
            'id'           => $comanda->id,
            'status'       => $comanda->status_comanda,
            'nome_cliente' => $comanda->buscar_cliente?->nome_cliente,
            'telefone'     => $comanda->buscar_cliente?->telefone,
            'valor_total'  => (float) $comanda->valor_total,
            'itens'        => $itens,
        ];
    }

    private function mapear_item_comanda_cliente(ComandaItem $item): array
    {
        $adicionais = $item->relationLoaded('adicionais') ? $item->adicionais : collect();
        $valorAdicionais = $adicionais->sum(function ($adicional) {
            return ((float) $adicional->preco_unitario) * max(1, (int) $adicional->quantidade);
        });

        $subtotal = (((float) $item->preco_unitario) + $valorAdicionais) * ((int) $item->quantidade);

        return [
            'id'             => $item->id,
            'produto_id'     => $item->produto_id,
            'nome_produto'   => $item->buscar_produto?->nome_produto ?? '(produto removido)',
            'quantidade'     => (int) $item->quantidade,
            'preco_unitario' => (float) $item->preco_unitario,
            'subtotal'       => round($subtotal, 2),
        ];
    }

    private function mapear_config(CardapioConfig $config): array
    {
        return [
            'nome_exibicao'        => $config->nome_exibicao,
            'subtitulo'            => $config->subtitulo,
            'mensagem_boas_vindas' => $config->mensagem_boas_vindas,
            'cor_primaria'         => $config->cor_primaria,
            'cor_destaque'         => $config->cor_destaque,
            'cor_fundo'            => $config->cor_fundo,
            'logo_url'             => $config->logo_url,
        ];
    }
}
