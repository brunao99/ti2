<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertContact extends Model
{
    protected $fillable = ['nome', 'telefone', 'consentimento'];

    protected function casts(): array
    {
        return ['consentimento' => 'boolean'];
    }

    public function listas(): BelongsToMany
    {
        return $this->belongsToMany(AlertList::class, 'alert_list_contact');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(AlertDispatchRecipient::class);
    }
}
