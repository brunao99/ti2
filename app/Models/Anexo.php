<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Anexo extends Model
{
    use HasFactory;

    protected $table = 'anexos';

    protected $fillable = ['postagem_id', 'nome_original', 'caminho', 'tamanho'];

    public function postagem(): BelongsTo
    {
        return $this->belongsTo(Postagem::class);
    }

    /** "840 KB", como aparece na tela de visualização. */
    public function tamanhoLegivel(): string
    {
        if ($this->tamanho < 1024) {
            return $this->tamanho . ' B';
        }
        if ($this->tamanho < 1048576) {
            return round($this->tamanho / 1024) . ' KB';
        }

        return round($this->tamanho / 1048576, 1) . ' MB';
    }

    public function extensao(): string
    {
        return mb_strtoupper(pathinfo($this->nome_original, PATHINFO_EXTENSION));
    }
}
