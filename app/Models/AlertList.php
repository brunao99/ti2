<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertList extends Model
{
    protected $fillable = ['nome', 'descricao'];

    public function contatos(): BelongsToMany
    {
        return $this->belongsToMany(AlertContact::class, 'alert_list_contact');
    }

    public function disparos(): HasMany
    {
        return $this->hasMany(AlertDispatch::class);
    }
}
