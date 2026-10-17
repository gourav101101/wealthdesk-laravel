<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceSetting extends Model
{
    protected $guarded = ['id'];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['name' => 'WealthDesk Capital', 'payment_note' => 'Include your Client ID in the payment reference. Contact your advisor after payment.']);
    }
}
