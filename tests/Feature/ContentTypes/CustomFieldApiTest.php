<?php

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\ContentType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
});

test('can list custom fields', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.viewAny')
        ->andReturn(true);

    CustomField::create([
        'name' => 'Price',
        'key' => 'price',
        'type' => 'number',
        'column_type' => 'decimal',
        'content_types' => ['products'],
        'order' => 1,
        'required' => true,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/custom-fields');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['name', 'key', 'type', 'column_type', 'content_types'],
        ],
    ]);
});

test('can create custom field', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.create')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Products',
        'slug' => 'products',
        'table_name' => 'test_products_field',
        'model_class' => 'App\\Models\\Product',
        'public' => true,
        'show_in_admin' => true,
    ]);

    Schema::create('test_products_field', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $data = [
        'name' => 'SKU',
        'key' => 'sku',
        'type' => 'text',
        'column_type' => 'string',
        'content_types' => ['products'],
        'order' => 1,
        'required' => false,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/custom-fields', $data);

    $response->assertCreated();
    $response->assertJsonFragment(['key' => 'sku']);

    expect(CustomField::where('key', 'sku')->exists())->toBeTrue();
    expect(Schema::hasColumn('test_products_field', 'sku'))->toBeTrue();

    Schema::dropIfExists('test_products_field');
});

test('can show single custom field', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.view')
        ->andReturn(true);

    $field = CustomField::create([
        'name' => 'Rating',
        'key' => 'rating',
        'type' => 'number',
        'column_type' => 'integer',
        'content_types' => ['posts'],
        'order' => 1,
        'required' => false,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/custom-fields/'.$field->id);

    $response->assertSuccessful();
    $response->assertJsonFragment(['key' => 'rating', 'name' => 'Rating']);
});

test('can update custom field', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.update')
        ->andReturn(true);

    $field = CustomField::create([
        'name' => 'Status',
        'key' => 'status',
        'type' => 'text',
        'column_type' => 'string',
        'content_types' => ['posts'],
        'order' => 1,
        'required' => false,
    ]);

    $updateData = [
        'name' => 'Post Status',
        'order' => 5,
    ];

    $response = $this->actingAs($this->user)->putJson('/api/v1/custom-fields/'.$field->id, $updateData);

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Post Status']);

    $field->refresh();
    expect($field->name)->toBe('Post Status');
    expect($field->order)->toBe(5);
});

test('can delete custom field', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.delete')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Events',
        'slug' => 'events',
        'table_name' => 'test_events_field',
        'model_class' => 'App\\Models\\Event',
        'public' => true,
        'show_in_admin' => true,
    ]);

    Schema::create('test_events_field', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('location');
        $table->timestamps();
    });

    $field = CustomField::create([
        'name' => 'Location',
        'key' => 'location',
        'type' => 'text',
        'column_type' => 'string',
        'content_types' => ['events'],
        'order' => 1,
        'required' => false,
    ]);

    expect(Schema::hasColumn('test_events_field', 'location'))->toBeTrue();

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/custom-fields/'.$field->id);

    $response->assertNoContent();
    expect(CustomField::find($field->id))->toBeNull();
    expect(Schema::hasColumn('test_events_field', 'location'))->toBeFalse();

    Schema::dropIfExists('test_events_field');
});

test('unauthorized user cannot create custom field', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.create')
        ->andReturn(false);

    $data = [
        'name' => 'Price',
        'key' => 'price',
        'type' => 'number',
        'column_type' => 'decimal',
        'content_types' => ['products'],
        'order' => 1,
        'required' => false,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/custom-fields', $data);

    $response->assertForbidden();
});

test('unauthorized user cannot update custom field', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.update')
        ->andReturn(false);

    $field = CustomField::create([
        'name' => 'Test Field',
        'key' => 'test_field',
        'type' => 'text',
        'column_type' => 'string',
        'content_types' => ['posts'],
        'order' => 1,
        'required' => false,
    ]);

    $response = $this->actingAs($this->user)->putJson('/api/v1/custom-fields/'.$field->id, ['name' => 'Updated']);

    $response->assertForbidden();
});

test('unauthorized user cannot delete custom field', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.delete')
        ->andReturn(false);

    $field = CustomField::create([
        'name' => 'Test Field',
        'key' => 'test_field',
        'type' => 'text',
        'column_type' => 'string',
        'content_types' => ['posts'],
        'order' => 1,
        'required' => false,
    ]);

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/custom-fields/'.$field->id);

    $response->assertForbidden();
});

test('returns 404 when custom field not found', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.view')
        ->andReturn(true);

    $response = $this->actingAs($this->user)->getJson('/api/v1/custom-fields/999');

    $response->assertNotFound();
});

test('validation fails when required fields are missing', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.create')
        ->andReturn(true);

    $data = [
        'name' => 'Price',
        // Missing required fields: key, type, column_type, content_types
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/custom-fields', $data);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['key', 'type', 'column_type', 'content_types']);
});

test('custom field key must be unique', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.create')
        ->andReturn(true);

    CustomField::create([
        'name' => 'Price',
        'key' => 'price',
        'type' => 'number',
        'column_type' => 'decimal',
        'content_types' => ['products'],
        'order' => 1,
        'required' => false,
    ]);

    $data = [
        'name' => 'Another Price',
        'key' => 'price', // Duplicate key
        'type' => 'number',
        'column_type' => 'decimal',
        'content_types' => ['services'],
        'order' => 2,
        'required' => false,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/custom-fields', $data);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['key']);
});

test('creating custom field generates migration', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.create')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Articles',
        'slug' => 'articles',
        'table_name' => 'test_articles_migration',
        'model_class' => 'App\\Models\\Article',
        'public' => true,
        'show_in_admin' => true,
    ]);

    Schema::create('test_articles_migration', function ($table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    $data = [
        'name' => 'Subtitle',
        'key' => 'subtitle',
        'type' => 'text',
        'column_type' => 'string',
        'content_types' => ['articles'],
        'order' => 1,
        'required' => false,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/v1/custom-fields', $data);

    $response->assertCreated();
    expect(Schema::hasColumn('test_articles_migration', 'subtitle'))->toBeTrue();

    Schema::dropIfExists('test_articles_migration');
});

test('deleting custom field removes column from tables', function () {
    $this->user->shouldReceive('can')
        ->with('customFields.delete')
        ->andReturn(true);

    ContentType::create([
        'name' => 'Reviews',
        'slug' => 'reviews',
        'table_name' => 'test_reviews_delete',
        'model_class' => 'App\\Models\\Review',
        'public' => true,
        'show_in_admin' => true,
    ]);

    Schema::create('test_reviews_delete', function ($table) {
        $table->id();
        $table->string('title');
        $table->integer('rating');
        $table->timestamps();
    });

    $field = CustomField::create([
        'name' => 'Rating',
        'key' => 'rating',
        'type' => 'number',
        'column_type' => 'integer',
        'content_types' => ['reviews'],
        'order' => 1,
        'required' => false,
    ]);

    expect(Schema::hasColumn('test_reviews_delete', 'rating'))->toBeTrue();

    $response = $this->actingAs($this->user)->deleteJson('/api/v1/custom-fields/'.$field->id);

    $response->assertNoContent();
    expect(Schema::hasColumn('test_reviews_delete', 'rating'))->toBeFalse();

    Schema::dropIfExists('test_reviews_delete');
});
