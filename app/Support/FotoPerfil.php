<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Foto de perfil: recebe a imagem enviada e grava inteira, sem corte e nas
 * proporções originais, como JPEG no disco local (storage/app/private).
 *
 * O círculo dos avatares é só visual (CSS, object-fit: cover). A imagem
 * guardada é a completa, e é ela que a visualização ampliada mostra.
 *
 * Regravar a imagem, em vez de guardar o arquivo enviado, limita o tamanho
 * e descarta o que não é imagem (metadados, localização do GPS, conteúdo
 * escondido no arquivo). O disco local não é público: a foto é entregue
 * por uma rota que exige login (ContaController::foto).
 */
class FotoPerfil
{
    public const TIPOS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Tamanho máximo do arquivo enviado, em KB. */
    public const MAX_KB = 2048;

    /** Maior lado da imagem gravada, em pixels. Acima disso ela é reduzida, sem cortar. */
    public const LADO = 1600;

    private const PASTA = 'fotos';

    public static function disco()
    {
        return Storage::disk('local');
    }

    /** Grava a nova foto e apaga a anterior. */
    public static function salvar(User $usuario, UploadedFile $arquivo): void
    {
        $imagem = @imagecreatefromstring((string) file_get_contents($arquivo->getRealPath()));
        if ($imagem === false) {
            throw new RuntimeException('Não foi possível ler a imagem.');
        }

        $imagem = self::endireitar($imagem, $arquivo->getRealPath());
        $completa = self::ajustar($imagem);

        ob_start();
        imagejpeg($completa, null, 85);
        $jpeg = ob_get_clean();

        $caminho = self::PASTA . '/' . $usuario->id . '-' . Str::random(12) . '.jpg';
        self::disco()->put($caminho, $jpeg);

        $anterior = $usuario->foto;
        $usuario->forceFill(['foto' => $caminho])->save();

        if ($anterior && $anterior !== $caminho) {
            self::disco()->delete($anterior);
        }
    }

    public static function remover(User $usuario): void
    {
        if ($usuario->foto) {
            self::disco()->delete($usuario->foto);
        }
        $usuario->forceFill(['foto' => null])->save();
    }

    /** Foto de celular costuma vir deitada, com a rotação só no EXIF. */
    private static function endireitar(\GdImage $imagem, string $caminho): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagem;
        }

        $exif = @exif_read_data($caminho);
        $angulo = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angulo ? imagerotate($imagem, $angulo, 0) : $imagem;
    }

    /**
     * Imagem inteira, nas mesmas proporções, sobre fundo branco. Só diminui
     * quando o maior lado passa de LADO; nunca corta nem amplia.
     */
    private static function ajustar(\GdImage $imagem): \GdImage
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $escala = min(1, self::LADO / max($largura, $altura));
        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));

        $completa = imagecreatetruecolor($novaLargura, $novaAltura);
        imagefill($completa, 0, 0, imagecolorallocate($completa, 255, 255, 255)); // PNG transparente vira fundo branco
        imagecopyresampled($completa, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

        return $completa;
    }
}
