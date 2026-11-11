<?php

namespace App\Http\Requests;

use App\Models\AgendaEvento;
use App\Models\User;
use App\Services\Alertas\DisparaAlerta;
use App\Support\Recorrencia;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Janela "Novo evento" da agenda. O tipo escolhido decide quais campos valem;
 * os dos outros tipos são ignorados e gravados vazios.
 */
class SalvarEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->podeGerenciarPostagens();
    }

    protected function prepareForValidation(): void
    {
        // Sem repetição não existe "termina"; hora vazia vira null.
        $repete = $this->input('repetir', 'nao') !== 'nao';

        $this->merge([
            'repetir' => $this->input('repetir', 'nao'),
            'termina' => $repete ? $this->input('termina', 'nunca') : 'nunca',
            'hora_inicio' => $this->input('hora_inicio') ?: null,
            'hora_fim' => $this->input('hora_fim') ?: null,
        ]);
    }

    public function rules(): array
    {
        $tipo = $this->input('tipo');
        $ehCompromisso = $tipo === AgendaEvento::COMPROMISSO;
        $diaInteiro = $ehCompromisso && $this->boolean('dia_inteiro');

        $regras = [
            'tipo' => ['required', Rule::in(array_keys(AgendaEvento::TIPOS))],
            'data' => ['required', 'date_format:Y-m-d'],
            'repetir' => ['required', Rule::in(array_keys(Recorrencia::OPCOES))],
            'termina' => ['required', Rule::in(['nunca', 'data', 'vezes'])],
            'termina_em' => ['nullable', 'required_if:termina,data', 'date_format:Y-m-d', 'after_or_equal:data'],
            'termina_vezes' => ['nullable', 'required_if:termina,vezes', 'integer', 'min:1', 'max:500'],
            'visao' => ['nullable', Rule::in(['dia', 'semana', 'mes'])],
        ];

        return $regras + match ($tipo) {
            AgendaEvento::COMPROMISSO => [
                'titulo' => ['required', 'string', 'max:200'],
                'dia_inteiro' => ['nullable', 'boolean'],
                'hora_inicio' => [$diaInteiro ? 'nullable' : 'required', 'date_format:H:i'],
                'hora_fim' => [$diaInteiro ? 'nullable' : 'required', 'date_format:H:i', 'after:hora_inicio'],
                'local' => ['nullable', 'string', 'max:500'],
                'participantes' => ['nullable', 'array'],
                'participantes.*' => ['integer', Rule::exists('users', 'id')->whereIn('perfil', [User::MASTER, User::ADM])],
                'participantes_externos' => ['nullable', 'array', 'max:30'],
                'participantes_externos.*' => ['nullable', 'string', 'max:120'],
                'lembrar_antes' => ['nullable', Rule::in(array_keys(AgendaEvento::LEMBRAR))],
                'cliente_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('perfil', User::CLIENT)],
            ],
            AgendaEvento::ALERTA => [
                'postagem_id' => ['required', 'integer', Rule::exists('postagens', 'id')->where('situacao', 'publicado')],
                'alert_template_id' => ['required', 'integer', 'exists:alert_templates,id'],
                'hora_inicio' => ['required', 'date_format:H:i'],
            ],
            AgendaEvento::LEMBRETE => [
                'titulo' => ['required', 'string', 'max:200'],
                'hora_inicio' => ['required', 'date_format:H:i'],
                'visibilidade' => ['required', Rule::in(['eu', 'equipe'])],
                'observacao' => ['nullable', 'string', 'max:2000'],
            ],
            AgendaEvento::DATA_IMPORTANTE => [
                'titulo' => ['required', 'string', 'max:200'],
                'categoria' => ['required', Rule::in(array_keys(AgendaEvento::CATEGORIAS))],
                'avisar_antes' => ['nullable', Rule::in(AgendaEvento::AVISAR)],
                'publicacao_relacionada_id' => ['nullable', 'integer', 'exists:postagens,id'],
                'mostrar_equipe' => ['nullable', 'boolean'],
            ],
            default => [],
        };
    }

    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty() || $this->input('tipo') !== AgendaEvento::ALERTA) {
                return;
            }

            // Vale para alerta novo, ou quando a data ou a hora do envio mudaram:
            // editar só o modelo de uma série em andamento não esbarra aqui.
            $evento = $this->route('evento');
            $mudouQuando = ! $evento
                || $evento->data->format('Y-m-d') !== $this->input('data')
                || $evento->hora_inicio !== $this->input('hora_inicio');

            if ($mudouQuando) {
                $envio = CarbonImmutable::parse($this->input('data') . ' ' . $this->input('hora_inicio'), AgendaEvento::FUSO);
                $agora = AgendaEvento::agora();

                if ($envio->format('Y-m-d') < $agora->format('Y-m-d')) {
                    $validador->errors()->add('data', 'A data do envio não pode ser anterior a hoje.');
                } elseif ($envio->lte($agora)) {
                    $validador->errors()->add('hora_inicio', 'A hora do envio já passou. Escolha um horário mais à frente.');
                }
            }

            if (! app(DisparaAlerta::class)->temDestinatario()) {
                $validador->errors()->add('postagem_id',
                    'Nenhum usuário autorizou receber alertas. A autorização é feita por cada um, na tela Minha conta.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'tipo.*' => 'Escolha o tipo de evento.',
            'titulo.required' => match ($this->input('tipo')) {
                AgendaEvento::LEMBRETE => 'Escreva o lembrete.',
                AgendaEvento::DATA_IMPORTANTE => 'Informe o nome da data.',
                default => 'Informe o título do compromisso.',
            },
            'titulo.max' => 'O título pode ter no máximo 200 caracteres.',
            'data.*' => 'Informe uma data válida.',
            'hora_inicio.required' => $this->input('tipo') === AgendaEvento::COMPROMISSO
                ? 'Informe a hora de início.' : 'Informe a hora.',
            'hora_inicio.date_format' => 'A hora é inválida.',
            'hora_fim.required' => 'Informe a hora de fim.',
            'hora_fim.date_format' => 'A hora de fim é inválida.',
            'hora_fim.after' => 'O fim deve ser depois do início.',
            'repetir.*' => 'Escolha uma opção de repetição da lista.',
            'termina.*' => 'Escolha quando a repetição termina.',
            'termina_em.required_if' => 'Informe a data em que a repetição termina.',
            'termina_em.date_format' => 'A data de término é inválida.',
            'termina_em.after_or_equal' => 'A repetição não pode terminar antes da primeira data.',
            'termina_vezes.required_if' => 'Informe quantas vezes o evento se repete.',
            'termina_vezes.*' => 'O número de vezes deve ficar entre 1 e 500.',
            'postagem_id.required' => 'Escolha a publicação do alerta.',
            'postagem_id.exists' => 'Escolha uma publicação com situação "publicado".',
            'alert_template_id.*' => 'Escolha o modelo de mensagem.',
            'participantes.*' => 'Os participantes devem ser administradores do painel.',
            'participantes_externos.max' => 'Inclua no máximo 30 participantes sem cadastro.',
            'participantes_externos.*.max' => 'O nome de cada participante pode ter no máximo 120 caracteres.',
            'lembrar_antes.*' => 'Escolha uma opção de lembrete da lista.',
            'cliente_id.*' => 'Escolha um cliente da lista.',
            'visibilidade.*' => 'Escolha quem vê o lembrete.',
            'observacao.max' => 'A observação pode ter no máximo 2000 caracteres.',
            'categoria.*' => 'Escolha a categoria da data.',
            'avisar_antes.*' => 'Escolha uma opção de aviso da lista.',
            'publicacao_relacionada_id.*' => 'Escolha uma publicação da lista.',
            'local.max' => 'O local pode ter no máximo 500 caracteres.',
        ];
    }

    /** Colunas do evento, só com o que vale para o tipo escolhido. */
    public function dados(): array
    {
        $v = $this->validated();
        $tipo = $v['tipo'];
        $repete = $v['repetir'] !== 'nao';
        $diaInteiro = $tipo === AgendaEvento::COMPROMISSO && $this->boolean('dia_inteiro');

        $dados = [
            'tipo' => $tipo,
            'titulo' => $tipo === AgendaEvento::ALERTA ? null : $v['titulo'],
            'data' => $v['data'],
            'hora_inicio' => $tipo === AgendaEvento::DATA_IMPORTANTE || $diaInteiro ? null : ($v['hora_inicio'] ?? null),
            'hora_fim' => $tipo === AgendaEvento::COMPROMISSO && ! $diaInteiro ? ($v['hora_fim'] ?? null) : null,
            'dia_inteiro' => $diaInteiro,
            'repetir' => $v['repetir'],
            'termina' => $repete ? $v['termina'] : 'nunca',
            'termina_em' => $repete && $v['termina'] === 'data' ? $v['termina_em'] : null,
            'termina_vezes' => $repete && $v['termina'] === 'vezes' ? (int) $v['termina_vezes'] : null,
            'local' => null, 'lembrar_antes' => null, 'cliente_id' => null, 'participantes_externos' => null,
            'visibilidade' => 'equipe', 'observacao' => null, 'categoria' => null, 'avisar_antes' => null,
            'postagem_id' => null, 'alert_template_id' => null,
        ];

        return match ($tipo) {
            AgendaEvento::COMPROMISSO => [
                'local' => $v['local'] ?? null,
                'lembrar_antes' => isset($v['lembrar_antes']) ? (int) $v['lembrar_antes'] : null,
                'cliente_id' => $v['cliente_id'] ?? null,
                'participantes_externos' => $this->externos($v['participantes_externos'] ?? []),
            ],
            AgendaEvento::ALERTA => [
                'postagem_id' => (int) $v['postagem_id'],
                'alert_template_id' => (int) $v['alert_template_id'],
            ],
            AgendaEvento::LEMBRETE => [
                'visibilidade' => $v['visibilidade'],
                'observacao' => $v['observacao'] ?? null,
            ],
            AgendaEvento::DATA_IMPORTANTE => [
                'categoria' => $v['categoria'],
                'avisar_antes' => isset($v['avisar_antes']) ? (int) $v['avisar_antes'] : null,
                'postagem_id' => $v['publicacao_relacionada_id'] ?? null,
                'visibilidade' => $this->boolean('mostrar_equipe') ? 'equipe' : 'eu',
            ],
        } + $dados;
    }

    /** Nomes digitados, sem vazios nem repetidos; null quando não há nenhum. */
    private function externos(array $nomes): ?array
    {
        $limpos = [];
        foreach ($nomes as $nome) {
            $nome = trim(preg_replace('/\s+/', ' ', (string) $nome));
            if ($nome !== '' && ! in_array(mb_strtolower($nome), array_map('mb_strtolower', $limpos), true)) {
                $limpos[] = $nome;
            }
        }

        return $limpos ?: null;
    }

    /** @return array<int, int> */
    public function participantes(): array
    {
        return $this->input('tipo') === AgendaEvento::COMPROMISSO
            ? array_map('intval', $this->validated('participantes') ?? [])
            : [];
    }
}
