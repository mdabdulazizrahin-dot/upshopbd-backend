<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'moderator', 'editor', 'viewer']);
    }

    public function hasRole($roles): bool
    {
        if ($this->role === 'admin') {
            return true;
        }
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }
        return $this->role === $roles;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        // Default role permissions
        $roleDefaults = [
            'moderator' => ['dashboard', 'orders', 'abandoned_checkouts', 'customers', 'courier', 'delivery', 'analytics'],
            'editor' => ['dashboard', 'products', 'categories', 'banners', 'home_sections', 'pages'],
            'viewer' => ['dashboard', 'products', 'categories', 'orders', 'analytics'],
        ];

        if (is_array($this->permissions) && in_array($permission, $this->permissions)) {
            return true;
        }

        $defaultPerms = $roleDefaults[$this->role] ?? [];
        return in_array($permission, $defaultPerms);
    }
}