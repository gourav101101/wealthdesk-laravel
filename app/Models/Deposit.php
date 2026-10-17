<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deposit extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'datetime', 'amount' => 'integer'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
