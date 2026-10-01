<?php

namespace App\Models;

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

    protected $fillable = ['nome', 'usuario', 'email', 'password', 'perfil', 'ativo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo'    => 'boolean',
        ];
    }

    public function postagens(): HasMany
    {
        return $this->hasMany(Postagem::class);
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

    /** Iniciais para o avatar do painel (MS, no wireframe). */
    public function iniciais(): string
    {
        $partes = preg_split('/\s+/', trim($this->nome));
        $primeira = mb_substr($partes[0] ?? '', 0, 1);
        $ultima = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';

        return mb_strtoupper($primeira . $ultima);
    }
}
