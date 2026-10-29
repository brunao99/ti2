<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Foto de perfil: recebe a imagem enviada, recorta no centro em quadrado,
 * reduz para 512px e grava como JPEG no disco local (storage/app/private).
 *
 * Regravar a imagem, em vez de guardar o arquivo enviado, deixa todas do
 * mesmo tamanho e descarta o que não é imagem (metadados, localização do
 * GPS, conteúdo escondido no arquivo). O disco local não é público: a foto
 * é entregue por uma rota que exige login (ContaController::foto).
 */
class FotoPerfil
{
    public const TIPOS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Tamanho máximo do arquivo enviado, em KB. */
    public const MAX_KB = 2048;

    public const LADO = 512;

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
        $quadrada = self::recortar($imagem);

        ob_start();
        imagejpeg($quadrada, null, 85);
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

    /** Recorte central quadrado, reduzido para LADO x LADO, sobre fundo branco. */
    private static function recortar(\GdImage $imagem): \GdImage
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $lado = min($largura, $altura);
        $destino = min(self::LADO, $lado);

        $quadrada = imagecreatetruecolor($destino, $destino);
        imagefill($quadrada, 0, 0, imagecolorallocate($quadrada, 255, 255, 255)); // PNG transparente vira fundo branco
        imagecopyresampled($quadrada, $imagem, 0, 0,
            intdiv($largura - $lado, 2), intdiv($altura - $lado, 2), $destino, $destino, $lado, $lado);

        return $quadrada;
    }
}
