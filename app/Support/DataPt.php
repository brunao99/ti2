<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Nomes de dias e meses em português. Ficam aqui, e não no Carbon, porque o
 * idioma do Carbon depende da configuração do servidor.
 */
final class DataPt
{
    public const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    /** Índice igual ao dayOfWeek do Carbon: 0 é domingo. */
    public const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    public const DIAS_ABREV = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

    /** "novembro" */
    public static function mes(CarbonInterface $data): string
    {
        return self::MESES[$data->month - 1];
    }

    /** "Novembro 2026" ou, com $de, "Novembro de 2026" */
    public static function mesAno(CarbonInterface $data, bool $de = false): string
    {
        return mb_convert_case(self::mes($data), MB_CASE_TITLE) . ($de ? ' de ' : ' ') . $data->year;
    }

    /** "quarta" */
    public static function dia(CarbonInterface $data): string
    {
        return self::DIAS[$data->dayOfWeek];
    }

    /** "Quarta-feira" (sábado e domingo ficam sem "-feira") */
    public static function diaCompleto(CarbonInterface $data): string
    {
        $dia = self::dia($data);
        $nome = in_array($data->dayOfWeek, [0, 6], true) ? $dia : $dia . '-feira';

        return mb_strtoupper(mb_substr($nome, 0, 1)) . mb_substr($nome, 1);
    }

    /** "Hoje", "Amanhã" ou "Sex, 06/11", como nos próximos alertas. */
    public static function relativo(CarbonInterface $dia, CarbonInterface $hoje): string
    {
        if ($dia->isSameDay($hoje)) {
            return 'Hoje';
        }

        if ($dia->isSameDay($hoje->copy()->addDay())) {
            return 'Amanhã';
        }

        $abrev = self::DIAS_ABREV[$dia->dayOfWeek];

        return mb_strtoupper(mb_substr($abrev, 0, 1)) . mb_substr($abrev, 1) . ', ' . $dia->format('d/m');
    }

    /** "Quarta-feira, 4 de novembro" ou, com o ano, "… de 2026" */
    public static function extenso(CarbonInterface $data, bool $comAno = false): string
    {
        return self::diaCompleto($data) . ', ' . $data->day . ' de ' . self::mes($data)
            . ($comAno ? ' de ' . $data->year : '');
    }
}
