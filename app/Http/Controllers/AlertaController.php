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

        return view('alertas.index', compact('disparos'));
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

        $disparo = DB::transaction(function () use (
            $postagem, $modelo, $aptos, $usuarios, $mensagem, $request, $sender
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
                    ]);
                    continue;
                }

                $resultado = $sender->send($usuario->telefone, $mensagem);

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
                ]);
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

    private function montarMensagem(string $modelo, Postagem $postagem): string
    {
        return strtr($modelo, [
            '{{titulo}}' => $postagem->titulo,
            '{{tipo}}' => $postagem->rotuloTipo(),
            '{{data_publicacao}}' => $postagem->data_publicacao->format('d/m/Y'),
        ]);
    }
}
