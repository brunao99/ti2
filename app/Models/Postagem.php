<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Postagem extends Model
{
    use HasFactory;

    protected $table = 'postagens';

    /**
     * Tipos de conteúdo, iguais às seções do menu do portal.
     *
     * Fonte única: validação, formulário, filtro, rótulo e cor saem todos
     * daqui. A coluna é string, não enum de banco — esta lista vai mudar
     * conforme o portal muda, e alterar enum em MySQL exige migration a cada
     * ajuste.
     *
     * 'curto' é o que aparece na etiqueta da listagem, onde o nome inteiro
     * não caberia.
     *
     * 'cor' é a cor padrão do tipo na identidade do portal. O título da
     * publicação a recebe sozinho — não há escolha no formulário nem coluna
     * no banco. Aqui fica só o nome; o hexadecimal vive no CSS, com um tom
     * para o tema claro e outro para o escuro.
     */
    public const TIPOS = [
        'ministerios_programas'   => ['nome' => 'Ministérios e autarquias — Programas',                      'curto' => 'Min. programas', 'cor' => 'roxo'],
        'ministerios_editais'     => ['nome' => 'Ministérios e autarquias — Editais',                        'curto' => 'Min. editais',   'cor' => 'vermelho'],
        'estado_programas'        => ['nome' => 'Governo do estado — Programas e ações',                     'curto' => 'Estado',         'cor' => 'marrom'],
        'informes_cnm_amm'        => ['nome' => 'Informes CNM e AMM',                                        'curto' => 'Informes',       'cor' => 'marinho'],
        'imprensa_midias'         => ['nome' => 'Imprensa e mídias digitais',                                'curto' => 'Imprensa',       'cor' => 'rosa'],
        'financiamentos'          => ['nome' => 'Financiamentos',                                            'curto' => 'Financiamento',  'cor' => 'verde'],
        'praticas_governamentais' => ['nome' => 'Práticas governamentais, projetos inovadores e premiações', 'curto' => 'Práticas',       'cor' => 'rosa'],
        'parcerias_privada'       => ['nome' => 'Parcerias com iniciativa privada',                          'curto' => 'Parcerias',      'cor' => 'vermelho'],
        'seminarios_eventos'      => ['nome' => 'Seminários, cursos e eventos',                              'curto' => 'Eventos',        'cor' => 'vinho'],
        'indices_indicadores'     => ['nome' => 'Índices e indicadores',                                     'curto' => 'Indicadores',    'cor' => 'azul'],
    ];

    public static function tiposValidos(): array
    {
        return array_keys(self::TIPOS);
    }

    protected $fillable = [
        'tipo', 'titulo', 'texto', 'data_publicacao',
        'mes_referencia', 'situacao', 'alerta_enviado', 'user_id',
    ];

    /**
     * Classe CSS que pinta o título com a cor padrão do tipo.
     * Tipo desconhecido não recebe cor e herda a do tema.
     */
    public function classeCor(): string
    {
        $cor = self::TIPOS[$this->tipo]['cor'] ?? null;

        return $cor ? 'cor-' . $cor : '';
    }

    protected function casts(): array
    {
        return [
            'data_publicacao' => 'date',
            'alerta_enviado'  => 'boolean',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(Anexo::class);
    }

    // ---------------------------------------------------------------- escopos

    public function scopePublicadas(Builder $q): Builder
    {
        return $q->where('situacao', 'publicado');
    }

    /**
     * Filtra por um ou vários tipos. Lista vazia significa "todos" — é o
     * estado inicial da tela e também o que sobra quando alguém desmarca tudo.
     */
    public function scopeDosTipos(Builder $q, array $tipos): Builder
    {
        $validos = array_values(array_intersect($tipos, self::tiposValidos()));

        return $validos ? $q->whereIn('tipo', $validos) : $q;
    }

    public function scopeBuscando(Builder $q, ?string $termo): Builder
    {
        return $termo ? $q->where('titulo', 'like', '%' . $termo . '%') : $q;
    }

    // ---------------------------------------------------------------- rótulos

    public function rotuloTipo(): string
    {
        return self::TIPOS[$this->tipo]['nome'] ?? $this->tipo;
    }

    /** Versão enxuta, para a etiqueta da listagem. */
    public function rotuloTipoCurto(): string
    {
        return self::TIPOS[$this->tipo]['curto'] ?? $this->tipo;
    }

    public function ehRascunho(): bool
    {
        return $this->situacao === 'rascunho';
    }

    /** "Setembro 2026", a partir de "2026-09". */
    public function mesPorExtenso(): string
    {
        [$ano, $mes] = explode('-', $this->mes_referencia);

        $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        return $meses[(int) $mes - 1] . ' ' . $ano;
    }
}
