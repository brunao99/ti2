<?php

namespace App\Models;

use App\Support\Recorrencia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um item da agenda dos administradores. A coluna "tipo" decide quais campos
 * valem: a tabela é uma só porque os quatro tipos dividem data, repetição e
 * a forma de aparecer na agenda.
 */
class AgendaEvento extends Model
{
    /** Datas e horas da agenda são sempre do horário de Brasília. */
    public const FUSO = 'America/Sao_Paulo';

    public const COMPROMISSO = 'compromisso';
    public const ALERTA = 'alerta';
    public const LEMBRETE = 'lembrete';
    public const DATA_IMPORTANTE = 'data_importante';

    /** Nome, descrição do menu "Novo", ícone e palavra usada nas contagens. */
    public const TIPOS = [
        self::COMPROMISSO     => ['nome' => 'Compromisso',       'desc' => 'Reunião ou horário marcado',          'icone' => 'calendario', 'um' => 'reunião',  'varios' => 'reuniões'],
        self::ALERTA          => ['nome' => 'Alerta programado', 'desc' => 'WhatsApp no dia e hora escolhidos',   'icone' => 'enviar',     'um' => 'envio',    'varios' => 'envios'],
        self::LEMBRETE        => ['nome' => 'Lembrete',          'desc' => 'Aviso só para você',                  'icone' => 'sino',       'um' => 'lembrete', 'varios' => 'lembretes'],
        self::DATA_IMPORTANTE => ['nome' => 'Data importante',   'desc' => 'Prazo de edital, feriado, evento',    'icone' => 'bandeira',   'um' => 'data',     'varios' => 'datas'],
    ];

    public const CATEGORIAS = [
        'prazo'  => 'Prazo de edital ou obrigação',
        'evento' => 'Evento',
        'outro'  => 'Outro',
    ];

    /** "Lembrar antes" do compromisso, em minutos. */
    public const LEMBRAR = [
        15   => '15 minutos antes',
        60   => '1 hora antes',
        1440 => '1 dia antes',
    ];

    /** "Avisar antes" da data importante, em dias. */
    public const AVISAR = [1, 3, 5, 7];

    protected $table = 'agenda_eventos';

    protected $fillable = [
        'tipo', 'titulo', 'data', 'hora_inicio', 'hora_fim', 'dia_inteiro',
        'repetir', 'termina', 'termina_em', 'termina_vezes',
        'local', 'lembrar_antes', 'cliente_id',
        'visibilidade', 'observacao', 'categoria', 'avisar_antes',
        'postagem_id', 'alert_template_id', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'immutable_date',
            'termina_em' => 'immutable_date',
            'dia_inteiro' => 'boolean',
        ];
    }

    // ------------------------------------------------------------- relações

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function postagem(): BelongsTo
    {
        return $this->belongsTo(Postagem::class);
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(AlertTemplate::class, 'alert_template_id');
    }

    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agenda_evento_participantes', 'agenda_evento_id', 'user_id');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(AgendaOcorrencia::class);
    }

    // --------------------------------------------------------------- escopos

    /** O que este administrador enxerga: o da equipe e o que é só dele. */
    public function scopeVisiveisPara(Builder $q, User $usuario): Builder
    {
        return $q->where(fn (Builder $w) => $w
            ->where('visibilidade', 'equipe')
            ->orWhere('user_id', $usuario->id));
    }

    // ---------------------------------------------------------------- tempo

    /** Hoje, no horário de Brasília, só a data. */
    public static function hoje(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::agora()->format('Y-m-d'));
    }

    /** Agora, no horário de Brasília. */
    public static function agora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::FUSO);
    }

    /** O instante em que uma ocorrência começa (dia inteiro: meia-noite). */
    public function instante(CarbonImmutable $dia): CarbonImmutable
    {
        return CarbonImmutable::parse($dia->format('Y-m-d') . ' ' . ($this->hora_inicio ?: '00:00'), self::FUSO);
    }

    public function recorrencia(): Recorrencia
    {
        return new Recorrencia($this->data, $this->repetir, $this->termina, $this->termina_em, $this->termina_vezes);
    }

    public function repete(): bool
    {
        return $this->repetir !== 'nao';
    }

    /** Sem horário: vai para a faixa "dia todo" da agenda. */
    public function semHorario(): bool
    {
        return $this->tipo === self::DATA_IMPORTANTE || $this->dia_inteiro || ! $this->hora_inicio;
    }

    /** Minutos de duração do bloco na grade; alerta e lembrete são blocos curtos. */
    public function duracao(): int
    {
        if ($this->tipo !== self::COMPROMISSO || ! $this->hora_fim) {
            return 0;
        }

        [$h1, $m1] = array_map('intval', explode(':', $this->hora_inicio));
        [$h2, $m2] = array_map('intval', explode(':', $this->hora_fim));

        return max(0, ($h2 * 60 + $m2) - ($h1 * 60 + $m1));
    }

    // ------------------------------------------------------------- exibição

    public function ehAlerta(): bool
    {
        return $this->tipo === self::ALERTA;
    }

    public function nomeTipo(): string
    {
        return self::TIPOS[$this->tipo]['nome'] ?? 'Evento';
    }

    public function icone(): string
    {
        return self::TIPOS[$this->tipo]['icone'] ?? 'calendario';
    }

    /** Classe CSS da cor do tipo: t-compromisso, t-alerta, t-lembrete, t-data. */
    public function classeTipo(): string
    {
        return self::classeDoTipo($this->tipo);
    }

    public static function classeDoTipo(string $tipo): string
    {
        return 't-' . ($tipo === self::DATA_IMPORTANTE ? 'data' : $tipo);
    }

    /** O alerta leva o título da publicação que anuncia. */
    public function nome(): string
    {
        if ($this->ehAlerta()) {
            return $this->postagem?->titulo ?? 'Publicação removida';
        }

        return (string) $this->titulo;
    }

    /** "reunião"/"reuniões", "envio"/"envios"… conforme o tipo e a quantidade. */
    public function unidade(int $quantas): string
    {
        return self::TIPOS[$this->tipo][$quantas === 1 ? 'um' : 'varios'] ?? ($quantas === 1 ? 'vez' : 'vezes');
    }
}
