<?php

namespace App\Http\Controllers;

use App\Models\AlertDispatch;
use App\Models\AlertDispatchRecipient;
use App\Models\AlertTemplate;
use App\Models\Postagem;
use App\Models\User;
use App\Services\Alertas\AlertSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AlertaController extends Controller
{
    public function index(): View
    {
        $disparos = AlertDispatch::with(['postagem', 'modelo', 'administrador'])
            ->latest()
            ->get();

        // Medidor: conta mensagens (destinatários), não disparos. Ignorados
        // não entram na taxa — para eles nada foi tentado.
        $porStatus = AlertDispatchRecipient::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $enviados = (int) ($porStatus['enviado'] ?? 0);
        $falhas = (int) ($porStatus['falha'] ?? 0);
        $tentados = $enviados + $falhas;

        $medidor = [
            'enviados'   => $enviados,
            'falhas'     => $falhas,
            'ignorados'  => (int) ($porStatus['ignorado'] ?? 0),
            'taxa'       => $tentados ? round($enviados / $tentados * 100, 1) : null,
            'pct_envio'  => $tentados ? $enviados / $tentados * 100 : 0,
            'disparos'   => $disparos->count(),
            'pendentes'  => $disparos->filter->temFalhas()->count(),
        ];

        return view('alertas.index', compact('disparos', 'medidor'));
    }

    /**
     * Reenvia só para quem falhou. A autorização é conferida de novo: quem
     * desautorizou, foi desativado ou removido depois do disparo não recebe,
     * e passa a constar como ignorado.
     */
    public function reenviar(AlertDispatch $disparo, AlertSender $sender): RedirectResponse
    {
        abort_unless($disparo->temFalhas(), 409, 'Este disparo não tem falhas para reenviar.');

        [$enviados, $falhas] = DB::transaction(function () use ($disparo, $sender) {
            // Trava o disparo: dois cliques seguidos não reenviam em dobro.
            $disparo = AlertDispatch::lockForUpdate()->findOrFail($disparo->id);

            $pendentes = $disparo->destinatarios()->where('status', 'falha')->with('usuario')->get();
            $parametros = $this->parametrosTemplate($disparo->postagem);
            $enviados = 0;
            $falhas = 0;

            foreach ($pendentes as $destinatario) {
                $usuario = $destinatario->usuario;

                if (! $usuario || ! $usuario->ativo || ! $usuario->autorizouAlertas()) {
                    $destinatario->update([
                        'status' => 'ignorado',
                        'resultado' => 'Não reenviado: a conta não está mais ativa ou autorizada.',
                    ]);
                    continue;
                }

                $resultado = $sender->send($usuario->telefone, $disparo->mensagem, $parametros);
                $resultado['status'] === 'enviado' ? $enviados++ : $falhas++;

                // Nova tentativa, novo wamid: os eventos do envio anterior
                // deixam de encontrar este destinatário.
                $destinatario->update([
                    'telefone' => $usuario->telefone,
                    'status' => $resultado['status'],
                    'resultado' => $resultado['resultado'],
                    'tentativas' => $destinatario->tentativas + 1,
                ] + $this->dadosMeta($resultado));
            }

            $disparo->recalcularTotais();

            return [$enviados, $falhas];
        });

        $mensagem = $falhas === 0
            ? "Reenvio concluído: {$enviados} entregue(s)."
            : "Reenvio concluído: {$enviados} entregue(s), {$falhas} ainda com falha.";

        return back()->with('ok', $mensagem);
    }

    /**
     * Pedido via JavaScript (janela sobre a lista de publicações) recebe só o
     * formulário; acesso direto recebe a página inteira, que é a reserva.
     */
    public function enviar(Request $request, Postagem $postagem): View
    {
        abort_unless($postagem->situacao === 'publicado', 404);
        abort_if($postagem->alerta_enviado, 409, 'Esta publicação já possui um alerta enviado.');

        $dados = [
            'postagem' => $postagem,
            'modelos' => AlertTemplate::orderBy('nome')->get(),
            'destinatarios' => User::alcancaveisPorAlerta()->orderBy('nome')->get(),
        ];

        return $request->ajax()
            ? view('alertas._envio', $dados + ['modal' => true])
            : view('alertas.enviar', $dados);
    }

    public function preview(Request $request, Postagem $postagem): RedirectResponse
    {
        abort_unless($postagem->situacao === 'publicado', 404);
        abort_if($postagem->alerta_enviado, 409, 'Esta publicação já possui um alerta enviado.');

        $dados = $request->validate([
            'alert_template_id' => ['required', 'exists:alert_templates,id'],
        ]);

        return redirect()
            ->route('alertas.enviar', $postagem)
            ->withInput($dados);
    }

    public function disparar(Request $request, Postagem $postagem, AlertSender $sender): RedirectResponse
    {
        abort_unless($postagem->situacao === 'publicado', 404);
        abort_if($postagem->alerta_enviado, 409, 'Esta publicação já possui um alerta enviado.');

        $dados = $request->validate([
            'alert_template_id' => ['required', 'exists:alert_templates,id'],
        ]);

        $modelo = AlertTemplate::findOrFail($dados['alert_template_id']);

        // Destinatário é usuário do sistema: conta ativa com telefone. Quem
        // não autorizou entra no histórico como "ignorado", para o ADM ver
        // quem ficou de fora e por quê.
        $usuarios = User::alcancaveisPorAlerta()->orderBy('nome')->get();
        $aptos = $usuarios->filter->autorizouAlertas()->values();

        // Volta para a página de envio, e não para "a anterior": vindo da
        // janela, a anterior é a lista, onde o erro ficaria sem contexto.
        if ($aptos->isEmpty()) {
            return redirect()->route('alertas.enviar', $postagem)->withErrors([
                'alerta' => 'Nenhum usuário autorizou receber alertas. A autorização é feita por cada um, na tela Minha conta.',
            ])->withInput();
        }

        $mensagem = $this->montarMensagem($modelo->mensagem, $postagem);
        $parametros = $this->parametrosTemplate($postagem);

        $disparo = DB::transaction(function () use (
            $postagem, $modelo, $aptos, $usuarios, $mensagem, $parametros, $request, $sender
        ) {
            $disparo = AlertDispatch::create([
                'postagem_id' => $postagem->id,
                'alert_template_id' => $modelo->id,
                'user_id' => $request->user()->id,
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

                $resultado = $sender->send($usuario->telefone, $mensagem, $parametros);

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
                ] + $this->dadosMeta($resultado));
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

        return redirect()
            ->route('alertas.index')
            ->with('ok', "Alerta enviado e registrado. {$disparo->total_enviados} destinatário(s) processado(s).");
    }

    public function modelos(): View
    {
        return view('alertas.modelos.index', [
            'modelos' => AlertTemplate::orderBy('nome')->get(),
        ]);
    }

    public function criarModelo(): View
    {
        return view('alertas.modelos.form', ['modelo' => new AlertTemplate()]);
    }

    public function salvarModelo(Request $request, ?AlertTemplate $modelo = null): RedirectResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'mensagem' => ['required', 'string', 'max:4000'],
        ]);

        $modelo ? $modelo->update($dados) : AlertTemplate::create($dados);

        return redirect()->route('alertas.modelos')->with('ok', 'Modelo de mensagem salvo.');
    }

    public function show(AlertDispatch $disparo): View
    {
        $disparo->load(['postagem', 'modelo', 'administrador', 'destinatarios']);

        return view('alertas.show', compact('disparo'));
    }

    /** Parâmetros do template da Meta: {{1}} título, {{2}} tipo, {{3}} data. */
    private function parametrosTemplate(Postagem $postagem): array
    {
        return [
            $postagem->titulo,
            $postagem->rotuloTipo(),
            $postagem->data_publicacao->format('d/m/Y'),
        ];
    }

    /** O que o envio devolveu para o rastreio pela Meta (vazio no simulado). */
    private function dadosMeta(array $resultado): array
    {
        return [
            'wamid' => $resultado['wamid'] ?? null,
            'status_meta' => null,
            'erro_codigo' => $resultado['erro_codigo'] ?? null,
            'erro_mensagem' => $resultado['erro_mensagem'] ?? null,
        ];
    }

    private function montarMensagem(string $modelo, Postagem $postagem): string
    {
        return strtr($modelo, [
            '{{titulo}}' => $postagem->titulo,
            '{{tipo}}' => $postagem->rotuloTipo(),
            '{{data_publicacao}}' => $postagem->data_publicacao->format('d/m/Y'),
        ]);
    }
}
