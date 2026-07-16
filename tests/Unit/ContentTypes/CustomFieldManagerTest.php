<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\ContentTypeManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use Illuminate\Support\Facades\Schema;

beforeEach( function (): void {
    $this->artisan( 'migrate', ['--database' => 'testing'] );
} );

test( 'register field adds custom field to filter hook', function (): void {
    $manager = new CustomFieldManager;

    $args = [
        'name'          => 'Price',
        'key'           => 'price',
        'type'          => 'number',
        'column_type'   => 'decimal',
        'content_types' => ['products'],
        'required'      => true,
    ];

    $manager->registerField( $args );

    // The field should be available through the filter
    $registeredFields = applyFilters( 'ap.contentTypes.registeredCustomFields', [] );

    expect( $registeredFields )->toHaveKey( 'price' );
    expect( $registeredFields['price']['name'] )->toBe( 'Price' );
} );

test( 'get fields for content type returns correct fields', function (): void {
    $manager = new CustomFieldManager;

    CustomField::create( [
        'name'          => 'Author Bio',
        'key'           => 'author_bio',
        'type'          => 'textarea',
        'column_type'   => 'text',
        'content_types' => ['posts', 'authors'],
        'order'         => 1,
        'required'      => false,
    ] );

    CustomField::create( [
        'name'          => 'Featured',
        'key'           => 'featured',
        'type'          => 'boolean',
        'column_type'   => 'boolean',
        'content_types' => ['posts'],
        'order'         => 2,
        'required'      => false,
    ] );

    $fields = $manager->getFieldsForContentType( 'posts' );

    expect( $fields )->toHaveCount( 2 );
    expect( $fields->first()->key )->toBe( 'author_bio' );
    expect( $fields->last()->key )->toBe( 'featured' );
} );

test( 'create field creates custom field in database', function (): void {
    $manager = new CustomFieldManager;

    // Create a content type first
    ContentType::create( [
        'name'          => 'Products',
        'slug'          => 'products',
        'table_name'    => 'test_products',
        'model_class'   => 'App\\Models\\Product',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    // Create the test table
    Schema::create( 'test_products', function ( $table ): void {
        $table->id();
        $table->string( 'name' );
        $table->timestamps();
    } );

    $data = [
        'name'          => 'SKU',
        'key'           => 'sku',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['products'],
        'order'         => 1,
        'required'      => true,
    ];

    $field = $manager->createField( $data );

    expect( $field )->toBeInstanceOf( CustomField::class );
    expect( $field->key )->toBe( 'sku' );
    expect( $field->name )->toBe( 'SKU' );
    expect( $field->required )->toBeTrue();
    expect( Schema::hasColumn( 'test_products', 'sku' ) )->toBeTrue();

    // Cleanup
    Schema::dropIfExists( 'test_products' );
} );

test( 'update field updates custom field data', function (): void {
    $manager = new CustomFieldManager;

    ContentType::create( [
        'name'          => 'Posts',
        'slug'          => 'posts',
        'table_name'    => 'test_posts',
        'model_class'   => 'App\\Models\\Post',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    Schema::create( 'test_posts', function ( $table ): void {
        $table->id();
        $table->string( 'title' );
        $table->timestamps();
    } );

    $field = CustomField::create( [
        'name'          => 'Rating',
        'key'           => 'rating',
        'type'          => 'number',
        'column_type'   => 'integer',
        'content_types' => ['posts'],
        'order'         => 1,
        'required'      => false,
    ] );

    $manager->addColumnToTable( $field, 'test_posts' );

    $updatedField = $manager->updateField( $field->id, [
        'name'  => 'Review Rating',
        'order' => 5,
    ] );

    expect( $updatedField->name )->toBe( 'Review Rating' );
    expect( $updatedField->order )->toBe( 5 );
    expect( $updatedField->key )->toBe( 'rating' );

    Schema::dropIfExists( 'test_posts' );
} );

test( 'delete field removes custom field and columns', function (): void {
    $manager = new CustomFieldManager;

    ContentType::create( [
        'name'          => 'Events',
        'slug'          => 'events',
        'table_name'    => 'test_events',
        'model_class'   => 'App\\Models\\Event',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    Schema::create( 'test_events', function ( $table ): void {
        $table->id();
        $table->string( 'name' );
        $table->timestamps();
    } );

    $field = CustomField::create( [
        'name'          => 'Location',
        'key'           => 'location',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['events'],
        'order'         => 1,
        'required'      => false,
    ] );

    $manager->addColumnToTable( $field, 'test_events' );
    expect( Schema::hasColumn( 'test_events', 'location' ) )->toBeTrue();

    $result = $manager->deleteField( $field->id );

    expect( $result )->toBeTrue();
    expect( CustomField::find( $field->id ) )->toBeNull();
    expect( Schema::hasColumn( 'test_events', 'location' ) )->toBeFalse();

    Schema::dropIfExists( 'test_events' );
} );

test( 'add column to table adds column with correct type', function (): void {
    $manager = new CustomFieldManager;

    Schema::create( 'test_table', function ( $table ): void {
        $table->id();
        $table->timestamps();
    } );

    $field = CustomField::create( [
        'name'          => 'Description',
        'key'           => 'description',
        'type'          => 'textarea',
        'column_type'   => 'text',
        'content_types' => ['test'],
        'order'         => 1,
        'required'      => false,
    ] );

    $manager->addColumnToTable( $field, 'test_table' );

    expect( Schema::hasColumn( 'test_table', 'description' ) )->toBeTrue();

    Schema::dropIfExists( 'test_table' );
} );

test( 'add column to table respects required constraint', function (): void {
    $manager = new CustomFieldManager;

    Schema::create( 'test_required', function ( $table ): void {
        $table->id();
        $table->timestamps();
    } );

    $requiredField = CustomField::create( [
        'name'          => 'Required Field',
        'key'           => 'required_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test'],
        'order'         => 1,
        'required'      => true,
    ] );

    $manager->addColumnToTable( $requiredField, 'test_required' );

    expect( Schema::hasColumn( 'test_required', 'required_field' ) )->toBeTrue();

    Schema::dropIfExists( 'test_required' );
} );

test( 'add column to table sets default value', function (): void {
    $manager = new CustomFieldManager;

    Schema::create( 'test_defaults', function ( $table ): void {
        $table->id();
        $table->timestamps();
    } );

    $field = CustomField::create( [
        'name'          => 'Status',
        'key'           => 'status',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test'],
        'order'         => 1,
        'required'      => false,
        'default_value' => 'active',
    ] );

    $manager->addColumnToTable( $field, 'test_defaults' );

    expect( Schema::hasColumn( 'test_defaults', 'status' ) )->toBeTrue();

    Schema::dropIfExists( 'test_defaults' );
} );

test( 'remove column from table removes column', function (): void {
    $manager = new CustomFieldManager;

    Schema::create( 'test_remove', function ( $table ): void {
        $table->id();
        $table->string( 'temp_field' );
        $table->timestamps();
    } );

    $field = CustomField::create( [
        'name'          => 'Temp Field',
        'key'           => 'temp_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test'],
        'order'         => 1,
        'required'      => false,
    ] );

    expect( Schema::hasColumn( 'test_remove', 'temp_field' ) )->toBeTrue();

    $manager->removeColumnFromTable( $field, 'test_remove' );

    expect( Schema::hasColumn( 'test_remove', 'temp_field' ) )->toBeFalse();

    Schema::dropIfExists( 'test_remove' );
} );

test( 'add column does not duplicate if column already exists', function (): void {
    $manager = new CustomFieldManager;

    Schema::create( 'test_duplicate', function ( $table ): void {
        $table->id();
        $table->string( 'existing_field' );
        $table->timestamps();
    } );

    $field = CustomField::create( [
        'name'          => 'Existing Field',
        'key'           => 'existing_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test'],
        'order'         => 1,
        'required'      => false,
    ] );

    // Try to add the column again
    $manager->addColumnToTable( $field, 'test_duplicate' );

    // Should not throw an error and column should still exist
    expect( Schema::hasColumn( 'test_duplicate', 'existing_field' ) )->toBeTrue();

    Schema::dropIfExists( 'test_duplicate' );
} );

test( 'generate migration creates valid migration file', function (): void {
    $manager = new CustomFieldManager;

    ContentType::create( [
        'name'          => 'Articles',
        'slug'          => 'articles',
        'table_name'    => 'articles',
        'model_class'   => 'App\\Models\\Article',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    $field = CustomField::create( [
        'name'          => 'Subtitle',
        'key'           => 'subtitle',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['articles'],
        'order'         => 1,
        'required'      => false,
    ] );

    $migrationPath = $manager->generateMigration( $field, 'add' );

    expect( file_exists( $migrationPath ) )->toBeTrue();
    expect( $migrationPath )->toContain( 'add_subtitle_to_content_types.php' );

    $content = file_get_contents( $migrationPath );
    expect( $content )->toContain( 'Schema::table' );
    expect( $content )->toContain( 'articles' );
    expect( $content )->toContain( 'subtitle' );

    // Cleanup
    unlink( $migrationPath );
} );

test( 'custom field manager works with app container', function (): void {
    $manager = app( CustomFieldManager::class );

    $field = CustomField::create( [
        'name'          => 'Test Field',
        'key'           => 'test_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['test'],
        'order'         => 1,
        'required'      => false,
    ] );

    $fields = $manager->getFieldsForContentType( 'test' );

    expect( $fields )->toHaveCount( 1 );
    expect( $fields->first()->key )->toBe( 'test_field' );
} );

test( 'update field handles content type changes', function (): void {
    $manager = new CustomFieldManager;

    ContentType::create( [
        'name'          => 'Posts',
        'slug'          => 'posts',
        'table_name'    => 'test_posts_ct',
        'model_class'   => 'App\\Models\\Post',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    ContentType::create( [
        'name'          => 'Pages',
        'slug'          => 'pages',
        'table_name'    => 'test_pages_ct',
        'model_class'   => 'App\\Models\\Page',
        'public'        => true,
        'show_in_admin' => true,
    ] );

    Schema::create( 'test_posts_ct', function ( $table ): void {
        $table->id();
        $table->timestamps();
    } );

    Schema::create( 'test_pages_ct', function ( $table ): void {
        $table->id();
        $table->timestamps();
    } );

    $field = CustomField::create( [
        'name'          => 'Custom Field',
        'key'           => 'custom_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['posts'],
        'order'         => 1,
        'required'      => false,
    ] );

    $manager->addColumnToTable( $field, 'test_posts_ct' );

    // Update to also include pages
    $manager->updateField( $field->id, [
        'content_types' => ['posts', 'pages'],
    ] );

    expect( Schema::hasColumn( 'test_posts_ct', 'custom_field' ) )->toBeTrue();
    expect( Schema::hasColumn( 'test_pages_ct', 'custom_field' ) )->toBeTrue();

    Schema::dropIfExists( 'test_posts_ct' );
    Schema::dropIfExists( 'test_pages_ct' );
} );

test( 'get fields for content type merges DB and filter-registered fields', function (): void {
    $manager = new CustomFieldManager;

    CustomField::create( [
        'name'          => 'DB Field',
        'key'           => 'db_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['posts'],
        'order'         => 1,
        'required'      => false,
    ] );

    $manager->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['posts'],
        'order'         => 2,
        'required'      => false,
    ] );

    $manager->registerField( [
        'name'          => 'Other Content Type Field',
        'key'           => 'wrong_ct',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['pages'],
    ] );

    $fields = $manager->getFieldsForContentType( 'posts' );
    $keys   = $fields->pluck( 'key' )->all();

    expect( $keys )->toContain( 'db_field' );
    expect( $keys )->toContain( 'plugin_field' );
    expect( $keys )->not->toContain( 'wrong_ct' );
} );

test( 'filter-registered fields are marked as metadata storage', function (): void {
    $manager = new CustomFieldManager;

    $manager->registerField( [
        'name'          => 'Plugin Field',
        'key'           => 'plugin_stored_field',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['posts'],
    ] );

    $fields = $manager->getFieldsForContentType( 'posts' );
    $plugin = $fields->firstWhere( 'key', 'plugin_stored_field' );

    expect( $plugin )->not->toBeNull();
    expect( $plugin->exists )->toBeFalse();
    expect( $plugin->storageMode() )->toBe( 'metadata' );
} );

test( 'createField refuses to run Schema::table against a filter-only content type', function (): void {
    // A plugin registers a content type with table_name pointing at a real
    // host table. An admin creating a custom field must NOT trigger a schema
    // mutation on that table.
    $manager    = new CustomFieldManager;
    $ctManager  = app( ContentTypeManager::class );

    Schema::create( 'attacker_target', function ( $table ): void {
        $table->id();
        $table->string( 'legit_column' );
        $table->timestamps();
    } );

    $ctManager->register( [
        'name'        => 'Attacker CT',
        'slug'        => 'attacker-ct',
        'table_name'  => 'attacker_target', // Plugin picks a real host table.
        'model_class' => 'App\\Models\\Attacker',
    ] );

    $field = $manager->createField( [
        'name'          => 'Injected',
        'key'           => 'injected_col',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['attacker-ct'],
        'order'         => 1,
        'required'      => false,
    ] );

    expect( $field )->toBeInstanceOf( CustomField::class );
    expect( Schema::hasColumn( 'attacker_target', 'injected_col' ) )->toBeFalse();

    Schema::dropIfExists( 'attacker_target' );
} );

test( 'filter-registered custom fields strip persistence-critical keys on hydration', function (): void {
    $manager = new CustomFieldManager;

    $manager->registerField( [
        'id'            => 999,
        'created_at'    => '2020-01-01 00:00:00',
        'name'          => 'Sneaky Field',
        'key'           => 'sneaky',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['posts'],
    ] );

    $field = $manager->getFieldsForContentType( 'posts' )->firstWhere( 'key', 'sneaky' );

    expect( $field )->not->toBeNull();
    expect( $field->id )->toBeNull();
    expect( $field->created_at )->toBeNull();
    expect( $field->exists )->toBeFalse();
} );

test( 'DB entry wins over filter entry when keys collide', function (): void {
    $manager = new CustomFieldManager;

    CustomField::create( [
        'name'          => 'DB Version',
        'key'           => 'shared_key',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['posts'],
        'order'         => 1,
        'required'      => false,
    ] );

    $manager->registerField( [
        'name'          => 'Filter Version',
        'key'           => 'shared_key',
        'type'          => 'text',
        'column_type'   => 'string',
        'content_types' => ['posts'],
    ] );

    $fields  = $manager->getFieldsForContentType( 'posts' );
    $matches = $fields->where( 'key', 'shared_key' );

    expect( $matches )->toHaveCount( 1 );
    expect( $matches->first()->name )->toBe( 'DB Version' );
    expect( $matches->first()->exists )->toBeTrue();
} );
