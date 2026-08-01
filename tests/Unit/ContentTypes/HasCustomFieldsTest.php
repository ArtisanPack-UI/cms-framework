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

test( 'applyCustomFieldValues routes a registered metadata field into the metadata column', function (): void {
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_subtitle',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Payload Post';
    $post->applyCustomFieldValues( ['plugin_subtitle' => 'From the payload'] );
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->metadata )->toBe( ['plugin_subtitle' => 'From the payload'] );
    expect( $reloaded->plugin_subtitle )->toBe( 'From the payload' );
} );

test( 'applyCustomFieldValues silently drops a metadata field whose key names a real column', function (): void {
    // The #253 attack: a plugin filter-registers a metadata field keyed to a
    // real column, so an untrusted custom-field payload carrying that key
    // reaches `parent::__set()` and overwrites the column. The payload key
    // must be dropped — neither the column nor the metadata JSON may change.
    // `title` is a real column on `test_hcf_posts`.
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Hijack Attempt',
        'key'           => 'title',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->applyCustomFieldValues( ['title' => 'Hijacked Title'] );
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->title )->toBe( 'Legit Title' );
    expect( $reloaded->metadata )->toBeNull();
} );

test( 'applyCustomFieldValues drops a real-column key even when nothing registered it', function (): void {
    // The payload is untrusted whether or not a plugin registered the key,
    // so an unregistered column name must be dropped on the same terms.
    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->applyCustomFieldValues( ['title' => 'Hijacked Title'] );
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->title )->toBe( 'Legit Title' );
} );

test( 'applyCustomFieldValues drops a key naming the metadata column itself', function (): void {
    // `metadata` is both a real column and a cast attribute; a payload key
    // matching it would otherwise replace the entire custom-field store.
    $post           = new TestHasCustomFieldsPost;
    $post->title    = 'Meta Post';
    $post->metadata = ['kept' => 'yes'];
    $post->applyCustomFieldValues( ['metadata' => ['wiped' => 'everything']] );
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->metadata )->toBe( ['kept' => 'yes'] );
} );

test( 'applyCustomFieldValues applies the safe keys alongside a dropped one', function (): void {
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_blurb',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Mixed Payload';
    $post->applyCustomFieldValues( [
        'title'        => 'Hijacked Title',
        'plugin_blurb' => 'A blurb.',
    ] );
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->title )->toBe( 'Mixed Payload' );
    expect( $reloaded->metadata )->toBe( ['plugin_blurb' => 'A blurb.'] );
} );

test( 'applyCustomFieldValues writes a DB-registered field into its own column', function (): void {
    // DB-registered fields are always column-storage and own a physical
    // column, so the shadow-column drop must not swallow their values —
    // otherwise every admin-created custom field stops persisting.
    Schema::table( 'test_hcf_posts', function ( $table ): void {
        $table->string( 'sku' )->nullable();
    } );

    CustomField::create( [
        'name'          => 'SKU',
        'key'           => 'sku',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
        'order'         => 1,
        'required'      => false,
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'DB Field Payload';
    $post->applyCustomFieldValues( ['sku' => 'ABC-123'] );
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->sku )->toBe( 'ABC-123' );
    expect( $reloaded->metadata )->toBeNull();
} );

test( 'applyCustomFieldValues ignores a filter registration claiming column storage', function (): void {
    // The DB-registered exemption keys off `exists`, not the declared
    // storage mode. A plugin declaring `storage => column` on a key that
    // names a real column must still be dropped, or #253 reopens verbatim.
    addFilter( 'ap.contentTypes.registeredCustomFields', function ( array $fields ): array {
        $fields['title'] = [
            'key'           => 'title',
            'name'          => 'Hijack Attempt',
            'type'          => 'text',
            'column_type'   => 'string',
            'content_types' => ['test_hcf_posts'],
            'storage'       => 'column',
        ];

        return $fields;
    } );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->applyCustomFieldValues( ['title' => 'Hijacked Title'] );
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->title )->toBe( 'Legit Title' );
} );

test( 'direct assignment to a real column still wins over a shadowing registration', function (): void {
    // The guard belongs to the untrusted payload path only. Host code
    // writing its own column must keep working, otherwise any plugin could
    // brick saves by registering a field keyed to a required column.
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Hijack Attempt',
        'key'           => 'title',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Written By The Host';
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->title )->toBe( 'Written By The Host' );
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
