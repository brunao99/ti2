<?php

namespace App\Support;

/**
 * URL de arquivo de public/ com a versão no endereço (?v=data de alteração).
 *
 * O servidor entrega style.css e app.js sem cabeçalho de cache, então o
 * navegador decide sozinho por quanto tempo reaproveitar a cópia antiga.
 * Depois de um deploy, isso deixava telas novas sem o CSS delas. Com a versão
 * no endereço, todo arquivo alterado vira um endereço novo e é baixado de novo.
 */
class Asset
{
    public static function url(string $caminho): string
    {
        $arquivo = public_path($caminho);
        $versao = is_file($arquivo) ? filemtime($arquivo) : null;

        return asset($caminho) . ($versao ? '?v=' . $versao : '');
    }
}
