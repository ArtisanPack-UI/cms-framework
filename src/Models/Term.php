<?php

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\Database\factories\TermFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Term Model.
 *
 * Represents an individual term within a taxonomy (e.g., 'Sports' as a 'Category' term)
 * in the ArtisanPack UI CMS Framework.
 *
 * @link    https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package ArtisanPackUI\CMSFramework\Models
 * @since   1.1.0
 *
 * @property int                        $id
 * @property string                     $name         The name of the term.
 * @property string                     $slug         The URL-friendly slug of the term.
 * @property int                        $taxonomy_id  Foreign key to the associated Taxonomy.
 * @property int|null                   $parent_id    Foreign key to a parent Term for hierarchical taxonomies.
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Term extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @since 1.1.0
     * @var string
     */
    protected $table = 'terms';

    /**
     * The attributes that are mass assignable.
     *
     * @since 1.1.0
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'taxonomy_id',
        'parent_id',
    ];

    /**
     * Get the taxonomy that this term belongs to.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function taxonomy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo( Taxonomy::class );
    }

    /**
     * Get the parent term for hierarchical terms.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo( Term::class, 'parent_id' );
    }

    /**
     * Get the child terms for hierarchical terms.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany( Term::class, 'parent_id' );
    }

    /**
     * The content items that are assigned to this term.
     *
     * Establishes a many-to-many relationship with content.
     *
     * @since 1.1.0
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function content(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany( Content::class, 'term_content', 'term_id', 'content_id' )
                    ->withTimestamps(); // If you added timestamps to the pivot table
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory(): Factory
    {
        return TermFactory::new();
    }
}
