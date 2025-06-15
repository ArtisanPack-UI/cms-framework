<?php
/**
 * Page Model
 *
 * Represents a website page within the CMS framework.
 *
 * @link       https://gitlab.com/jacob-martella-web-design/artisanpack-ui/artisanpack-ui-cms-framework
 *
 * @package    ArtisanPackUI\CMSFramework
 * @subpackage ArtisanPackUI\CMSFramework\Models
 * @since      1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Models;

use ArtisanPackUI\Database\factories\PageFactory;
use ArtisanPackUI\Database\factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a website page.
 *
 * This model defines the structure and relationships for a page
 * on the public-facing website.
 *
 * @since 1.0.0
 */
class Page extends Model
{
	use HasFactory;

	/**
	 * The factory that should be used to instantiate the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected static $factory = PageFactory::class;

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'pages';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $fillable = [
		'title',
		'slug',
		'content',
		'status',
		'user_id', // Add user_id to fillable attributes
		'parent_id',
		'order',
		'published_at',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = [
		'published_at' => 'datetime',
	];

	/**
	 * Get the user that owns the page.
	 *
	 * @since 1.0.0
	 * @return BelongsTo
	 */
	public function user(): BelongsTo
	{
		// Assuming your User model is at App\Models\User or similar
		return $this->belongsTo( User::class );
	}

	/**
	 * Get the parent page.
	 *
	 * @since 1.0.0
	 * @return BelongsTo
	 */
	public function parent()
	{
		return $this->belongsTo( Page::class, 'parent_id' );
	}

	/**
	 * Get the children pages.
	 *
	 * @since 1.0.0
	 * @return HasMany
	 */
	public function children()
	{
		return $this->hasMany( Page::class, 'parent_id' );
	}
}
