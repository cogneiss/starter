<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $email
 * @property-read CarbonInterface|null $email_verified_at
 * @property-read string|null $current_organization_id
 * @property-read bool $is_active
 * @property-read bool $is_super_admin
 * @property-read array<string, array<string, bool>>|null $notification_preferences
 * @property-read string $password
 * @property-read string|null $remember_token
 * @property-read string|null $two_factor_secret
 * @property-read string|null $two_factor_recovery_codes
 * @property-read CarbonInterface|null $two_factor_confirmed_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization|null $currentOrganization
 * @property-read Collection<int, OrganizationMembership> $memberships
 * @property-read Collection<int, Organization> $organizations
 */
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'deleted_at',
])]
final class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUuids;
    use Notifiable;
    use PasskeyAuthenticatable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The notifications a person may turn off, and the channels each offers.
     *
     * Notifications that carry a security decision are absent on purpose: a
     * magic link is not something anybody opts out of.
     *
     * @var array<string, array<int, string>>
     */
    public const array NOTIFICATION_CHANNELS = [
        'organization_invitation_notification' => ['mail', 'database'],
    ];

    /**
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot(['status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * Whether the user holds an active membership of the given organization.
     */
    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->memberships()
            ->where('organization_id', $organization->id)
            ->where('status', MembershipStatus::Active)
            ->exists();
    }

    /**
     * The channels the given notification may use for this user.
     *
     * A person's preferences are a filter over what the notification offers,
     * never a way to add a channel it does not support. Nothing recorded means
     * everything is wanted, so a new channel reaches people without a backfill.
     *
     * @param  class-string  $notification
     * @param  array<int, string>  $channels
     * @return array<int, string>
     */
    public function channelsFor(string $notification, array $channels): array
    {
        $wanted = [];

        foreach ($channels as $channel) {
            if ($this->wantsNotification($notification, $channel)) {
                $wanted[] = $channel;
            }
        }

        return $wanted;
    }

    /**
     * The preference matrix as the settings screen shows it, defaults filled in.
     *
     * @return array<string, array<string, bool>>
     */
    public function notificationPreferences(): array
    {
        $preferences = [];

        foreach (self::NOTIFICATION_CHANNELS as $notification => $channels) {
            foreach ($channels as $channel) {
                $preferences[$notification][$channel] = (bool) data_get(
                    $this->notification_preferences ?? [],
                    $notification.'.'.$channel,
                    true,
                );
            }
        }

        return $preferences;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'email' => 'string',
            'email_verified_at' => 'datetime',
            'current_organization_id' => 'string',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
            'remember_token' => 'string',
            'two_factor_secret' => 'string',
            'two_factor_recovery_codes' => 'string',
            'two_factor_confirmed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * Whether the user wants the given notification on the given channel.
     *
     * @param  class-string  $notification
     */
    private function wantsNotification(string $notification, string $channel): bool
    {
        $preferences = $this->notification_preferences;

        if (! is_array($preferences)) {
            return true;
        }

        $key = Str::snake(class_basename($notification));

        return (bool) data_get($preferences, $key.'.'.$channel, true);
    }
}
