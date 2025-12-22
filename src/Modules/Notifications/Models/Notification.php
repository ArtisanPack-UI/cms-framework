<?php

declare( strict_types = 1 );

/**
 * Notification Model
 *
 * Represents a notification in the system.
 *
 * @since 1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Models;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
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
 * @since 1.0.0
 */
class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.0.0
     *
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
     * Get the pivot data for the current user.
     * This provides a convenient way to access pivot data in views.
     *
     * @since 1.0.0
     *
     * @return mixed
     */
    public function getPivotAttribute()
    {
        if ( $this->relationLoaded( 'users' ) && $this->users->isNotEmpty() ) {
            return $this->users->first()->pivot;
        }

        return null;
    }

    /**
     * Get the users that this notification belongs to.
     *
     * @since 1.0.0
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config( 'auth.providers.users.model' ),
            'notification_user',
            'notification_id',
            'user_id',
        )
            ->withPivot( ['is_read', 'read_at', 'is_dismissed', 'dismissed_at'] )
            ->withTimestamps();
    }

    /**
     * Scope a query to only include unread notifications for a user.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnreadForUser( Builder $query, int $userId )
    {
        return $query->whereHas( 'users', function ( $q ) use ( $userId ): void {
            $q->where( 'user_id', sanitizeInt( $userId ) )
                ->where( 'is_read', false )
                ->where( 'is_dismissed', false );
        } );
    }

    /**
     * Scope a query to only include read notifications for a user.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReadForUser( Builder $query, int $userId )
    {
        return $query->whereHas( 'users', function ( $q ) use ( $userId ): void {
            $q->where( 'user_id', sanitizeInt( $userId ) )
                ->where( 'is_read', true )
                ->where( 'is_dismissed', false );
        } );
    }

    /**
     * Scope a query to only include notifications that are not dismissed for a user.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotDismissedForUser( Builder $query, int $userId )
    {
        return $query->whereHas( 'users', function ( $q ) use ( $userId ): void {
            $q->where( 'user_id', sanitizeInt( $userId ) )
                ->where( 'is_dismissed', false );
        } );
    }

    /**
     * Scope a query to filter by notification type.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType( Builder $query, NotificationType $type )
    {
        // phpcs:ignore ArtisanPackUIStandard.Security.ValidatedSanitizedInput.MissingUnslash -- Type-safe enum
        return $query->where( 'type', $type );
    }

    /**
     * Create a new factory instance for the model.
     *
     * @since 1.0.0
     */
    protected static function newFactory()
    {
        return \ArtisanPackUI\Database\Factories\NotificationFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type'       => NotificationType::class,
            'metadata'   => 'array',
            'send_email' => 'boolean',
        ];
    }
}
