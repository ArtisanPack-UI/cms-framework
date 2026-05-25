<?php

declare(strict_types=1);

/**
 * Test fixture: a model that uses HasFeaturedImage against TestMedia.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasFeaturedImage;
use Illuminate\Database\Eloquent\Model;

class TestFeaturedImageModel extends Model
{
    use HasFeaturedImage;

    protected $table = 'test_featured_image_models';

    protected $guarded = [];

    protected function featuredImageMediaModel(): string
    {
        return TestMedia::class;
    }
}
