<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IdempotenciaMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $uuid = $request->input('uuid_operacao');

        if (!$uuid) {
            return $next($request);
        }

        // 🟢 FORÇAMOS O USO DA DATABASE PARA EVITAR O ERRO "DOES NOT SUPPORT TAGGING"
        $store = Cache::store('database');
        $cacheKey = "tx_{$uuid}";

        if ($payload = $store->get($cacheKey)) {
            return response()->json($payload);
        }

        // Lock atómico guardado também no banco de dados central
        $lock = $store->lock("lock_{$uuid}", 10);

        if (!$lock->get()) {
            return response()->json([
                'sucesso' => false, 
                'mensagem' => 'Operação em processamento.'
            ], 409);
        }

        try {
            $response = $next($request);

            if ($response->isSuccessful() && method_exists($response, 'getData')) {
                // Guarda com expiração de 7 dias
                $store->put($cacheKey, $response->getData(true), now()->addDays(7));
            }

            return $response;
            
        } finally {
            $lock->release();
        }
    }
}