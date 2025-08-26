<?php

/**
 * User Model
 *
 * Represents a user in the application.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\CMSFramework\Services\CacheService;
use ArtisanPackUI\Database\factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use TorMorten\Eventy\Facades\Eventy;

/**
 * Class for the User model.
 *
 * Handles database interactions for users, including authentication, roles,
 * and user-specific settings and links stored as JSON columns.
 *
 * @since 1.0.0
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The factory that should be used to instantiate the model.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected static $factory = UserFactory::class;

    /**
     * The table associated with the model.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'role_id',
        'first_name',
        'last_name',
        'website',
        'bio',
        'links',
        'settings',
        'two_factor_code',
        'two_factor_expires_at',
        'two_factor_enabled_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'links' => 'array',
        'settings' => 'array',
        'two_factor_expires_at' => 'datetime',
        'two_factor_enabled_at' => 'datetime',
    ];

    /**
     * Get the role that the user belongs to.
     *
     * @since 1.0.0
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get the pages for the user.
     *
     * @since 1.0.0
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Get cache service instance.
     */
    private function getCacheService(): CacheService
    {
        return app(CacheService::class);
    }

    /**
     * Checks if the user has a given capability through their assigned role.
     *
     * @since 1.0.0
     *
     * @param  iterable|string  $abilities  The capability to check for.
     * @param  array  $arguments  Additional arguments for the check.
     * @return bool True if the user has the capability, false otherwise.
     */
    public function can($abilities, $arguments = []): bool
    {
        // Convert abilities to string for caching
        $abilityString = is_array($abilities) ? implode(',', $abilities) : (string) $abilities;
        $cacheKey = 'user_capabilities';
        $cacheParams = ['user_id' => $this->id, 'ability' => md5($abilityString)];

        return $this->getCacheService()->remember(
            'users',
            $cacheKey,
            function () use ($abilities) {
                /**
                 * Filters whether a user has a specific capability.
                 *
                 * This hook allows for custom logic to determine if a user has a capability,
                 * bypassing the default role-based check if necessary.
                 *
                 * @since 1.0.0
                 *
                 * @param  bool  $hasCapability  Whether the user has the capability. Default false.
                 * @param  string  $abilities  The capability being checked.
                 * @param  User  $user  The user model instance.
                 */
                $hasCapability = Eventy::filter('ap.cms.users.user_can', false, $abilities, $this);

                if ($hasCapability) {
                    return true;
                }

                if ($this->role) {
                    return $this->role->hasCapability($abilities);
                }

                return false;
            },
            $cacheParams
        );
    }

    /**
     * Get a user-specific setting.
     *
     * @since 1.0.0
     *
     * @param  mixed  $default  Optional. The default value if the setting is not found. Default null.
     * @param  string  $key  The setting key to retrieve.
     * @return mixed The setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'user_settings';
        $cacheParams = ['user_id' => $this->id, 'key' => $key];

        return $this->getCacheService()->remember(
            'users',
            $cacheKey,
            function () use ($key, $default) {
                $settings = $this->settings ?? [];
                $value = $settings[$key] ?? $default;

                /**
                 * Filters a user-specific setting value retrieved from the user's settings column.
                 *
                 * @since 1.0.0
                 *
                 * @param  mixed  $value  The setting value.
                 * @param  string  $key  The setting key.
                 * @param  User  $user  The user model instance.
                 */
                $filtered = Eventy::filter('ap.cms.users.user_setting.get', $value, $key, $this);

                // Ensure we return null if the setting doesn't exist and default is null
                if ($value === null && $default === null && $filtered === '') {
                    return null;
                }

                return $filtered;
            },
            $cacheParams
        );
    }

    /**
     * Set a user-specific setting.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The value to store.
     * @param  string  $key  The setting key to set.
     * @return bool True if the setting was set and saved, false otherwise.
     */
    public function setSetting(string $key, mixed $value): bool
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        $saved = $this->save();

        if ($saved) {
            // Invalidate cached setting
            $cacheParams = ['user_id' => $this->id, 'key' => $key];
            $this->getCacheService()->forget('users', 'user_settings', $cacheParams);

            /**
             * Fires after a user-specific setting has been set and saved.
             *
             * @since 1.0.0
             *
             * @param  string  $key  The setting key.
             * @param  mixed  $value  The value that was set.
             * @param  User  $user  The user model instance.
             */
            Eventy::action('ap.cms.users.user_setting.set', $key, $value, $this);
        }

        return $saved;
    }

    /**
     * Delete a user-specific setting.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The setting key to delete.
     * @return bool True if the setting was deleted and saved, false otherwise.
     */
    public function deleteSetting(string $key): bool
    {
        $settings = $this->settings ?? [];
        if (isset($settings[$key])) {
            unset($settings[$key]);
            $this->settings = $settings;
            $saved = $this->save();

            if ($saved) {
                // Invalidate cached setting
                $cacheParams = ['user_id' => $this->id, 'key' => $key];
                $this->getCacheService()->forget('users', 'user_settings', $cacheParams);

                /**
                 * Fires after a user-specific setting has been deleted and saved.
                 *
                 * @since 1.0.0
                 *
                 * @param  string  $key  The setting key that was deleted.
                 * @param  User  $user  The user model instance.
                 */
                Eventy::action('ap.cms.users.user_setting.deleted', $key, $this);
            }

            return $saved;
        }

        return false;
    }

    /**
     * Get the current two-factor authentication code for the user.
     *
     * @since 1.1.0
     *
     * @return string|null The current 2FA code, or null if not set.
     */
    public function getTwoFactorCodeAttribute(): ?string
    {
        return $this->two_factor_code;
    }

    /**
     * Get the expiration timestamp for the two-factor authentication code.
     *
     * @since 1.1.0
     *
     * @return Carbon|null The expiration timestamp, or null if not set.
     */
    public function getTwoFactorExpiresAtAttribute(): ?Carbon
    {
        return $this->two_factor_expires_at;
    }

    /**
     * Determine if the two-factor authentication code has expired.
     *
     * @since 1.1.0
     *
     * @return bool True if the code has expired, false otherwise.
     */
    public function twoFactorCodeHasExpired(): bool
    {
        return $this->hasTwoFactorEnabled() && Carbon::now()->isAfter($this->two_factor_expires_at);
    }

    /**
     * Determine if email-based two-factor authentication is currently enabled for this user.
     *
     * This checks if the two_factor_code and two_factor_expires_at fields are set
     * and if the enabled_at timestamp is set, indicating a 2FA session is active.
     *
     * @since 1.1.0
     *
     * @return bool True if 2FA is active, false otherwise.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_code) &&
            ! is_null($this->two_factor_expires_at) &&
            ! is_null($this->two_factor_enabled_at);
    }

    /**
     * Store the two-factor authentication code for the user.
     *
     * @since 1.1.0
     *
     * @param  string  $code  The 2FA code to store.
     * @param  int  $expiresInMinutes  Optional. Minutes until code expires. Default 5.
     */
    public function setTwoFactorCode(string $code, int $expiresInMinutes = 5): void
    {
        $this->forceFill([
            'two_factor_code' => $code,
            'two_factor_expires_at' => Carbon::now()->addMinutes($expiresInMinutes),
            'two_factor_enabled_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Clear two-factor authentication data for the user.
     *
     * This effectively disables 2FA for the user.
     *
     * @since 1.1.0
     */
    public function clearTwoFactorData(): void
    {
        $this->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
            'two_factor_enabled_at' => null,
        ])->save();
    }
}
