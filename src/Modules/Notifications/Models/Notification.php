<?php
/**
 * Notification Model
 *
 * Represents a notification in the system.
 *
 * @since 2.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Notifications\Models
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Models;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Notification Model
 *
 * @property int $id
 * @property NotificationType $type
 * @property string $title
 * @property string $content
 * @property array|null $metadata
 * @property bool $send_email
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @since 2.0.0
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @since 2.0.0
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'title',
        'content',
        'metadata',
        'send_email',
    ];

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
            'type' => NotificationType::class,
            'metadata' => 'array',
            'send_email' => 'boolean',
        ];
    }

    /**
     * Get the users that this notification belongs to.
     *
     * @since 2.0.0
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            'notification_user'
        )
            ->withPivot(['is_read', 'read_at', 'is_dismissed', 'dismissed_at'])
            ->withTimestamps();
    }

    /**
     * Scope a query to only include unread notifications for a user.
     *
     * @since 2.0.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnreadForUser($query, int $userId)
    {
        return $query->whereHas('users', function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->where('is_read', false)
                ->where('is_dismissed', false);
        });
    }

    /**
     * Scope a query to only include read notifications for a user.
     *
     * @since 2.0.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReadForUser($query, int $userId)
    {
        return $query->whereHas('users', function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->where('is_read', true)
                ->where('is_dismissed', false);
        });
    }

    /**
     * Scope a query to only include notifications that are not dismissed for a user.
     *
     * @since 2.0.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotDismissedForUser($query, int $userId)
    {
        return $query->whereHas('users', function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->where('is_dismissed', false);
        });
    }

    /**
     * Scope a query to filter by notification type.
     *
     * @since 2.0.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param NotificationType $type
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, NotificationType $type)
    {
        return $query->where('type', $type);
    }
}
