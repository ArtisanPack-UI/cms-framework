<?php

declare( strict_types=1 );

/**
 * Test fixture: a content-type model that uses HasBlockContent.
 *
 * Used by `VisualEditorBridgeTest` to assert that custom content types
 * registered via `ContentTypeManager` are auto-picked-up into the
 * `ap.visual-editor.resources` filter when their model applies the
 * trait. No table is required — the bridge only inspects the class
 * for the trait, never instantiates or queries the model.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Tests\Support;

use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;
use Illuminate\Database\Eloquent\Model;

class TestBlockContentTypeModel extends Model
{
    use HasBlockContent;

    protected $table = 'test_block_content_type_models';
}
