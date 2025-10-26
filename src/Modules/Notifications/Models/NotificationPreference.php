<?php
/**
 * NotificationPreference Model
 *
 * Represents user preferences for notification types.
 *
 * @since 2.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Notifications\Models
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NotificationPreference Model
 *
 * @property int $id
 * @property int $user_id
 * @property string $notification_type
 * @property bool $is_enabled
 * @property bool $email_enabled
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 2.0.0
 */
class NotificationPreference extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @since 2.0.0
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'notification_type',
        'is_enabled',
        'email_enabled',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @since 2.0.0
     */
    protected static function newFactory()
    {
        return \ArtisanPackUI\Database\Factories\NotificationPreferenceFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'email_enabled' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the notification preference.
     *
     * @since 2.0.0
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
