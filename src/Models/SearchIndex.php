<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * SearchIndex Model.
 *
 * Represents a searchable content index entry in the ArtisanPack UI CMS Framework.
 * This model stores processed, searchable content from various models like Content, Term, etc.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.2.0
 *
 * @property int                        $id
 * @property string                     $searchable_type    The type of the searchable model
 * @property int                        $searchable_id      The ID of the searchable model
 * @property string                     $title              Indexed title for search
 * @property string|null                $content            Full-text searchable content
 * @property string|null                $excerpt            Short description/excerpt
 * @property string|null                $keywords           Comma-separated keywords
 * @property string|null                $type               Content type, taxonomy name, etc.
 * @property string|null                $status             Status (published, draft, etc.)
 * @property int|null                   $author_id          Content author ID
 * @property Carbon|null                $published_at       Publication date
 * @property float                      $relevance_boost    Manual relevance multiplier
 * @property array|null                 $meta_data          Additional searchable metadata
 * @property Carbon                     $created_at
 * @property Carbon                     $updated_at
 */
class SearchIndex extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.2.0
     *
     * @var string
     */
    protected $table = 'search_indices';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.2.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'searchable_type',
        'searchable_id',
        'title',
        'content',
        'excerpt',
        'keywords',
        'type',
        'status',
        'author_id',
        'published_at',
        'relevance_boost',
        'meta_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.2.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta_data' => 'array',
        'published_at' => 'datetime',
        'relevance_boost' => 'decimal:2',
    ];

    /**
     * Get the searchable model that this index entry represents.
     *
     * @since 1.2.0
     *
     * @return MorphTo
     */
    public function searchable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the author that created the indexed content.
     *
     * @since 1.2.0
     *
     * @return BelongsTo
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope a query to only include published searchable content.
     *
     * @since 1.2.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope a query to filter by content type.
     *
     * @since 1.2.0
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by author.
     *
     * @since 1.2.0
     *
     * @param Builder $query
     * @param int $authorId
     * @return Builder
     */
    public function scopeByAuthor(Builder $query, int $authorId): Builder
    {
        return $query->where('author_id', $authorId);
    }

    /**
     * Scope a query to filter by date range.
     *
     * @since 1.2.0
     *
     * @param Builder $query
     * @param Carbon|string|null $from
     * @param Carbon|string|null $to
     * @return Builder
     */
    public function scopeDateRange(Builder $query, $from = null, $to = null): Builder
    {
        if ($from) {
            $query->where('published_at', '>=', $from);
        }

        if ($to) {
            $query->where('published_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Perform a full-text search query.
     *
     * @since 1.2.0
     *
     * @param Builder $query
     * @param string $searchTerm
     * @param string $mode Search mode: 'boolean', 'natural', or 'query_expansion'
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $searchTerm, string $mode = 'natural'): Builder
    {
        if (empty(trim($searchTerm))) {
            return $query;
        }

        $searchTerm = addslashes($searchTerm);

        return match ($mode) {
            'boolean' => $query->whereRaw(
                'MATCH(title, content, excerpt, keywords) AGAINST(? IN BOOLEAN MODE)',
                [$searchTerm]
            )->selectRaw(
                '*, MATCH(title, content, excerpt, keywords) AGAINST(? IN BOOLEAN MODE) as relevance_score',
                [$searchTerm]
            ),
            'query_expansion' => $query->whereRaw(
                'MATCH(title, content, excerpt, keywords) AGAINST(? WITH QUERY EXPANSION)',
                [$searchTerm]
            )->selectRaw(
                '*, MATCH(title, content, excerpt, keywords) AGAINST(? WITH QUERY EXPANSION) as relevance_score',
                [$searchTerm]
            ),
            default => $query->whereRaw(
                'MATCH(title, content, excerpt, keywords) AGAINST(?)',
                [$searchTerm]
            )->selectRaw(
                '*, MATCH(title, content, excerpt, keywords) AGAINST(?) as relevance_score',
                [$searchTerm]
            ),
        };
    }

    /**
     * Calculate freshness score based on publication date.
     *
     * @since 1.2.0
     *
     * @param int $decayDays Number of days for freshness to decay to 50%
     * @return float
     */
    public function getFreshnessScore(int $decayDays = 365): float
    {
        if (!$this->published_at) {
            return 0.5; // Default score for content without publication date
        }

        $daysOld = now()->diffInDays($this->published_at);
        
        // Exponential decay: score = e^(-days / decay_constant)
        // When days = decayDays, score ≈ 0.5
        $decayConstant = $decayDays / log(2);
        
        return min(1.0, exp(-$daysOld / $decayConstant));
    }

    /**
     * Get content type weight from configuration.
     *
     * @since 1.2.0
     *
     * @return float
     */
    public function getTypeWeight(): float
    {
        $weights = config('cms.search.type_weights', []);
        
        return $weights[$this->type] ?? 1.0;
    }

    /**
     * Calculate final search score with all ranking factors.
     *
     * @since 1.2.0
     *
     * @param float $textRelevance Base text relevance score
     * @param array $weights Scoring weights configuration
     * @return float
     */
    public function calculateSearchScore(float $textRelevance, array $weights = []): float
    {
        $defaultWeights = [
            'text_relevance' => 0.4,
            'type_weight' => 0.2,
            'freshness' => 0.15,
            'author_authority' => 0.1,
            'manual_boost' => 0.1,
            'engagement' => 0.05,
        ];

        $weights = array_merge($defaultWeights, $weights);

        $typeWeight = $this->getTypeWeight();
        $freshnessScore = $this->getFreshnessScore(
            config('cms.search.freshness_decay_days', 365)
        );
        $authorAuthority = 1.0; // TODO: Implement author authority scoring
        $engagementScore = 1.0; // TODO: Implement engagement scoring

        $score = (
            $textRelevance * $weights['text_relevance'] +
            $typeWeight * $weights['type_weight'] +
            $freshnessScore * $weights['freshness'] +
            $authorAuthority * $weights['author_authority'] +
            1.0 * $weights['manual_boost'] + // Base manual boost
            $engagementScore * $weights['engagement']
        ) * $this->relevance_boost;

        return round($score, 4);
    }
}