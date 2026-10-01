<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertTemplate extends Model
{
    protected $fillable = ['nome', 'mensagem'];

    public function disparos(): HasMany
    {
        return $this->hasMany(AlertDispatch::class);
    }
}
