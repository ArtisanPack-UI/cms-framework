<?php

declare(strict_types=1);

/**
 * Test fixture: a content-type model that does NOT use HasBlockContent.
 *
 * Used to verify that the bridge silently skips types whose models lack
 * the trait (and warns when they nevertheless declare editor support).
 *
 * @since 1.2.0
 */

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use Illuminate\Database\Eloquent\Model;

class TestPlainContentTypeModel extends Model
{
    protected $table = 'test_plain_content_type_models';
}
