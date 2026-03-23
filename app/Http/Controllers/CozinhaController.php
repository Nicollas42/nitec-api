<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PedidoCozinha;
use App\Models\Mesa;

class CozinhaController extends Controller
{
    /**
     * Lista pedidos ativos (pendente + em_preparacao) agrupados por mesa.
     */
    public function listar_pedidos()
    {
        $pedidos = PedidoCozinha::with('mesa')
            ->whereIn('status', ['pendente', 'em_preparacao'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Agrupar por mesa (incluindo balcão/sem mesa)
        $agrupado = $pedidos->groupBy(fn ($p) => $p->mesa_id ?? 'balcao');

        $mesas = $agrupado->map(function ($itens, $mesa_id) {
            $mesa = $itens->first()->mesa;
            return [
                'mesa_id'   => $mesa_id === 'balcao' ? null : (int) $mesa_id,
                'mesa_nome' => $mesa?->nome_mesa ?? 'Balcão',
                'pedidos'   => $itens->map(fn ($p) => [
                    'id'               => $p->id,
                    'produto_nome'     => $p->produto_nome,
                    'adicionais_texto' => $p->adicionais_texto,
                    'quantidade'       => $p->quantidade,
                    'status'           => $p->status,
                    'criado_em'        => $p->created_at->format('H:i'),
                ])->values(),
            ];
        })->values();

        return response()->json(['status' => true, 'mesas' => $mesas]);
    }

    /**
     * Atualiza o status de um pedido de cozinha.
     */
    public function atualizar_status(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pendente,em_preparacao,finalizado',
        ]);

        $pedido = PedidoCozinha::findOrFail($id);
        $pedido->update([
            'status'             => $request->status,
            'visto_pelo_garcom'  => false, // nova mudança = garçom não viu ainda
        ]);

        return response()->json(['status' => true, 'mensagem' => 'Status atualizado.', 'pedido' => $pedido]);
    }

    /**
     * Retorna resumo de status por mesa — usado para piscar os cards.
     */
    public function status_mesas()
    {
        $pedidos = PedidoCozinha::whereNotNull('mesa_id')
            ->where(function ($q) {
                // Ativos (pendente/em_preparacao) OU finalizados ainda não vistos
                $q->whereIn('status', ['pendente', 'em_preparacao'])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'finalizado')->where('visto_pelo_garcom', false);
                  });
            })
            ->get(['mesa_id', 'status', 'visto_pelo_garcom']);

        $por_mesa = $pedidos->groupBy('mesa_id')->map(function ($itens) {
            $tem_pendente      = $itens->where('status', 'pendente')->isNotEmpty();
            $tem_em_preparacao = $itens->where('status', 'em_preparacao')->isNotEmpty();
            $tem_finalizado_nao_visto = $itens->where('status', 'finalizado')->where('visto_pelo_garcom', false)->isNotEmpty();
            return [
                'tem_pendente'             => $tem_pendente,
                'tem_em_preparacao'        => $tem_em_preparacao,
                'tem_finalizado_nao_visto' => $tem_finalizado_nao_visto,
                'tem_nao_visto'            => $itens->where('visto_pelo_garcom', false)->isNotEmpty(),
            ];
        });

        return response()->json(['status' => true, 'status_por_mesa' => $por_mesa]);
    }

    /**
     * Marca todos os pedidos de uma mesa como vistos pelo garçom.
     */
    public function marcar_visto(Request $request, $mesa_id)
    {
        PedidoCozinha::where('mesa_id', $mesa_id)
            ->where('visto_pelo_garcom', false)
            ->update(['visto_pelo_garcom' => true]);

        return response()->json(['status' => true, 'mensagem' => 'Marcado como visto.']);
    }
}
