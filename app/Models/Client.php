<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'investment' => 'integer', 'archived' => 'boolean', 'sample' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function getNameAttribute(): string
    {
        return $this->user->name;
    }
}
