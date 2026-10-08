<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Support\AdminPermissions;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A NISconnect account. Always backed by a verified NIS personnel record.
 *
 * @property string $id
 * @property string $service_number
 * @property string $account_state
 */
class User extends Authenticatable implements AuthenticatableContract
{
    use HasApiTokens;
    use HasFactory;
    use HasUuidPrimaryKey;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'personnel_record_id', 'service_number', 'phone', 'phone_verified_at',
        'display_name', 'avatar_path', 'account_state', 'presence', 'privacy',
    ];

    protected $hidden = [
        'password_hash', 'pin_hash', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /** @var list<string>|null Per-instance cache of permission names. */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'phone_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'privacy' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'last_admin_login_at' => 'datetime',
        ];
    }

    // Account states
    public const STATE_PENDING = 'pending';

    public const STATE_ACTIVE = 'active';

    public const STATE_SUSPENDED = 'suspended';

    public const STATE_LOCKED = 'locked';

    public const STATE_DISABLED = 'disabled';

    public const STATE_INACTIVE = 'inactive';

    public function isActive(): bool
    {
        return $this->account_state === self::STATE_ACTIVE;
    }

    /**
     * The admin portal (web guard) authenticates against the login password.
     */
    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    /**
     * Default privacy settings applied when none are set.
     *
     * @return array<string, string>
     */
    public static function defaultPrivacy(): array
    {
        return [
            'last_seen' => 'everyone',   // everyone|contacts|nobody
            'online' => 'everyone',
            'read_receipts' => 'everyone',
            'typing' => 'everyone',
            'profile_photo' => 'everyone',
            'calls' => 'everyone',
            'group_invites' => 'everyone',
        ];
    }

    public function personnelRecord(): BelongsTo
    {
        return $this->belongsTo(PersonnelRecord::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function conversationMemberships(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Names of every permission granted through the user's roles. Super
     * administrators hold every permission.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        $roleNames = $this->roles()->with('role.permissions')->get()->pluck('role');
        if ($roleNames->contains(fn ($r) => $r?->name === 'super_admin')) {
            return $this->permissionCache = array_keys(AdminPermissions::all());
        }

        return $this->permissionCache = $roleNames->filter()
            ->flatMap(fn ($r) => $r->permissions->pluck('name'))
            ->unique()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    public function canAccessAdminPortal(): bool
    {
        return $this->isActive() && $this->hasPermission('dashboard.view');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** Clears the cached permissions after role changes. */
    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
    }

    /**
     * Does the user hold the given role name (optionally within a scope)?
     */
    public function hasRole(string $roleName, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        return $this->roles()
            ->whereHas('role', fn ($q) => $q->where('name', $roleName))
            ->when($scopeType !== null, fn ($q) => $q->where('scope_type', $scopeType))
            ->when($scopeId !== null, fn ($q) => $q->where('scope_id', $scopeId))
            ->exists();
    }
}
