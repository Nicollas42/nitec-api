<?php

use Illuminate\Support\Facades\Route;

$frontend_dist = realpath(base_path('../nitec_app/dist'));

Route::get('/{path?}', function (?string $path = null) use ($frontend_dist) {
    abort_unless($frontend_dist && is_dir($frontend_dist), 500, 'Build do frontend nao encontrada.');

    $path = trim($path ?? '', '/');
    $index_path = $frontend_dist . DIRECTORY_SEPARATOR . 'index.html';
    $debug_ativo = request()->boolean('debug');

    $responder_index = static function () use ($index_path, $debug_ativo) {
        abort_unless(is_file($index_path), 500, 'index.html do frontend nao encontrado.');

        $conteudo = file_get_contents($index_path);
        abort_unless($conteudo !== false, 500, 'Nao foi possivel ler o build do frontend.');

        // Em producao web, forca caminhos absolutos mesmo com build "./" do Vite.
        $conteudo = preg_replace('/<head>/i', "<head>\n    <base href=\"/\">", $conteudo, 1);
        $conteudo = str_replace(['href="./', 'src="./'], ['href="/', 'src="/'], $conteudo);

        if ($debug_ativo) {
            $script_debug = <<<'HTML'
<script>
(function () {
    var painel = document.createElement('div');
    painel.id = 'nitec-debug-bootstrap';
    painel.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:99999;padding:12px 14px;background:#111827;color:#f8fafc;border:1px solid #334155;border-radius:14px;font:12px/1.4 monospace;box-shadow:0 20px 40px rgba(15,23,42,.35);white-space:pre-wrap;word-break:break-word;';
    painel.textContent = '[DEBUG BOOTSTRAP]\\nURL: ' + window.location.href + '\\nUA: ' + navigator.userAgent;
    document.addEventListener('DOMContentLoaded', function () {
        document.body.appendChild(painel);
    });
    window.addEventListener('error', function (event) {
        painel.textContent += '\\n\\n[window.error] ' + (event.message || 'Erro sem mensagem');
        if (event.filename) painel.textContent += '\\nArquivo: ' + event.filename + ':' + event.lineno + ':' + event.colno;
    });
    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var mensagem = '';
        if (reason && typeof reason === 'object') {
            mensagem = reason.stack || reason.message || JSON.stringify(reason);
        } else {
            mensagem = String(reason || 'Promise rejeitada sem detalhe');
        }
        painel.textContent += '\\n\\n[unhandledrejection] ' + mensagem;
    });
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function (regs) {
            painel.textContent += '\\n\\n[serviceWorker] registros encontrados: ' + regs.length;
            return Promise.all(regs.map(function (reg) { return reg.unregister(); }));
        }).then(function () {
            painel.textContent += '\\n[serviceWorker] registros removidos para diagnostico';
        }).catch(function (erro) {
            painel.textContent += '\\n[serviceWorker] falha ao remover registros: ' + (erro && erro.message ? erro.message : erro);
        });
    }
    if ('caches' in window) {
        caches.keys().then(function (keys) {
            painel.textContent += '\\n[caches] entradas encontradas: ' + keys.length;
            return Promise.all(keys.map(function (key) { return caches.delete(key); }));
        }).then(function () {
            painel.textContent += '\\n[caches] cache limpo para diagnostico';
        }).catch(function (erro) {
            painel.textContent += '\\n[caches] falha ao limpar cache: ' + (erro && erro.message ? erro.message : erro);
        });
    }
})();
</script>
HTML;

            $conteudo = str_ireplace('</body>', $script_debug . "\n</body>", $conteudo);
        }

        return response($conteudo, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    };

    if ($path === '') {
        return $responder_index();
    }

    $arquivo_solicitado = realpath($frontend_dist . DIRECTORY_SEPARATOR . $path);

    $detectar_content_type = static function (string $arquivo): ?string {
        return match (strtolower(pathinfo($arquivo, PATHINFO_EXTENSION))) {
            'js', 'mjs' => 'application/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'html' => 'text/html; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'webmanifest' => 'application/manifest+json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            default => null,
        };
    };

    if ($arquivo_solicitado
        && is_file($arquivo_solicitado)
        && str_starts_with($arquivo_solicitado, $frontend_dist)) {
        $headers = [];
        $nome_arquivo = basename($arquivo_solicitado);
        $content_type = $detectar_content_type($arquivo_solicitado);

        if ($content_type) {
            $headers['Content-Type'] = $content_type;
        }

        if (in_array($nome_arquivo, ['registerSW.js', 'sw.js', 'manifest.webmanifest'], true)) {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
            $headers['Pragma'] = 'no-cache';
            $headers['Expires'] = '0';
        }

        return response()->file($arquivo_solicitado, $headers);
    }

    return $responder_index();
})->where('path', '^(?!api(?:/|$)).*');
