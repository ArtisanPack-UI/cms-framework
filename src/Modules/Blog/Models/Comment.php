<?php

declare( strict_types=1 );

/**
 * Comment Model
 *
 * Represents a comment on a blog post. Comments belong to a post and
 * optionally to an authenticated user; threaded replies hang off
 * `parent_id`. The accessor surface mirrors the shape the visual-editor
 * package's `CommentResolver` reads when stamping `_resolved*`
 * attributes on `artisanpack/comment-*` blocks.
 *
 * @since 2.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Models;

use ArtisanPackUI\CMSFramework\Modules\Blog\Database\Factories\CommentFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comment Model
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string|null $author_name
 * @property string|null $author_email
 * @property string|null $author_url
 * @property string $content
 * @property string $status
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @since 2.1.0
 */
class Comment extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Status constants. Mirrors WordPress' comment_approved values
     * conceptually but kept as strings so host apps can extend the
     * status vocabulary via the hooks system.
     *
     * @since 2.1.0
     */
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_SPAM     = 'spam';
    public const STATUS_TRASH    = 'trash';

    /**
     * Explicit table name — the package convention prefixes Blog tables
     * with `post_*` (see `post_categories`, `post_tag_pivots`).
     *
     * @since 2.1.0
     *
     * @var string
     */
    protected $table = 'post_comments';

    /**
     * The attributes that are mass assignable.
     *
     * @since 2.1.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'post_id',
        'parent_id',
        'user_id',
        'author_name',
        'author_email',
        'author_url',
        'content',
        'status',
        'approved_at',
    ];

    /**
     * Get the post this comment belongs to.
     *
     * @since 2.1.0
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo( Post::class );
    }

    /**
     * Get the authenticated user who left the comment (nullable for
     * guest comments).
     *
     * @since 2.1.0
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo( config( 'auth.providers.users.model' ), 'user_id' );
    }

    /**
     * Get the parent comment when this comment is a reply.
     *
     * @since 2.1.0
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo( self::class, 'parent_id' );
    }

    /**
     * Get the approved replies hanging off this comment. Mirrors the
     * default scoping on `Post::comments()` so consumers iterating
     * public threads don't leak pending / spam / trash replies. Use
     * `$comment->repliesIncludingUnapproved()` (or query
     * `$comment->hasMany(self::class, 'parent_id')` directly) for
     * moderation surfaces that need the full set.
     *
     * @since 2.1.0
     */
    public function replies(): HasMany
    {
        return $this->hasMany( self::class, 'parent_id' )->approved()->oldest();
    }

    /**
     * Get every reply (regardless of moderation status) — used by
     * the admin / moderation surfaces. Public consumers should use
     * the default `replies()` relation above.
     *
     * @since 2.1.0
     */
    public function repliesIncludingUnapproved(): HasMany
    {
        return $this->hasMany( self::class, 'parent_id' );
    }

    /**
     * Scope a query to approved comments only.
     *
     * @since 2.1.0
     */
    public function scopeApproved( Builder $query ): Builder
    {
        return $query->where( 'status', self::STATUS_APPROVED );
    }

    /**
     * Scope a query to pending comments.
     *
     * @since 2.1.0
     */
    public function scopePending( Builder $query ): Builder
    {
        return $query->where( 'status', self::STATUS_PENDING );
    }

    /**
     * Scope a query to spam comments.
     *
     * @since 2.1.0
     */
    public function scopeSpam( Builder $query ): Builder
    {
        return $query->where( 'status', self::STATUS_SPAM );
    }

    /**
     * Scope a query to top-level comments (no parent).
     *
     * @since 2.1.0
     */
    public function scopeTopLevel( Builder $query ): Builder
    {
        return $query->whereNull( 'parent_id' );
    }

    /**
     * Get the resolved author for the comment. Returns the related
     * `User` when the comment was left by an authenticated user,
     * otherwise a guest-shaped `stdClass` populated from the
     * `author_*` columns. Always returns an object with at least
     * `name` / `url` / `avatar_url` keys so consumers (e.g. the
     * visual-editor `CommentResolver`) can read them uniformly.
     *
     * @since 2.1.0
     */
    public function getAuthorAttribute(): object
    {
        if ( null !== $this->user_id && null !== $this->user ) {
            return $this->user;
        }

        return ( object ) [
            'name'        => $this->author_name ?? '',
            'url'         => $this->author_url ?? '',
            'avatar_url'  => $this->avatar_url,
            'is_guest'    => true,
        ];
    }

    /**
     * Get the avatar URL for the comment author. Prefers the related
     * user's avatar (when the User model exposes one), otherwise
     * falls back to a Gravatar derived from `author_email`.
     *
     * @since 2.1.0
     */
    public function getAvatarUrlAttribute(): string
    {
        if ( null !== $this->user_id && null !== $this->user ) {
            $userAvatar = $this->user->avatar_url ?? $this->user->avatar ?? null;
            if ( is_string( $userAvatar ) && '' !== $userAvatar ) {
                return $userAvatar;
            }
        }

        $email = $this->user?->email ?? $this->author_email ?? '';

        if ( '' === $email ) {
            return '';
        }

        $hash = md5( strtolower( trim( $email ) ) );

        return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=96";
    }

    /**
     * Get the public permalink for the comment.
     *
     * @since 2.1.0
     */
    public function getPermalinkAttribute(): string
    {
        $postPermalink = $this->post?->permalink ?? '';

        if ( '' === $postPermalink ) {
            return '';
        }

        return $postPermalink . '#comment-' . $this->id;
    }

    /**
     * Get the admin edit link for the comment. Routes through a
     * filter so host apps can supply their own moderation URL.
     *
     * @since 2.1.0
     */
    public function getEditLinkAttribute(): string
    {
        $default = url( "/admin/comments/{$this->id}/edit" );

        if ( function_exists( 'applyFilters' ) ) {
            return ( string ) applyFilters( 'comment.editLink', $default, $this );
        }

        return $default;
    }

    /**
     * Get the public reply URL for the comment.
     *
     * @since 2.1.0
     */
    public function getReplyLinkAttribute(): string
    {
        $postPermalink = $this->post?->permalink ?? '';

        if ( '' === $postPermalink ) {
            return '';
        }

        $separator = str_contains( $postPermalink, '?' ) ? '&' : '?';

        return $postPermalink . $separator . 'replytocom=' . $this->id;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @since 2.1.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Resolve the factory for this model. The default Laravel
     * factory guesser produces `Database\Factories\{FQCN}Factory`,
     * which doesn't match the package's
     * `Modules\Blog\Database\Factories\CommentFactory` location.
     * Other modules (Notifications, Users) use the same override.
     *
     * @since 2.1.0
     */
    protected static function newFactory(): Factory
    {
        return CommentFactory::new();
    }
}
