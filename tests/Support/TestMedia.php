<?php

declare( strict_types=1 );

/**
 * Test fixture: a minimal Media model.
 *
 * Stands in for `\ArtisanPackUI\MediaLibrary\Models\Media` so that the
 * cms-framework test suite can exercise media-aware traits without
 * requiring the media-library package as a dev dependency.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class TestMedia extends Model
{
    protected $table = 'media';

    protected $guarded = [];
}
