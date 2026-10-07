<?php

namespace App\Models;

use App\Support\Telefone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /** Os três perfis do sistema. */
    public const MASTER = 'master';
    public const ADM    = 'adm';
    public const CLIENT = 'client';

    // alerta_consentimento_em fica de fora de propósito: só a própria pessoa
    // autoriza, pela tela Minha conta. Assim nenhum formulário do ADM consegue
    // marcar o consentimento por ela, nem editando o HTML.
    protected $fillable = ['nome', 'usuario', 'email', 'password', 'perfil', 'ativo', 'telefone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo'    => 'boolean',
            'alerta_consentimento_em' => 'datetime',
        ];
    }

    public function postagens(): HasMany
    {
        return $this->hasMany(Postagem::class);
    }

    // ---------------------------------------------------------------- alertas

    /** Quem entra num disparo: conta ativa e com telefone cadastrado. */
    public function scopeAlcancaveisPorAlerta(Builder $query): Builder
    {
        return $query->where('ativo', true)->whereNotNull('telefone');
    }

    public function autorizouAlertas(): bool
    {
        return $this->telefone !== null && $this->alerta_consentimento_em !== null;
    }

    public function telefoneFormatado(bool $comDdi = true): string
    {
        return Telefone::formatar($this->telefone, $comDdi);
    }

    // ---------------------------------------------------------------- perfis

    public function ehMaster(): bool
    {
        return $this->perfil === self::MASTER;
    }

    public function ehAdm(): bool
    {
        return $this->perfil === self::ADM;
    }

    public function ehClient(): bool
    {
        return $this->perfil === self::CLIENT;
    }

    /** Master e ADM podem criar e editar postagens; Client não. */
    public function podeGerenciarPostagens(): bool
    {
        return in_array($this->perfil, [self::MASTER, self::ADM], true);
    }

    /** Master e ADM podem cadastrar usuários — mas com alcances diferentes. */
    public function podeGerenciarUsuarios(): bool
    {
        return in_array($this->perfil, [self::MASTER, self::ADM], true);
    }

    /**
     * Quais perfis este usuário pode atribuir a outro.
     * Só o Master cria outro Master; o ADM cria ADM e Client.
     */
    public function perfisQuePodeAtribuir(): array
    {
        return $this->ehMaster()
            ? [self::MASTER, self::ADM, self::CLIENT]
            : [self::ADM, self::CLIENT];
    }

    /** O ADM não mexe em Master. */
    public function podeEditarUsuario(User $outro): bool
    {
        if ($this->ehMaster()) {
            return true;
        }
        return $this->ehAdm() && ! $outro->ehMaster();
    }

    public function rotuloPerfil(): string
    {
        return match ($this->perfil) {
            self::MASTER => 'Master',
            self::ADM    => 'Administrador',
            default      => 'Cliente',
        };
    }

    /** O que o perfil permite, para a tela Minha conta. */
    public function permissoesDoPerfil(): array
    {
        return match ($this->perfil) {
            self::MASTER => [
                'Ver todas as publicações, inclusive rascunhos',
                'Criar, editar e excluir publicações',
                'Disparar alertas e gerenciar modelos de mensagem',
                'Ver os indicadores do painel',
                'Cadastrar e editar qualquer usuário, inclusive Master',
            ],
            self::ADM => [
                'Ver todas as publicações, inclusive rascunhos',
                'Criar, editar e excluir publicações',
                'Disparar alertas e gerenciar modelos de mensagem',
                'Ver os indicadores do painel',
                'Cadastrar e editar usuários Administrador e Cliente',
            ],
            default => [
                'Ver as publicações publicadas',
                'Receber alertas de novas publicações no WhatsApp, se autorizar',
            ],
        };
    }

    /** Iniciais para o avatar do painel (MS, no wireframe). */
    public function iniciais(): string
    {
        $partes = preg_split('/\s+/', trim($this->nome));
        $primeira = mb_substr($partes[0] ?? '', 0, 1);
        $ultima = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';

        return mb_strtoupper($primeira . $ultima);
    }
}
