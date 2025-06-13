<?php
/**
 * Media Category Model
 *
 * Represents a category for media items in the database.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Models
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\Database\factories\MediaCategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class MediaCategory
 *
 * Eloquent model for the 'media_categories' table.
 *
 * @since 1.0.0
 *
 * @property int                     $id         The
 *           unique identifier for the media category.
 * @property string                  $name       The
 *           name of the media category.
 * @property string                  $slug       The
 *           unique slug for the media category.
 * @property Carbon                  $created_at The
 *           creation timestamp.
 * @property Carbon                  $updated_at The
 *           last update timestamp.
 * @property-read Collection|Media[] $media      The
 *                media items associated with this category.
 */
class MediaCategory extends Model
{
	use HasFactory;
	/**
	 * The factory that should be used to instantiate the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected static $factory = MediaCategoryFactory::class;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'media_categories';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $fillable = [
		'name',
		'slug',
	];

	/**
	 * The media that belongs to this category.
	 *
	 * @since 1.0.0
	 * @return BelongsToMany
	 */
	public function media(): BelongsToMany
	{
		return $this->belongsToMany( Media::class, 'media_media_category', 'category_id', 'media_id' );
	}
}
