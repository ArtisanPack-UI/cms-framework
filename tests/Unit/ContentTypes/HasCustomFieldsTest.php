<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasCustomFields;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only content-type model with a `metadata` JSON column, exercising
 * the HasCustomFields metadata-storage code path for filter-registered
 * fields.
 */
class TestHasCustomFieldsPost extends Model
{
    use HasCustomFields;

    protected $table = 'test_hcf_posts';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );

    Schema::dropIfExists( 'test_hcf_posts' );
    Schema::create( 'test_hcf_posts', function ( $table ): void {
        $table->id();
        $table->string( 'title' );
        $table->json( 'metadata' )->nullable();
        $table->timestamps();
    } );
} );

afterEach( function (): void {
    Schema::dropIfExists( 'test_hcf_posts' );
} );

test( 'reads filter-registered field value from metadata', function (): void {
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_note',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
        'default_value' => 'unset',
    ] );

    $post           = new TestHasCustomFieldsPost;
    $post->title    = 'Hello';
    $post->metadata = ['plugin_note' => 'stored via metadata'];
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->plugin_note )->toBe( 'stored via metadata' );
} );

test( 'writes filter-registered field value into metadata', function (): void {
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_bio',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post             = new TestHasCustomFieldsPost;
    $post->title      = 'Bio Post';
    $post->plugin_bio = 'A short bio.';
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->metadata )->toHaveKey( 'plugin_bio' );
    expect( $reloaded->metadata['plugin_bio'] )->toBe( 'A short bio.' );
    expect( $reloaded->plugin_bio )->toBe( 'A short bio.' );
} );

test( 'reads default_value when metadata is missing the key', function (): void {
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_default',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
        'default_value' => 'default from field',
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'No plugin data yet';
    $post->save();

    expect( $post->plugin_default )->toBe( 'default from field' );
} );

test( 'plugin custom field key colliding with a real column does NOT hijack the column', function (): void {
    // Ensures a rogue plugin cannot shadow a host attribute by registering
    // a filter-scoped custom field whose key matches a real DB column.
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Hijack Attempt',
        'key'           => 'title', // Real column on `test_hcf_posts`.
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->title )->toBe( 'Legit Title' );
    expect( $reloaded->metadata )->toBeNull();
} );

test( 'plugin custom field key colliding with a cast attribute does NOT hijack it', function (): void {
    // Casts declared via `casts()` method (per Laravel 12 convention) must
    // still short-circuit — the trait consults `getCasts()`, not `$casts`.
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Hijack Attempt',
        'key'           => 'metadata', // The cast attribute itself.
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post           = new TestHasCustomFieldsPost;
    $post->title    = 'Post';
    $post->metadata = ['user_supplied' => 'ok'];
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->metadata )->toBe( ['user_supplied' => 'ok'] );
} );

test( 'plugin custom field write on a model without metadata column fails loud', function (): void {
    // A HasCustomFields model that has no metadata column must not silently
    // drop plugin writes — throw so the plugin author sees the misuse.
    Schema::dropIfExists( 'test_no_meta_hcf' );
    Schema::create( 'test_no_meta_hcf', function ( $table ): void {
        $table->id();
        $table->string( 'title' );
        $table->timestamps();
    } );

    $model = new class extends Model {
        use HasCustomFields;

        protected $table = 'test_no_meta_hcf';

        protected $guarded = [];
    };

    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_only',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_no_meta_hcf'],
    ] );

    expect( fn () => $model->plugin_only = 'boom' )
        ->toThrow( RuntimeException::class, 'no `metadata` JSON column' );

    Schema::dropIfExists( 'test_no_meta_hcf' );
} );

test( 'getCustomFieldsForType is memoized per instance', function (): void {
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'memoized_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'x';
    $post->save();

    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();

    // First lookup populates the cache; subsequent lookups must not requery.
    $post->getCustomFieldsForType();
    $post->getCustomFieldsForType();
    $post->getCustomFieldsForType();

    expect( Illuminate\Support\Facades\DB::getQueryLog() )->toHaveCount( 1 );

    Illuminate\Support\Facades\DB::disableQueryLog();
} );

test( 'DB-registered field values still flow through physical columns', function (): void {
    Schema::table( 'test_hcf_posts', function ( $table ): void {
        $table->string( 'db_field' )->nullable();
    } );

    CustomField::create( [
        'name'          => 'DB Field',
        'key'           => 'db_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
        'order'         => 1,
        'required'      => false,
    ] );

    $post           = new TestHasCustomFieldsPost;
    $post->title    = 'DB Post';
    $post->db_field = 'column value';
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->db_field )->toBe( 'column value' );
    expect( $reloaded->metadata )->toBeNull();
} );
