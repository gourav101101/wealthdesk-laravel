<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected ?string $pendingClientPassword = null;

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if ($user->isDirty('password')) {
                // Keep the login hash and encrypted client copy in the same SQL update.
                $user->client_password = ! $user->isAdmin() ? $user->pendingClientPassword : null;
            } elseif ($user->isAdmin()) {
                $user->client_password = null;
            }
        });
        static::saved(fn (self $user) => $user->pendingClientPassword = null);
    }

    public function setPasswordAttribute(string $value): void
    {
        $isHash = Hash::isHashed($value);
        $this->pendingClientPassword = $isHash ? null : $value;
        $this->attributes['password'] = $isHash ? $value : Hash::make($value);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'password_changed_at',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'client_password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'client_password' => 'encrypted',
            'password_changed_at' => 'datetime',
        ];
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
