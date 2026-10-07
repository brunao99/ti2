<?php

namespace App\Support;

/**
 * Telefone de WhatsApp no formato que a API da Meta exige: só dígitos, com
 * DDI. É assim que vai para o banco; a máscara existe só na tela.
 *
 * Aceita o que a pessoa digitar — "(31) 99999-0001", "+55 31 99999-0001",
 * "31999990001" — e devolve "5531999990001". Número sem DDI é tratado como
 * brasileiro, porque todo destinatário hoje é prefeitura.
 */
class Telefone
{
    /** DDI 55 + DDD + 8 dígitos (fixo) ou 9 (celular). */
    public const FORMATO = '/^55\d{10,11}$/';

    public static function normalizar(?string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);

        if ($digitos === '') {
            return null;
        }

        // Sem DDI: DDD + número, 10 ou 11 dígitos.
        if (strlen($digitos) <= 11) {
            $digitos = '55' . $digitos;
        }

        return $digitos;
    }

    public static function valido(?string $normalizado): bool
    {
        return $normalizado !== null && preg_match(self::FORMATO, $normalizado) === 1;
    }

    /**
     * 5531999990001 → +55 (31) 99999-0001, ou (31) 99999-0001 sem o DDI,
     * para onde o espaço é curto e todo número é brasileiro (listagens).
     */
    public static function formatar(?string $normalizado, bool $comDdi = true): string
    {
        if (! self::valido($normalizado)) {
            return (string) $normalizado;
        }

        $ddd = substr($normalizado, 2, 2);
        $numero = substr($normalizado, 4);
        $corte = strlen($numero) - 4;

        $local = sprintf('(%s) %s-%s', $ddd, substr($numero, 0, $corte), substr($numero, $corte));

        return $comDdi ? '+55 ' . $local : $local;
    }
}
