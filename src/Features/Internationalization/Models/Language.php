<?php

namespace ArtisanPackUI\CMSFramework\Features\Internationalization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Language Model
 *
 * Manages language configurations and settings for the internationalization system
 * in the ArtisanPack UI CMS Framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework\Features\Internationalization\Models
 * @since      1.5.0
 *
 * @property int                 $id
 * @property string              $code                    Language code (e.g., en, es, fr)
 * @property string|null         $iso_code                ISO 639-1 or 639-2 language code
 * @property string              $locale                  Full locale code (e.g., en_US, es_ES)
 * @property string              $name                    Native language name
 * @property string              $english_name            English language name
 * @property string|null         $flag_emoji              Flag emoji representation
 * @property string|null         $country_code            Associated country code
 * @property bool                $is_rtl                  Whether language is right-to-left
 * @property string              $direction               Text direction (ltr/rtl)
 * @property string              $date_format             Preferred date format
 * @property string              $time_format             Preferred time format
 * @property string              $number_format           Number formatting locale
 * @property bool                $is_active               Whether language is available for use
 * @property bool                $is_default              Whether this is the default language
 * @property bool                $is_fallback             Whether this language serves as fallback
 * @property int                 $sort_order              Display order in lists
 * @property float               $completion_percentage   Translation completion percentage
 * @property int                 $total_strings           Total translatable strings
 * @property int                 $translated_strings      Number of translated strings
 * @property Carbon|null         $last_updated_at         Last translation update
 * @property array|null          $metadata                Additional language-specific metadata
 * @property Carbon              $created_at
 * @property Carbon              $updated_at
 */
class Language extends Model
{
    /**
     * The table associated with the model.
     *
     * @since 1.5.0
     *
     * @var string
     */
    protected $table = 'languages';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.5.0
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'iso_code',
        'locale',
        'name',
        'english_name',
        'flag_emoji',
        'country_code',
        'is_rtl',
        'direction',
        'date_format',
        'time_format',
        'number_format',
        'is_active',
        'is_default',
        'is_fallback',
        'sort_order',
        'completion_percentage',
        'total_strings',
        'translated_strings',
        'last_updated_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @since 1.5.0
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_rtl' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_fallback' => 'boolean',
        'completion_percentage' => 'decimal:2',
        'last_updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @since 1.5.0
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'metadata',
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

        // Ensure only one default language exists
        static::saving(function (Language $language) {
            if ($language->is_default) {
                static::where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // Update direction based on RTL setting
        static::saving(function (Language $language) {
            $language->direction = $language->is_rtl ? 'rtl' : 'ltr';
        });

        // Update completion percentage when translation counts change
        static::saving(function (Language $language) {
            if ($language->total_strings > 0) {
                $language->completion_percentage = 
                    round(($language->translated_strings / $language->total_strings) * 100, 2);
            }
        });
    }

    /**
     * Get translations for this language.
     *
     * @since 1.5.0
     *
     * @return HasMany
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    /**
     * Scope a query to only include active languages.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to get the default language.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to get fallback languages.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFallback(Builder $query): Builder
    {
        return $query->where('is_fallback', true);
    }

    /**
     * Scope a query to get RTL languages.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRtl(Builder $query): Builder
    {
        return $query->where('is_rtl', true);
    }

    /**
     * Scope a query to order languages by sort order.
     *
     * @since 1.5.0
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('english_name');
    }

    /**
     * Get the default language.
     *
     * @since 1.5.0
     *
     * @return Language|null
     */
    public static function getDefault(): ?Language
    {
        return static::default()->first();
    }

    /**
     * Get the fallback language.
     *
     * @since 1.5.0
     *
     * @return Language|null
     */
    public static function getFallback(): ?Language
    {
        return static::fallback()->first();
    }

    /**
     * Get active languages ordered by sort order.
     *
     * @since 1.5.0
     *
     * @return Collection
     */
    public static function getActive(): Collection
    {
        return static::active()->ordered()->get();
    }

    /**
     * Find a language by its code.
     *
     * @since 1.5.0
     *
     * @param string $code Language code
     * @return Language|null
     */
    public static function findByCode(string $code): ?Language
    {
        return static::where('code', $code)->first();
    }

    /**
     * Find a language by its locale.
     *
     * @since 1.5.0
     *
     * @param string $locale Locale code
     * @return Language|null
     */
    public static function findByLocale(string $locale): ?Language
    {
        return static::where('locale', $locale)->first();
    }

    /**
     * Get languages that support RTL.
     *
     * @since 1.5.0
     *
     * @return Collection
     */
    public static function getRtlLanguages(): Collection
    {
        return static::rtl()->active()->get();
    }

    /**
     * Check if this language is the default language.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Check if this language is a fallback language.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function isFallback(): bool
    {
        return $this->is_fallback;
    }

    /**
     * Check if this language supports RTL.
     *
     * @since 1.5.0
     *
     * @return bool
     */
    public function isRtl(): bool
    {
        return $this->is_rtl;
    }

    /**
     * Get the display name for the language.
     *
     * @since 1.5.0
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        $name = $this->name;
        
        if ($this->flag_emoji) {
            $name = $this->flag_emoji . ' ' . $name;
        }
        
        return $name;
    }

    /**
     * Get formatted date using this language's format.
     *
     * @since 1.5.0
     *
     * @param Carbon|string $date Date to format
     * @return string Formatted date
     */
    public function formatDate($date): string
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        
        return $date->format($this->date_format);
    }

    /**
     * Get formatted time using this language's format.
     *
     * @since 1.5.0
     *
     * @param Carbon|string $time Time to format
     * @return string Formatted time
     */
    public function formatTime($time): string
    {
        if (is_string($time)) {
            $time = Carbon::parse($time);
        }
        
        return $time->format($this->time_format);
    }

    /**
     * Get formatted datetime using this language's formats.
     *
     * @since 1.5.0
     *
     * @param Carbon|string $datetime DateTime to format
     * @return string Formatted datetime
     */
    public function formatDateTime($datetime): string
    {
        if (is_string($datetime)) {
            $datetime = Carbon::parse($datetime);
        }
        
        return $datetime->format($this->date_format . ' ' . $this->time_format);
    }

    /**
     * Format a number using this language's locale.
     *
     * @since 1.5.0
     *
     * @param float $number Number to format
     * @param int $decimals Number of decimal places
     * @return string Formatted number
     */
    public function formatNumber(float $number, int $decimals = 0): string
    {
        if (function_exists('numfmt_create')) {
            $formatter = \NumberFormatter::create($this->locale, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
            return $formatter->format($number);
        }
        
        // Fallback to PHP's number_format
        return number_format($number, $decimals);
    }

    /**
     * Update translation statistics for this language.
     *
     * @since 1.5.0
     *
     * @param int $totalStrings Total number of translatable strings
     * @param int $translatedStrings Number of translated strings
     * @return bool
     */
    public function updateTranslationStats(int $totalStrings, int $translatedStrings): bool
    {
        return $this->update([
            'total_strings' => $totalStrings,
            'translated_strings' => $translatedStrings,
            'last_updated_at' => now(),
        ]);
    }

    /**
     * Get completion status as a descriptive string.
     *
     * @since 1.5.0
     *
     * @return string
     */
    public function getCompletionStatus(): string
    {
        $percentage = $this->completion_percentage;
        
        return match (true) {
            $percentage >= 100 => 'Complete',
            $percentage >= 90 => 'Nearly Complete',
            $percentage >= 75 => 'Mostly Complete',
            $percentage >= 50 => 'Partially Complete',
            $percentage >= 25 => 'In Progress',
            $percentage > 0 => 'Started',
            default => 'Not Started',
        };
    }

    /**
     * Get CSS class for completion percentage.
     *
     * @since 1.5.0
     *
     * @return string
     */
    public function getCompletionCssClass(): string
    {
        $percentage = $this->completion_percentage;
        
        return match (true) {
            $percentage >= 90 => 'text-green-600',
            $percentage >= 75 => 'text-lime-600',
            $percentage >= 50 => 'text-yellow-600',
            $percentage >= 25 => 'text-orange-600',
            $percentage > 0 => 'text-red-600',
            default => 'text-gray-400',
        };
    }

    /**
     * Get metadata value by key.
     *
     * @since 1.5.0
     *
     * @param string $key Metadata key
     * @param mixed $default Default value if key not found
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
            'code' => $this->code,
            'locale' => $this->locale,
            'name' => $this->name,
            'english_name' => $this->english_name,
            'flag_emoji' => $this->flag_emoji,
            'is_rtl' => $this->is_rtl,
            'direction' => $this->direction,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'completion_percentage' => $this->completion_percentage,
            'completion_status' => $this->getCompletionStatus(),
        ];
    }
}