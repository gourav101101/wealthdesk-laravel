<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'datetime', 'quantity' => 'integer', 'entry_price' => 'integer', 'ltp' => 'integer', 'exit_price' => 'integer', 'pnl' => 'integer'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
