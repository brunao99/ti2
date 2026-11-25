<?php

namespace App\Services\Alertas;

use App\Models\Postagem;
use Illuminate\Support\Facades\URL;

/**
 * Imagem do alerta de WhatsApp, no formato de story (900 × 1600):
 * ícone de alerta, "COMUNICADO IMPORTANTE", o título da publicação em azul,
 * o resumo do alerta (datas em negrito) e a marca da Algoritmos e Dados.
 *
 * Gerada com a GD do PHP e fontes que vão no projeto (resources/alertas),
 * para sair igual no Railway e no cPanel sem depender de serviço externo.
 */
class ImagemAlerta
{
    private const LARGURA = 900;
    private const ALTURA = 1600;
    private const AZUL = [11, 71, 168];
    private const TINTA = [17, 17, 17];

    private const MESES = 'janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro';

    /**
     * Endereço público e assinado da imagem, que o provedor de WhatsApp
     * baixa na hora do envio. Null quando o envio com imagem está desligado
     * (ALERTA_IMAGEM).
     */
    public static function url(Postagem $postagem): ?string
    {
        if (! config('services.alertas.imagem')) {
            return null;
        }

        return URL::temporarySignedRoute('alertas.imagem', now()->addDays(7), ['postagem' => $postagem->id]);
    }

    /** PNG da imagem do alerta desta publicação. */
    public function gerar(Postagem $postagem): string
    {
        $img = imagecreatetruecolor(self::LARGURA, self::ALTURA);
        imagealphablending($img, true);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $azul = imagecolorallocate($img, ...self::AZUL);
        $tinta = imagecolorallocate($img, ...self::TINTA);

        $icone = imagecreatefrompng($this->arquivo('icone.png'));
        $logo = imagecreatefrompng($this->arquivo('logo.png'));
        [$iconeL, $iconeA] = $this->escala($icone, 200);
        [$logoL, $logoA] = $this->escala($logo, 430);

        // Título da publicação: até 4 linhas; a fonte diminui se precisar.
        $titulo = mb_strtoupper(trim((string) $postagem->titulo));
        [$tamTitulo, $linhasTitulo] = $this->ajustar(
            fn (float $t) => $this->quebrar($this->palavras($titulo, false), $t, 740, 'InterTight-700.ttf', 'InterTight-700.ttf'),
            33, 24, fn (array $linhas) => count($linhas) <= 4
        );

        // Resumo: diminui até caber no espaço que sobra na imagem.
        $resumo = $postagem->resumoParaAlerta();
        $fixo = 90 + $iconeA + 30 + 76 + 80 + 64
            + count($linhasTitulo) * $this->alturaLinha($tamTitulo, 1.12) + 44 + 80 + $logoA + 90;
        $espaco = self::ALTURA - $fixo;
        [$tamResumo, $linhasResumo] = $this->ajustar(
            fn (float $t) => $this->quebrar($this->palavras($resumo, true), $t, 730, 'OpenSans-400.ttf', 'OpenSans-700.ttf'),
            26, 17, fn (array $linhas, float $t) => count($linhas) * $this->alturaLinha($t, 1.45) <= $espaco
        );

        $alturaTitulo = count($linhasTitulo) * $this->alturaLinha($tamTitulo, 1.12);
        $alturaResumo = count($linhasResumo) * $this->alturaLinha($tamResumo, 1.45);
        $total = $iconeA + 30 + 76 + 80 + 64 + $alturaTitulo + ($resumo !== '' ? 44 + $alturaResumo : 0) + 80 + $logoA;

        // Bloco centralizado na vertical, um pouco puxado para cima.
        $y = (int) max(70, (self::ALTURA - $total) * 0.42);

        imagecopyresampled($img, $icone, (int) ((self::LARGURA - $iconeL) / 2), $y, 0, 0, $iconeL, $iconeA, imagesx($icone), imagesy($icone));
        $y += $iconeA + 30;

        $this->linhaCentralizada($img, 'COMUNICADO', 50, $y + 62, $azul, 'InterTight-500.ttf');
        $y += 76;
        $this->linhaCentralizada($img, 'IMPORTANTE', 53, $y + 66, $azul, 'InterTight-800Italic.ttf');
        $y += 80 + 64;

        foreach ($linhasTitulo as $linha) {
            $y += $this->alturaLinha($tamTitulo, 1.12);
            $this->desenharLinha($img, $linha, $tamTitulo, $y - (int) ($this->alturaLinha($tamTitulo, 1.12) * 0.22), $azul, 'InterTight-700.ttf', 'InterTight-700.ttf');
        }

        if ($resumo !== '') {
            $y += 44;
            foreach ($linhasResumo as $linha) {
                $y += $this->alturaLinha($tamResumo, 1.45);
                $this->desenharLinha($img, $linha, $tamResumo, $y - (int) ($this->alturaLinha($tamResumo, 1.45) * 0.3), $tinta, 'OpenSans-400.ttf', 'OpenSans-700.ttf');
            }
        }

        $y += 80;
        imagecopyresampled($img, $logo, (int) ((self::LARGURA - $logoL) / 2), $y, 0, 0, $logoL, $logoA, imagesx($logo), imagesy($logo));

        ob_start();
        imagepng($img, null, 6);

        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------ apoio

    /** Palavras do texto; com $negrito, as datas ficam marcadas para o negrito. */
    private function palavras(string $texto, bool $negrito): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));
        if ($texto === '') {
            return [];
        }

        $trechos = [];
        if ($negrito) {
            $padroes = [
                '/\b\d{1,2}º?\s+de\s+(?:' . self::MESES . ')(?:\s+de\s+\d{4})?/iu',
                '/\b\d{1,2}\/\d{1,2}(?:\/\d{2,4})?\b/u',
            ];
            foreach ($padroes as $padrao) {
                preg_match_all($padrao, $texto, $achados, PREG_OFFSET_CAPTURE);
                foreach ($achados[0] as [$trecho, $inicio]) {
                    $trechos[] = [$inicio, $inicio + strlen($trecho)];
                }
            }
        }

        $palavras = [];
        $pos = 0;
        foreach (explode(' ', $texto) as $palavra) {
            $fim = $pos + strlen($palavra);
            $forte = false;
            foreach ($trechos as [$ini, $fimTrecho]) {
                if ($pos < $fimTrecho && $fim > $ini) {
                    $forte = true;
                    break;
                }
            }
            $palavras[] = [$palavra, $forte];
            $pos = $fim + 1;
        }

        return $palavras;
    }

    /** Quebra em linhas que cabem na largura. Cada linha: lista de [palavra, negrito]. */
    private function quebrar(array $palavras, float $tamanho, int $largura, string $fonte, string $fonteForte): array
    {
        $linhas = [];
        $atual = [];
        foreach ($palavras as $palavra) {
            $tentativa = [...$atual, $palavra];
            if ($atual && $this->larguraLinha($tentativa, $tamanho, $fonte, $fonteForte) > $largura) {
                $linhas[] = $atual;
                $atual = [$palavra];
            } else {
                $atual = $tentativa;
            }
        }
        if ($atual) {
            $linhas[] = $atual;
        }

        return $linhas;
    }

    /** Maior tamanho de fonte (de $maior a $menor) em que o resultado cabe. */
    private function ajustar(callable $quebrar, float $maior, float $menor, callable $cabe): array
    {
        for ($tamanho = $maior; $tamanho > $menor; $tamanho -= 1) {
            $linhas = $quebrar($tamanho);
            if ($cabe($linhas, $tamanho)) {
                return [$tamanho, $linhas];
            }
        }

        return [$menor, $quebrar($menor)];
    }

    private function larguraLinha(array $linha, float $tamanho, string $fonte, string $fonteForte): int
    {
        $total = 0;
        foreach ($linha as $i => [$palavra, $forte]) {
            $total += $this->larguraTexto($palavra, $tamanho, $forte ? $fonteForte : $fonte);
            if ($i > 0) {
                $total += $this->larguraEspaco($tamanho, $fonte);
            }
        }

        return $total;
    }

    private function desenharLinha($img, array $linha, float $tamanho, int $base, int $cor, string $fonte, string $fonteForte): void
    {
        $x = (int) ((self::LARGURA - $this->larguraLinha($linha, $tamanho, $fonte, $fonteForte)) / 2);
        foreach ($linha as $i => [$palavra, $forte]) {
            if ($i > 0) {
                $x += $this->larguraEspaco($tamanho, $fonte);
            }
            $arquivo = $forte ? $fonteForte : $fonte;
            imagettftext($img, $tamanho, 0, $x, $base, $cor, $this->arquivo('fontes/' . $arquivo), $palavra);
            $x += $this->larguraTexto($palavra, $tamanho, $arquivo);
        }
    }

    private function linhaCentralizada($img, string $texto, float $tamanho, int $base, int $cor, string $fonte): void
    {
        $x = (int) ((self::LARGURA - $this->larguraTexto($texto, $tamanho, $fonte)) / 2);
        imagettftext($img, $tamanho, 0, $x, $base, $cor, $this->arquivo('fontes/' . $fonte), $texto);
    }

    private function larguraTexto(string $texto, float $tamanho, string $fonte): int
    {
        $caixa = imagettfbbox($tamanho, 0, $this->arquivo('fontes/' . $fonte), $texto);

        return (int) ($caixa[2] - $caixa[0]);
    }

    private function larguraEspaco(float $tamanho, string $fonte): int
    {
        // O espaço medido pela GD sai estreito demais; 28% do corpo da fonte
        // fica próximo do que um editor de texto usaria.
        return (int) max(
            $this->larguraTexto('a a', $tamanho, $fonte) - $this->larguraTexto('aa', $tamanho, $fonte),
            round($tamanho * 96 / 72 * 0.28)
        );
    }

    /** Altura de uma linha em pixels (a GD mede a fonte em pontos, a 96 dpi). */
    private function alturaLinha(float $tamanho, float $entrelinha): int
    {
        return (int) round($tamanho * 96 / 72 * $entrelinha);
    }

    /** [largura, altura] de uma imagem redimensionada para a largura dada. */
    private function escala($imagem, int $largura): array
    {
        return [$largura, (int) round(imagesy($imagem) * $largura / imagesx($imagem))];
    }

    private function arquivo(string $nome): string
    {
        return resource_path('alertas/' . $nome);
    }
}
