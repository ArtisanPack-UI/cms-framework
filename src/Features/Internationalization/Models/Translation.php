<?php

namespace ArtisanPackUI\CMSFramework\Features\Internationalization\Models;

use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Translation Model
 *
 * Manages individual translation strings with support for pluralization,
 * workflow management, and fallback mechanisms in the ArtisanPack UI CMS Framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Internationalization\Models
 * @since      1.5.0
 *
 * @property int                 $id
 * @property int                 $language_id             Associated language ID
 * @property string              $group                   Translation group/namespace
 * @property string              $key                     Translation key identifier
 * @property string|null         $value                   Translated text value
 * @property array|null          $plurals                 Plural forms for pluralization
 * @property string|null         $plural_rule             Pluralization rule
 * @property string|null         $context                 Context for translators
 * @property string|null         $comment                 Translator comments
 * @property array|null          $metadata                Additional metadata
 * @property string              $status                  Translation status
 * @property bool                $needs_review            Whether needs review
 * @property bool                $is_fuzzy                Whether fuzzy/uncertain
 * @property float|null          $quality_score           Quality score (0-100)
 * @property string|null         $source_value            Original source text
 * @property Carbon|null         $source_updated_at       Source last updated
 * @property bool                $is_outdated             Whether outdated vs source
 * @property int|null            $translator_id           User who translated
 * @property int|null            $reviewer_id             User who reviewed
 * @property Carbon|null         $translated_at           When translated
 * @property Carbon|null         $reviewed_at             When reviewed
 * @property int                 $usage_count             Usage count
 * @property Carbon|null         $last_used_at            Last used timestamp
 * @property Carbon              $created_at
 * @property Carbon              $updated_at
 * @property-read Language       $language
 * @property-read User|null      $translator
 * @property-read User|null      $reviewer
 */
class Translation extends Model
{
    /**
     * Translation status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_TRANSLATED = 'translated';
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * The table associated with the model.
     *
     * @since 1.5.0
     *
     * @var string
     */
    protected $table = 'translations';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.5.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'language_id',
        'group',
        'key',
        'value',
        'plurals',
        'plural_rule',
        'context',
        'comment',
        'metadata',
        'status',
        'needs_review',
        'is_fuzzy',
        'quality_score',
        'source_value',
        'source_updated_at',
        'is_outdated',
        'translator_id',
        'reviewer_id',
        'translated_at',
        'reviewed_at',
        'usage_count',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.5.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'plurals' => 'array',
        'metadata' => 'array',
        'needs_review' => 'boolean',
        'is_fuzzy' => 'boolean',
        'quality_score' => 'decimal:2',
        'source_updated_at' => 'datetime',
        'is_outdated' => 'boolean',
        'translated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Boot the model and set up event listeners.
     *
     * @since 1.5.0
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        // Update language statistics when translation is saved
        static::saved(function (Translation $translation) {
            $translation->updateLanguageStats();
        });

        // Update language statistics when translation is deleted
        static::deleted(function (Translation $translation) {
            $translation->updateLanguageStats();
        });

        // Set translated_at timestamp when status changes to translated
        static::saving(function (Translation $translation) {
            if ($translation->isDirty('status') && $translation->status === self::STATUS_TRANSLATED) {
                $translation->translated_at = now();
            }
            
            if ($translation->isDirty('status') && in_array($translation->status, [self::STATUS_REVIEWED, self::STATUS_APPROVED])) {
                $translation->reviewed_at = now();
            }
        });
    }

    /**
     * Get the language that owns the translation.
     *
     * @since 1.5.0
     *
     * @return BelongsTo
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the user who provided the translation.
     *
     * @since 1.5.0
     *
     * @return BelongsTo
     */
    public function translator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'translator_id');
    }

    /**
     * Get the user who reviewed the translation.
     *
     * @since 1.5.0
     *
     * @return BelongsTo
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Scope a query to filter by language.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @param string|int $language Language code or ID
     * @return Builder
     */
    public function scopeForLanguage(Builder $query, $language): Builder
    {
        if (is_numeric($language)) {
            return $query->where('language_id', $language);
        }

        return $query->whereHas('language', function (Builder $q) use ($language) {
            $q->where('code', $language)->orWhere('locale', $language);
        });
    }

    /**
     * Scope a query to filter by group.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @param string $group Translation group
     * @return Builder
     */
    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Scope a query to filter by status.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @param string $status Translation status
     * @return Builder
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to get translations needing review.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('needs_review', true);
    }

    /**
     * Scope a query to get outdated translations.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOutdated(Builder $query): Builder
    {
        return $query->where('is_outdated', true);
    }

    /**
     * Scope a query to get fuzzy translations.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFuzzy(Builder $query): Builder
    {
        return $query->where('is_fuzzy', true);
    }

    /**
     * Scope a query to get missing translations.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeMissing(Builder $query): Builder
    {
        return $query->whereNull('value')->orWhere('value', '');
    }

    /**
     * Scope a query to get completed translations.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('value')
            ->where('value', '!=', '')
            ->where('status', '!=', self::STATUS_PENDING);
    }

    /**
     * Get translation for a specific key in a language with fallback support.
     *
     * @since 1.5.0
     *
     * @param string $key Translation key
     * @param string $language Language code
     * @param string $group Translation group
     * @param array $fallbackLanguages Fallback language codes
     * @return Translation|null
     */
    public static function getTranslation(
        string $key,
        string $language,
        string $group = 'default',
        array $fallbackLanguages = []
    ): ?Translation {
        // First try the requested language
        $translation = static::forLanguage($language)
            ->forGroup($group)
            ->where('key', $key)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->first();

        if ($translation) {
            $translation->recordUsage();
            return $translation;
        }

        // Try fallback languages
        foreach ($fallbackLanguages as $fallbackLang) {
            $translation = static::forLanguage($fallbackLang)
                ->forGroup($group)
                ->where('key', $key)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->first();

            if ($translation) {
                $translation->recordUsage();
                return $translation;
            }
        }

        return null;
    }

    /**
     * Create or update a translation.
     *
     * @since 1.5.0
     *
     * @param string $key Translation key
     * @param string $value Translation value
     * @param int $languageId Language ID
     * @param string $group Translation group
     * @param array $attributes Additional attributes
     * @return Translation
     */
    public static function createOrUpdate(
        string $key,
        string $value,
        int $languageId,
        string $group = 'default',
        array $attributes = []
    ): Translation {
        return static::updateOrCreate(
            [
                'language_id' => $languageId,
                'group' => $group,
                'key' => $key,
            ],
            array_merge([
                'value' => $value,
                'status' => self::STATUS_TRANSLATED,
                'translated_at' => now(),
            ], $attributes)
        );
    }

    /**
     * Get the appropriate plural form for a count.
     *
     * @since 1.5.0
     *
     * @param int $count Item count
     * @return string|null Plural form value
     */
    public function getPluralForm(int $count): ?string
    {
        if (!$this->plurals || empty($this->plurals)) {
            return $this->value;
        }

        // Simple English pluralization rules
        $rule = $this->getPluralRule($count);
        
        return $this->plurals[$rule] ?? $this->plurals['other'] ?? $this->value;
    }

    /**
     * Get the plural rule for a count.
     *
     * @since 1.5.0
     *
     * @param int $count Item count
     * @return string Plural rule (one, few, many, other)
     */
    protected function getPluralRule(int $count): string
    {
        // Simple English pluralization - can be extended for other languages
        if ($count === 1) {
            return 'one';
        }
        
        return 'other';
    }

    /**
     * Check if translation has a value.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function isTranslated(): bool
    {
        return !empty($this->value);
    }

    /**
     * Check if translation is approved.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if translation needs review.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function needsReview(): bool
    {
        return $this->needs_review;
    }

    /**
     * Check if translation is outdated.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function isOutdated(): bool
    {
        return $this->is_outdated;
    }

    /**
     * Record usage of this translation.
     *
     * @since 1.5.0
     *
     * @return void
     */
    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Approve the translation.
     *
     * @since 1.5.0
     *
     * @param int|null $reviewerId User ID of the reviewer
     * @return bool
     */
    public function approve(?int $reviewerId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'needs_review' => false,
            'is_fuzzy' => false,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Reject the translation.
     *
     * @since 1.5.0
     *
     * @param int|null $reviewerId User ID of the reviewer
     * @param string|null $comment Rejection comment
     * @return bool
     */
    public function reject(?int $reviewerId = null, ?string $comment = null): bool
    {
        $attributes = [
            'status' => self::STATUS_REJECTED,
            'needs_review' => false,
            'reviewer_id' => $reviewerId,
            'reviewed_at' => now(),
        ];

        if ($comment) {
            $attributes['comment'] = $comment;
        }

        return $this->update($attributes);
    }

    /**
     * Mark translation as fuzzy/uncertain.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function markFuzzy(): bool
    {
        return $this->update([
            'is_fuzzy' => true,
            'needs_review' => true,
        ]);
    }

    /**
     * Mark translation as outdated.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function markOutdated(): bool
    {
        return $this->update([
            'is_outdated' => true,
            'needs_review' => true,
        ]);
    }

    /**
     * Update translation quality score.
     *
     * @since 1.5.0
     *
     * @param float $score Quality score (0-100)
     * @return bool
     */
    public function updateQualityScore(float $score): bool
    {
        return $this->update(['quality_score' => max(0, min(100, $score))]);
    }

    /**
     * Get metadata value by key.
     *
     * @since 1.5.0
     *
     * @param string $key Metadata key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value by key.
     *
     * @since 1.5.0
     *
     * @param string $key Metadata key
     * @param mixed $value Value to set
     * @return bool
     */
    public function setMetadata(string $key, $value): bool
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        
        return $this->update(['metadata' => $metadata]);
    }

    /**
     * Update language statistics.
     *
     * @since 1.5.0
     *
     * @return void
     */
    protected function updateLanguageStats(): void
    {
        if (!$this->language) {
            return;
        }

        $total = static::where('language_id', $this->language_id)->count();
        $translated = static::where('language_id', $this->language_id)
            ->completed()
            ->count();

        $this->language->updateTranslationStats($total, $translated);
    }

    /**
     * Get status color class for UI display.
     *
     * @since 1.5.0
     *
     * @return string CSS class
     */
    public function getStatusColorClass(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'text-green-600',
            self::STATUS_REVIEWED => 'text-blue-600',
            self::STATUS_TRANSLATED => 'text-yellow-600',
            self::STATUS_REJECTED => 'text-red-600',
            default => 'text-gray-400',
        };
    }

    /**
     * Convert to array for API responses.
     *
     * @since 1.5.0
     *
     * @return array
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'language_code' => $this->language->code ?? null,
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->value,
            'plurals' => $this->plurals,
            'context' => $this->context,
            'status' => $this->status,
            'needs_review' => $this->needs_review,
            'is_fuzzy' => $this->is_fuzzy,
            'is_outdated' => $this->is_outdated,
            'quality_score' => $this->quality_score,
            'usage_count' => $this->usage_count,
            'translated_at' => $this->translated_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'last_used_at' => $this->last_used_at?->toISOString(),
        ];
    }
}