<?php

namespace App\Services\Alertas;

use App\Models\AlertDispatch;
use App\Models\AlertDispatchRecipient;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Envia o alerta de uma publicação e registra o disparo na Central de alertas.
 *
 * É o mesmo caminho para o envio feito na hora (lista de publicações) e para
 * o alerta programado na agenda: as regras de quem recebe não mudam.
 */
class DisparaAlerta
{
    public function __construct(private readonly AlertSender $sender)
    {
    }

    /** Há alguém para receber? Sem ninguém autorizado, não existe disparo. */
    public function temDestinatario(): bool
    {
        return User::alcancaveisPorAlerta()->get()->contains->autorizouAlertas();
    }

    /**
     * Destinatário é usuário do sistema: conta ativa com telefone. Quem não
     * autorizou entra no histórico como "ignorado", para o ADM ver quem ficou
     * de fora e por quê.
     *
     * Devolve null quando ninguém autorizou: nada é enviado nem registrado.
     */
    public function executar(Postagem $postagem, AlertTemplate $modelo, User $autor): ?AlertDispatch
    {
        $usuarios = User::alcancaveisPorAlerta()->orderBy('nome')->get();
        $aptos = $usuarios->filter->autorizouAlertas()->values();

        if ($aptos->isEmpty()) {
            return null;
        }

        $mensagem = self::montarMensagem($modelo->mensagem, $postagem);
        $parametros = self::parametrosTemplate($postagem);

        return DB::transaction(function () use ($postagem, $modelo, $autor, $usuarios, $aptos, $mensagem, $parametros) {
            $disparo = AlertDispatch::create([
                'postagem_id' => $postagem->id,
                'alert_template_id' => $modelo->id,
                'user_id' => $autor->id,
                'mensagem' => $mensagem,
                'status' => 'sucesso',
                'total_destinatarios' => $aptos->count(),
                'total_enviados' => 0,
                'total_falhas' => 0,
            ]);

            $enviados = 0;
            $falhas = 0;

            foreach ($usuarios as $usuario) {
                if (! $usuario->autorizouAlertas()) {
                    AlertDispatchRecipient::create([
                        'alert_dispatch_id' => $disparo->id,
                        'user_id' => $usuario->id,
                        'nome' => $usuario->nome,
                        'telefone' => $usuario->telefone,
                        'status' => 'ignorado',
                        'resultado' => 'Não autorizou receber alertas.',
                        'tentativas' => 0,
                    ]);
                    continue;
                }

                $resultado = $this->sender->send($usuario->telefone, $mensagem, $parametros);

                if ($resultado['status'] === 'enviado') {
                    $enviados++;
                } else {
                    $falhas++;
                }

                AlertDispatchRecipient::create([
                    'alert_dispatch_id' => $disparo->id,
                    'user_id' => $usuario->id,
                    'nome' => $usuario->nome,
                    'telefone' => $usuario->telefone,
                    'status' => $resultado['status'],
                    'resultado' => $resultado['resultado'],
                ] + self::dadosMeta($resultado));
            }

            $status = $falhas === 0 ? 'sucesso' : ($enviados > 0 ? 'parcial' : 'falha');

            $disparo->update([
                'status' => $status,
                'total_enviados' => $enviados,
                'total_falhas' => $falhas,
            ]);

            $postagem->update(['alerta_enviado' => true]);

            return $disparo;
        });
    }

    /** Parâmetros do template da Meta: {{1}} título, {{2}} tipo, {{3}} data. */
    public static function parametrosTemplate(Postagem $postagem): array
    {
        return [
            $postagem->titulo,
            $postagem->rotuloTipo(),
            $postagem->data_publicacao->format('d/m/Y'),
        ];
    }

    /** O que o envio devolveu para o rastreio pela Meta (vazio no simulado). */
    public static function dadosMeta(array $resultado): array
    {
        return [
            'wamid' => $resultado['wamid'] ?? null,
            'status_meta' => null,
            'erro_codigo' => $resultado['erro_codigo'] ?? null,
            'erro_mensagem' => $resultado['erro_mensagem'] ?? null,
        ];
    }

    public static function montarMensagem(string $modelo, Postagem $postagem): string
    {
        return strtr($modelo, [
            '{{titulo}}' => $postagem->titulo,
            '{{tipo}}' => $postagem->rotuloTipo(),
            '{{data_publicacao}}' => $postagem->data_publicacao->format('d/m/Y'),
        ]);
    }
}
