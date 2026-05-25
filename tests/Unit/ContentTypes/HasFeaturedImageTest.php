<?php

declare(strict_types=1);

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\Concerns\HasFeaturedImage;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use ArtisanPackUI\CMSFramework\Tests\Support\TestFeaturedImageModel;
use ArtisanPackUI\CMSFramework\Tests\Support\TestMedia;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    // The cms-framework migrations for `featureables`, `posts`, and `pages`
    // reference a `media` table via foreign keys. SQLite accepts the FK
    // definitions even when the referenced table is absent, so the
    // package's own migrations pass; the host table just needs to exist
    // before we attach rows in tests.
    if (! Schema::hasTable('media')) {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('test_featured_image_models')) {
        Schema::create('test_featured_image_models', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }
});

test('featuredImage returns a MorphToMany relation against the featureables pivot', function (): void {
    $model = TestFeaturedImageModel::create(['name' => 'Subject']);

    $relation = $model->featuredImage();

    expect($relation)->toBeInstanceOf(MorphToMany::class);
    expect($relation->getTable())->toBe('featureables');
});

test('setFeaturedImage attaches a media row via the pivot table', function (): void {
    $model = TestFeaturedImageModel::create(['name' => 'Subject']);
    $media = TestMedia::create(['url' => 'https://example.test/a.jpg']);

    $model->setFeaturedImage($media->id);

    $row = DB::table('featureables')->first();

    expect($row)->not->toBeNull();
    expect((int) $row->media_id)->toBe($media->id);
    expect((int) $row->featurable_id)->toBe($model->id);
    expect($row->featurable_type)->toBe(TestFeaturedImageModel::class);
    expect($model->featuredImage()->count())->toBe(1);
});

test('setFeaturedImage replaces the existing featured image rather than appending', function (): void {
    $model = TestFeaturedImageModel::create(['name' => 'Subject']);
    $first = TestMedia::create(['url' => 'https://example.test/first.jpg']);
    $second = TestMedia::create(['url' => 'https://example.test/second.jpg']);

    $model->setFeaturedImage($first->id);
    $model->setFeaturedImage($second->id);

    $rows = DB::table('featureables')
        ->where('featurable_type', TestFeaturedImageModel::class)
        ->where('featurable_id', $model->id)
        ->get();

    expect($rows)->toHaveCount(1);
    expect((int) $rows->first()->media_id)->toBe($second->id);
});

test('removeFeaturedImage detaches every row for the host model', function (): void {
    $model = TestFeaturedImageModel::create(['name' => 'Subject']);
    $media = TestMedia::create(['url' => 'https://example.test/a.jpg']);

    $model->setFeaturedImage($media->id);
    expect($model->featuredImage()->count())->toBe(1);

    $model->removeFeaturedImage();

    expect($model->featuredImage()->count())->toBe(0);
    expect(DB::table('featureables')->count())->toBe(0);
});

test('removeFeaturedImage on a model without a featured image is a no-op', function (): void {
    $model = TestFeaturedImageModel::create(['name' => 'Subject']);

    $model->removeFeaturedImage();

    expect(DB::table('featureables')->count())->toBe(0);
});

test('getFeaturedImageUrl returns null when no featured image is attached', function (): void {
    $model = TestFeaturedImageModel::create(['name' => 'Subject']);

    expect($model->getFeaturedImageUrl())->toBeNull();
});

test('getFeaturedImageUrl returns the URL of the attached media row', function (): void {
    $model = TestFeaturedImageModel::create(['name' => 'Subject']);
    $media = TestMedia::create(['url' => 'https://example.test/a.jpg']);

    $model->setFeaturedImage($media->id);

    expect($model->getFeaturedImageUrl())->toBe('https://example.test/a.jpg');
});

test('setFeaturedImage isolates featured images per host model row', function (): void {
    $modelA = TestFeaturedImageModel::create(['name' => 'A']);
    $modelB = TestFeaturedImageModel::create(['name' => 'B']);
    $mediaA = TestMedia::create(['url' => 'https://example.test/a.jpg']);
    $mediaB = TestMedia::create(['url' => 'https://example.test/b.jpg']);

    $modelA->setFeaturedImage($mediaA->id);
    $modelB->setFeaturedImage($mediaB->id);

    expect($modelA->featuredImage()->first()->id)->toBe($mediaA->id);
    expect($modelB->featuredImage()->first()->id)->toBe($mediaB->id);
});

test('post model still uses HasFeaturedImage trait', function (): void {
    expect(in_array(HasFeaturedImage::class, class_uses_recursive(Post::class), true))->toBeTrue();
});

test('page model still uses HasFeaturedImage trait', function (): void {
    expect(in_array(HasFeaturedImage::class, class_uses_recursive(Page::class), true))->toBeTrue();
});
