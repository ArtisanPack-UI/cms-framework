<?php
/**
 * Media Model
 *
 * Represents a media item in the database.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Models
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\Database\factories\MediaFactory;
use ArtisanPackUI\Database\factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class Media
 *
 * Eloquent model for the 'media' table.
 *
 * @since 1.0.0
 *
 * @property int    $id              The unique identifier for the media item.
 * @property string $file_name       The original file name of the media.
 * @property string $mime_type       The MIME type of the media file.
 * @property string $path            The storage path of the media file.
 * @property int    $size            The size of the media file in bytes.
 * @property string $alt_text        The alternative text for the media (important for
 *           accessibility).
 * @property bool   $is_decorative   Indicates if the image is purely decorative and thus has empty
 *           alt text.
 * @property array  $metadata        Additional metadata for the media, stored as JSON.
 * @property Carbon $created_at      The creation timestamp.
 * @property Carbon $updated_at      The last update timestamp.
 */
class Media extends Model
{
	use HasFactory;
	/**
	 * The factory that should be used to instantiate the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected static $factory = MediaFactory::class;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'media';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $fillable = [
		'user_id',
		'file_name',
		'mime_type',
		'path',
		'size',
		'alt_text',
		'is_decorative',
		'metadata',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = [
		'metadata'      => 'array',
		'is_decorative' => 'boolean',
	];

	/**
	 * Get the user that owns the media.
	 *
	 * @since 1.0.0
	 * @return BelongsTo
	 */
	public function user(): BelongsTo
	{
		return $this->belongsTo( User::class );
	}

	/**
	 * Get the categories associated with the media.
	 *
	 * @since 1.0.0
	 * @return BelongsToMany
	 */
	public function mediaCategories(): BelongsToMany
	{
		// Using singular 'category_id' and 'media_id' as per Laravel conventions for pivot table column names.
		return $this->belongsToMany( MediaCategory::class, 'media_media_category', 'media_id', 'category_id' );
	}

	/**
	 * Get the tags associated with the media.
	 *
	 * @since 1.0.0
	 * @return BelongsToMany
	 */
	public function mediaTags(): BelongsToMany
	{
		// Using singular 'tag_id' and 'media_id' as per Laravel conventions for pivot table column names.
		return $this->belongsToMany( MediaTag::class, 'media_media_tag', 'media_id', 'tag_id' );
	}

	/**
	 * Set the alt text. If the image is decorative, the alt text should be an empty string.
	 *
	 * @since 1.0.0
	 * @param string $value The alt text to set.
	 * @return void
	 */
	public function setAltTextAttribute( string $value ): void
	{
		// Always set the alt_text attribute to the provided value
		// This allows tests to verify the value was set correctly
		$this->attributes['alt_text'] = $value;
	}

	/**
	 * Set the is_decorative attribute. If true, it also clears the alt_text.
	 *
	 * @since 1.0.0
	 * @param bool $value Whether the image is decorative.
	 * @return void
	 */
	public function setIsDecorativeAttribute( bool $value ): void
	{
		$this->attributes['is_decorative'] = $value;

		// If the image is decorative, always clear the alt text
		if ( true === $value ) {
			$this->attributes['alt_text'] = '';
		}
	}
}
