<?php

namespace App\Http\Controllers;

use App\Services\ConsultasProntasService;
use Illuminate\Http\Request;

class ConsultasProntasController extends Controller
{
    public function __construct(private ConsultasProntasService $servico) {}

    public function listar()
    {
        return response()->json(['sucesso' => true, 'categorias' => $this->servico->listar()]);
    }

    public function executar(Request $requisicao, string $slug)
    {
        try {
            $parametros = $requisicao->only(['dias', 'limite', 'minimo', 'usuario_id']);
            $resultado = $this->servico->executar($slug, $parametros);
            return response()->json(['sucesso' => true, ...$resultado]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['sucesso' => false, 'mensagem' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['sucesso' => false, 'mensagem' => 'Erro ao executar consulta.'], 500);
        }
    }
}
