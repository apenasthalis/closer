<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active', 'phone', 'avatar_color', 'team_id', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_CLOSER = 'closer';

    public const ROLE_SDR = 'sdr';

    public const ROLES = [
        self::ROLE_ADMIN => 'Administrador',
        self::ROLE_MANAGER => 'Gestor comercial',
        self::ROLE_CLOSER => 'Closer',
        self::ROLE_SDR => 'SDR / Pré-vendas',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function leadsAsCloser(): HasMany
    {
        return $this->hasMany(Lead::class, 'closer_id');
    }

    public function leadsAsSdr(): HasMany
    {
        return $this->hasMany(Lead::class, 'pre_sales_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function authSessions(): HasMany
    {
        return $this->hasMany(AuthSession::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isCloser(): bool
    {
        return $this->role === self::ROLE_CLOSER;
    }

    public function isSdr(): bool
    {
        return $this->role === self::ROLE_SDR;
    }

    public function canViewFinancials(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canManageSettings(): bool
    {
        return $this->isAdmin();
    }

    public function canApproveCommissions(): bool
    {
        return $this->isAdmin();
    }

    public function canManagePlaybooks(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function permissions(): array
    {
        return [
            'manage_users' => $this->canManageUsers(),
            'manage_settings' => $this->canManageSettings(),
            'view_financials' => $this->canViewFinancials(),
            'approve_commissions' => $this->canApproveCommissions(),
            'manage_playbooks' => $this->canManagePlaybooks(),
            'view_all_leads' => false,
            'control_docker' => $this->isAdmin() && app()->environment(['local', 'testing']),
        ];
    }
}
