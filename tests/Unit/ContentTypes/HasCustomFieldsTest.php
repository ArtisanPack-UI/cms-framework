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

    // The column listing is cached per model class for the life of the
    // process, and this table is recreated per test — without the flush,
    // later tests read an earlier test's column list. See CF-7 / TEST-3.
    TestHasCustomFieldsPost::flushCustomFieldsRealColumnsCache();
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

    // Without this the stale column cache reports `sku` as not-a-column, the
    // shadow guard never fires, and the value writes by ordinary fall-through
    // — so the exemption this test exists to prove is never reached (TEST-3).
    TestHasCustomFieldsPost::flushCustomFieldsRealColumnsCache();

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

test( 'applyCustomFieldValues cannot rewrite Eloquent internals to write another table', function (): void {
    // CF-1: `applyCustomFieldValues()` runs in trait ( class ) scope, where
    // `$this->{$key} = $value` resolves Eloquent's own protected properties
    // directly instead of falling through to `__set()`. A payload carrying
    // `table` / `exists` / `attributes` would repoint the model at another
    // table and issue an UPDATE against it on `save()`.
    Schema::dropIfExists( 'test_hcf_victims' );
    Schema::create( 'test_hcf_victims', function ( $table ): void {
        $table->id();
        $table->string( 'password' );
        $table->timestamps();
    } );

    Illuminate\Support\Facades\DB::table( 'test_hcf_victims' )->insert( [
        'id'       => 1,
        'password' => 'original-hash',
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Post';
    $post->applyCustomFieldValues( [
        'table'      => 'test_hcf_victims',
        'exists'     => true,
        'attributes' => ['id' => 1, 'password' => 'ATTACKER-HASH'],
    ] );
    $post->save();

    expect( $post->getTable() )->toBe( 'test_hcf_posts' );
    expect(
        Illuminate\Support\Facades\DB::table( 'test_hcf_victims' )->where( 'id', 1 )->value( 'password' ),
    )->toBe( 'original-hash' );
    expect( Illuminate\Support\Facades\DB::table( 'test_hcf_posts' )->count() )->toBe( 1 );
    expect( Illuminate\Support\Facades\DB::table( 'test_hcf_posts' )->value( 'title' ) )->toBe( 'Legit Post' );

    Schema::dropIfExists( 'test_hcf_victims' );
} );

test( 'applyCustomFieldValues drops Eloquent property keys that are not registered fields', function (): void {
    // CF-1, narrower: none of Eloquent's own non-static properties may be
    // reachable from an untrusted payload.
    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Post';
    $post->applyCustomFieldValues( [
        'connection'   => 'nope',
        'primaryKey'   => 'title',
        'incrementing' => false,
        'perPage'      => 9999,
        'guarded'      => [],
        'hidden'       => ['title'],
        'timestamps'   => false,
        'appends'      => ['pwned'],
    ] );
    $post->save();

    expect( $post->getConnectionName() )->not->toBe( 'nope' );
    expect( $post->getKeyName() )->toBe( 'id' );
    expect( $post->getIncrementing() )->toBeTrue();
    expect( $post->getPerPage() )->toBe( 15 );
    expect( $post->getHidden() )->toBe( [] );
    expect( $post->usesTimestamps() )->toBeTrue();
} );

test( 'applyCustomFieldValues drops case-variant keys naming a real column on insert', function (): void {
    // CF-2: `Schema::getColumnListing()` returns canonical case and the guard
    // compares strictly, but MySQL and SQLite resolve identifiers
    // case-insensitively — so `TITLE` writes the real `title` column.
    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->applyCustomFieldValues( ['TITLE' => 'Hijacked Title'] );
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->title )->toBe( 'Legit Title' );
} );

test( 'applyCustomFieldValues drops case-variant keys naming a real column on update', function (): void {
    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );
    $reloaded->applyCustomFieldValues( ['TITLE' => 'Hijacked Title'] );
    $reloaded->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->title )->toBe( 'Legit Title' );
} );

test( 'applyCustomFieldValues drops a JSON-path key targeting the metadata column', function (): void {
    // CF-3: `metadata->x` is not a hydrated attribute, cast, column, method
    // or relation, so the reserved check misses it — and `setAttribute()`
    // routes `->` keys to `fillJsonAttribute()`, writing the real column.
    $post           = new TestHasCustomFieldsPost;
    $post->title    = 'Meta Post';
    $post->metadata = ['kept' => 'yes'];
    $post->applyCustomFieldValues( ['metadata->injected' => 'pwned'] );
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->metadata )->toBe( ['kept' => 'yes'] );
} );

test( 'applyCustomFieldValues drops a JSON-path key targeting a non-JSON column', function (): void {
    // CF-3, second half: `title->x` clobbers the column with a JSON string.
    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->applyCustomFieldValues( ['title->x' => 'y'] );
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->title )->toBe( 'Legit Title' );
} );

test( 'applyCustomFieldValues drops an unregistered key that names no column', function (): void {
    // CF-5: the denylist shape let an unregistered, non-column key fall
    // through to an attribute with no column, so `save()` raised a
    // QueryException — a one-request DoS on the editor save path.
    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Legit Title';
    $post->applyCustomFieldValues( ['bogus_key' => 'whatever'] );
    $post->save();

    $reloaded = TestHasCustomFieldsPost::find( $post->id );

    expect( $reloaded->title )->toBe( 'Legit Title' );
    expect( $reloaded->metadata )->toBeNull();
} );

test( 'applyCustomFieldValues does not throw on an unregistered key for a model without metadata', function (): void {
    // CF-5, second half: the same input reached assertMetadataColumnAvailable()
    // and raised a RuntimeException from untrusted input.
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

    $model::flushCustomFieldsRealColumnsCache();

    $model->title = 'Legit Title';
    $model->applyCustomFieldValues( ['bogus_key' => 'whatever'] );
    $model->save();

    expect( $model->fresh()->title )->toBe( 'Legit Title' );

    Schema::dropIfExists( 'test_no_meta_hcf' );
} );

test( 'the real-column cache is flushed when a custom field column is removed', function (): void {
    // CF-7: nothing ever invalidated $customFieldsRealColumnsCache, so a key
    // whose column was dropped mid-process stayed permanently dropped even
    // after being re-registered as metadata storage.
    $manager = app( CustomFieldManager::class );

    Schema::table( 'test_hcf_posts', function ( $table ): void {
        $table->string( 'legacy_code' )->nullable();
    } );
    TestHasCustomFieldsPost::flushCustomFieldsRealColumnsCache();

    $field = CustomField::create( [
        'name'          => 'Legacy Code',
        'key'           => 'legacy_code',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
        'order'         => 1,
        'required'      => false,
    ] );

    // Warm the cache with the column present.
    ( new TestHasCustomFieldsPost )->applyCustomFieldValues( ['legacy_code' => 'warm'] );

    $manager->removeColumnFromTable( $field, 'test_hcf_posts' );
    $field->delete();

    $manager->registerField( [
        'name'          => 'Legacy Code',
        'key'           => 'legacy_code',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'After Removal';
    $post->applyCustomFieldValues( ['legacy_code' => 'now in metadata'] );
    $post->save();

    expect( TestHasCustomFieldsPost::find( $post->id )->metadata )->toBe( ['legacy_code' => 'now in metadata'] );
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

test( 'registering a reserved key logs a warning instead of failing silently', function (): void {
    // CF-6/CF-10: a key that can never resolve used to produce no signal at
    // all, so a plugin author saw only "my field doesn't save".
    //
    // Note what is NOT changed here: a key colliding with a model method stays
    // reserved. Narrowing the bare method_exists() check would buy nothing,
    // because Laravel implements isRelation() as
    // `method_exists() || relationResolver()` and catches those keys anyway —
    // and loosening *that* would let a plugin shadow a real relation.
    Illuminate\Support\Facades\Log::spy();

    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Author',
        'key'           => 'author_id',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    Illuminate\Support\Facades\Log::shouldHaveReceived( 'warning' )
        ->withArgs( fn ( string $message, array $context = [] ) => str_contains( $message, 'reserved key' )
            && 'author_id' === ( $context['key'] ?? null ) );
} );

test( 'a dropped payload key is logged rather than vanishing silently', function (): void {
    // CF-10.
    Illuminate\Support\Facades\Log::spy();

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Probe';
    $post->applyCustomFieldValues( ['author_id' => 1] );

    Illuminate\Support\Facades\Log::shouldHaveReceived( 'warning' )
        ->withArgs( fn ( string $message, array $context = [] ) => str_contains( $message, 'Dropped a custom-field payload key' )
            && 'author_id' === ( $context['key'] ?? null ) );
} );

test( 'a column-storage field explicitly set to null does not read back as the default', function (): void {
    // CF-9: the default was substituted whenever the value read null, rather
    // than only when the attribute was absent — so a value an editor
    // deliberately cleared came back as the default.
    //
    // Note the setup: the field is DB-persisted (so storageMode() is
    // `column`) but the physical column does not exist, which is the only
    // state in which __get()'s column branch is reachable — on a real column,
    // findCustomFieldByKey() short-circuits at the reserved check first.
    CustomField::create( [
        'name'          => 'Shelf',
        'key'           => 'shelf',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
        'order'         => 1,
        'required'      => false,
        'default_value' => 'unshelved',
    ] );

    $post = new TestHasCustomFieldsPost;

    // Attribute absent → the default is the right answer.
    expect( $post->shelf )->toBe( 'unshelved' );

    // Attribute present and explicitly null → the clear must stick.
    $post->setAttribute( 'shelf', null );

    expect( $post->shelf )->toBeNull();
} );

test( 'the default is still used for a field the model never loaded', function (): void {
    // CF-9 must not break the case the substitution exists for.
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Blurb',
        'key'           => 'plugin_blurb_default',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
        'default_value' => 'a default',
    ] );

    $post        = new TestHasCustomFieldsPost;
    $post->title = 'Defaulted';
    $post->save();

    expect( $post->plugin_blurb_default )->toBe( 'a default' );
} );

test( 'the custom field list is queried once per content type across a collection', function (): void {
    // CF-8: the memo lived on the model *instance*, so an unknown attribute
    // access on each model in a collection ran its own query — 50 posts, 50
    // queries — and bootHasCustomFields() flushed it on every save.
    app( CustomFieldManager::class )->registerField( [
        'name'          => 'Note',
        'key'           => 'plugin_note_n1',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test_hcf_posts'],
    ] );

    foreach ( range( 1, 5 ) as $i ) {
        $post        = new TestHasCustomFieldsPost;
        $post->title = "Post {$i}";
        $post->save();
    }

    $posts = TestHasCustomFieldsPost::all();

    // Deliberately no cache warm-up: the assertion is that the field list is
    // fetched once for the whole collection, not once per model. Warming it
    // first would let the per-instance-memo regression pass unnoticed.
    app( CustomFieldManager::class )->flushFieldCache();

    Illuminate\Support\Facades\DB::enableQueryLog();
    Illuminate\Support\Facades\DB::flushQueryLog();

    foreach ( $posts as $post ) {
        $post->plugin_note_n1;
    }

    expect( Illuminate\Support\Facades\DB::getQueryLog() )->toHaveCount( 1 );

    Illuminate\Support\Facades\DB::disableQueryLog();
} );
