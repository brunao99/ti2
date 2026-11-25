<?php

namespace App\Support;

/**
 * Resumo padrão do alerta: o começo do texto da publicação, sem formatação,
 * cortado no fim de uma frase para caber no centro da imagem.
 *
 * O editor da publicação faz a mesma conta em JavaScript (form de
 * publicação) para mostrar o padrão enquanto a pessoa escreve.
 */
class ResumoAlerta
{
    public const LIMITE = 450;

    /** Tamanho máximo aceito quando a pessoa escreve o próprio resumo. */
    public const MAXIMO = 700;

    public static function padrao(?string $html, int $limite = self::LIMITE): string
    {
        $texto = self::textoPuro($html);

        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        // Junta frases inteiras enquanto couberem.
        $frases = preg_split('/(?<=[.!?])\s+/u', $texto) ?: [];
        $resumo = '';
        foreach ($frases as $frase) {
            $tentativa = $resumo === '' ? $frase : $resumo . ' ' . $frase;
            if (mb_strlen($tentativa) > $limite) {
                break;
            }
            $resumo = $tentativa;
        }

        if ($resumo !== '') {
            return $resumo;
        }

        // A primeira frase sozinha já passa do limite: corta na última palavra inteira.
        $corte = mb_substr($texto, 0, $limite);
        $espaco = mb_strrpos($corte, ' ');

        return rtrim($espaco ? mb_substr($corte, 0, $espaco) : $corte, " ,;:-–") . '…';
    }

    /** HTML do editor → texto corrido, com espaço onde havia quebra de bloco. */
    public static function textoPuro(?string $html): string
    {
        $html = preg_replace('#<\s*(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', ' ', (string) $html);
        $texto = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $texto));
    }
}
